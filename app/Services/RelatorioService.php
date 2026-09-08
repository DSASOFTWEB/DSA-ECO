<?php

namespace App\Services;

use App\Models\Acesso;
use App\Models\CaixaMovimentacao;
use App\Models\Comissao;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\Contrato;
use App\Models\Mensalidade;
use App\Models\Venda;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Consultas agregadas usadas pelo Dashboard e pelos relatórios PDF/Excel
 * (App\Services\Relatorios\*Exporter). Mantidas separadas dos exporters
 * para que a mesma consulta sirva tanto a tela quanto o arquivo exportado.
 */
class RelatorioService
{
    public function resumoFinanceiroMensal(?Carbon $mes = null): array
    {
        $mes ??= now();
        $inicio = $mes->copy()->startOfMonth();
        $fim = $mes->copy()->endOfMonth();

        // "previsto/pendente/atrasado" olham pra quando a mensalidade VENCE
        // neste mês (quanto cai pra receber). "recebido" é diferente de
        // propósito: olha pra quando o pagamento de fato ACONTECEU — uma
        // mensalidade atrasada de agosto paga hoje em setembro é dinheiro
        // que entrou em setembro, não em agosto, então precisa contar aqui.
        $mensalidadesPorVencimento = Mensalidade::whereBetween('data_vencimento', [$inicio, $fim]);

        return [
            'previsto' => (float) (clone $mensalidadesPorVencimento)->sum('valor_total'),
            'recebido' => (float) Mensalidade::where('status', 'pago')->whereBetween('data_pagamento', [$inicio, $fim])->sum('valor_total'),
            'pendente' => (float) (clone $mensalidadesPorVencimento)->where('status', 'pendente')->sum('valor_total'),
            'atrasado' => (float) (clone $mensalidadesPorVencimento)->where('status', 'atrasado')->sum('valor_total'),
            'vendas_produtos' => (float) Venda::where('status', 'pago')->whereBetween('created_at', [$inicio, $fim])->sum('valor_total'),
        ];
    }

    /**
     * Faturamento do mês de verdade: mensalidades recebidas no mês (por
     * data_pagamento, não vencimento) + vendas pagas no mês (PDV/checkout).
     * É a mesma fórmula de faturadoHoje(), só que na janela do mês inteiro
     * — vai "somando" à medida que pagamentos/vendas acontecem no mês.
     */
    public function faturadoNoMes(?Carbon $mes = null): float
    {
        $resumo = $this->resumoFinanceiroMensal($mes);

        return round($resumo['recebido'] + $resumo['vendas_produtos'], 2);
    }

    public function inadimplenciaPorUnidade(): \Illuminate\Support\Collection
    {
        return Mensalidade::query()
            ->join('contratos', 'contratos.id', '=', 'mensalidades.contrato_id')
            ->join('unidades', 'unidades.id', '=', 'contratos.unidade_id')
            ->where('mensalidades.status', 'atrasado')
            ->groupBy('unidades.id', 'unidades.nome')
            ->select('unidades.id', 'unidades.nome', DB::raw('count(*) as total_mensalidades'), DB::raw('sum(mensalidades.valor_total) as total_valor'))
            ->get();
    }

    public function contratosAtivosNoMes(?Carbon $mes = null): int
    {
        $mes ??= now();

        return Contrato::where('status', 'ativo')
            ->where('data_inicio', '<=', $mes->copy()->endOfMonth())
            ->count();
    }

    public function rankingVendedores(Carbon $inicio, Carbon $fim): \Illuminate\Support\Collection
    {
        return Comissao::query()
            ->join('users', 'users.id', '=', 'comissoes.vendedor_id')
            ->whereBetween('comissoes.created_at', [$inicio, $fim])
            ->groupBy('users.id', 'users.name')
            ->select('users.id', 'users.name', DB::raw('sum(comissoes.valor) as total_comissao'), DB::raw('count(*) as total_negocios'))
            ->orderByDesc('total_comissao')
            ->get();
    }

    public function kpisDashboard(): array
    {
        return [
            'clientes_ativos' => \App\Models\Cliente::where('status', 'ativo')->count(),
            'contratos_ativos' => Contrato::where('status', 'ativo')->count(),
            'mensalidades_atrasadas' => Mensalidade::where('status', 'atrasado')->count(),
            'faturamento_mes' => $this->faturadoNoMes(),
            'entradas_hoje' => $this->baseEntradasContabilizadas()->whereDate(DB::raw('COALESCE(validado_em, registrado_em)'), now()->toDateString())->count(),
            'contas_a_pagar' => (float) ContaPagar::whereIn('status', ['pendente', 'atrasado'])->sum('valor'),
            'contas_a_receber' => (float) ContaReceber::whereIn('status', ['pendente', 'atrasado'])->sum('valor'),
            'ticket_medio' => $this->ticketMedioMensal(),
            'faturado_hoje' => $this->faturadoHoje(),
        ];
    }

    /**
     * Tudo que entrou de receita HOJE: mensalidades pagas hoje + vendas
     * pagas hoje (produtos + entradas avulsas do PDV/checkout). Mesma
     * fórmula do "Faturamento do mês", só que só do dia — diferente do card
     * "Entradas hoje", que conta PESSOAS que passaram, não dinheiro.
     */
    public function faturadoHoje(): float
    {
        $hoje = now()->toDateString();

        $mensalidades = (float) Mensalidade::where('status', 'pago')->whereDate('data_pagamento', $hoje)->sum('valor_total');
        $vendas = (float) Venda::where('status', 'pago')->whereDate('created_at', $hoje)->sum('valor_total');

        return round($mensalidades + $vendas, 2);
    }

    /**
     * Valor médio das vendas pagas no mês (produtos + entradas avulsas do
     * PDV/checkout). Não considera mensalidades, que têm valor fixo por
     * plano e não representam um "ticket" de venda.
     */
    public function ticketMedioMensal(?Carbon $mes = null): float
    {
        $mes ??= now();
        $vendas = Venda::whereBetween('created_at', [$mes->copy()->startOfMonth(), $mes->copy()->endOfMonth()])
            ->where('status', 'pago');

        $quantidade = (clone $vendas)->count();

        return $quantidade > 0 ? round((float) (clone $vendas)->sum('valor_total') / $quantidade, 2) : 0.0;
    }

    /**
     * Saldo acumulado do caixa (todas as unidades da empresa) dia a dia,
     * no formato OHLC (abertura/máxima/mínima/fechamento) usado pelo
     * gráfico de velas do Dashboard. "Abertura" é o saldo no início do
     * dia; ao longo do dia o saldo sobe com entradas e desce com saídas —
     * "máxima"/"mínima" marcam os picos alcançados nesse trajeto.
     *
     * @return array<int, array{data: string, open: float, high: float, low: float, close: float}>
     */
    public function velasCaixa(int $dias = 14): array
    {
        $inicio = now()->copy()->subDays($dias - 1)->startOfDay();
        $fim = now()->copy()->endOfDay();

        $saldoInicial = (float) CaixaMovimentacao::whereHas('caixa')
            ->where('created_at', '<', $inicio)
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE -valor END), 0) as saldo")
            ->value('saldo');

        $movimentos = CaixaMovimentacao::whereHas('caixa')
            ->whereBetween('created_at', [$inicio, $fim])
            ->orderBy('created_at')
            ->get(['created_at', 'tipo', 'valor'])
            ->groupBy(fn ($mov) => $mov->created_at->toDateString());

        $velas = [];
        $saldo = $saldoInicial;
        $cursor = $inicio->copy();

        while ($cursor->lte($fim)) {
            $chave = $cursor->toDateString();
            $open = $saldo;
            $high = $saldo;
            $low = $saldo;

            foreach ($movimentos->get($chave, collect()) as $mov) {
                $saldo += $mov->tipo === 'entrada' ? (float) $mov->valor : -(float) $mov->valor;
                $high = max($high, $saldo);
                $low = min($low, $saldo);
            }

            $velas[] = [
                'data' => $chave,
                'open' => round($open, 2),
                'high' => round($high, 2),
                'low' => round($low, 2),
                'close' => round($saldo, 2),
            ];

            $cursor->addDay();
        }

        return $velas;
    }

    /**
     * Entradas autorizadas de hoje, agrupadas por hora (0-23). Não há
     * controle de saída no sistema, então isto é só contagem de entradas —
     * usado pelo card "Entradas por hora" do Dashboard. Vouchers online
     * (venda avulsa ou check-in pelo link público) só entram na conta
     * depois de validados na portaria — antes disso o ingresso existe, mas
     * a pessoa ainda não passou de fato (ver Acesso::ORIGENS_QUE_EXIGEM_VALIDACAO).
     *
     * @return array<int, int> hora (0-23) => total de entradas
     */
    public function entradasPorHoraHoje(): array
    {
        $porHora = $this->baseEntradasContabilizadas()
            ->whereDate(DB::raw('COALESCE(validado_em, registrado_em)'), now()->toDateString())
            ->selectRaw('HOUR(COALESCE(validado_em, registrado_em)) as hora, COUNT(*) as total')
            ->groupBy('hora')
            ->pluck('total', 'hora');

        return collect(range(0, 23))->mapWithKeys(fn ($hora) => [$hora => (int) ($porHora[$hora] ?? 0)])->all();
    }

    /**
     * Acesso conta como entrada de verdade quando: é de uma origem
     * presencial (já tem gente conferindo na hora), OU é online mas já foi
     * validado na portaria.
     */
    protected function baseEntradasContabilizadas()
    {
        return Acesso::daEmpresa(\Illuminate\Support\Facades\Auth::user()->empresa_id)
            ->where('tipo', 'entrada')
            ->where('autorizado', true)
            ->where(function ($q) {
                $q->whereNotIn('origem', Acesso::ORIGENS_QUE_EXIGEM_VALIDACAO)
                    ->orWhereNotNull('validado_em');
            });
    }
}
