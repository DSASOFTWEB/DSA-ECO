<?php

namespace App\Services;

use App\Models\Acesso;
use App\Models\Caixa;
use App\Models\CaixaMovimentacao;
use App\Models\Comissao;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\Contrato;
use App\Models\Mensalidade;
use App\Models\TipoEntrada;
use App\Models\Venda;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Consultas agregadas usadas pelo Dashboard e pelos relatórios PDF/Excel
 * (App\Services\Relatorios\*Exporter). Mantidas separadas dos exporters
 * para que a mesma consulta sirva tanto a tela quanto o arquivo exportado.
 */
class RelatorioService
{
    public function __construct(protected CaixaService $caixaService) {}

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
     * Entradas de hoje agrupadas por tipo de entrada (ingresso avulso do
     * PDV/checkout) — um item por tipo ATIVO da empresa (inclusive com 0),
     * usado pelos cards extras do Dashboard, pela origem de acesso
     * Acesso::tipo_entrada_id, preenchido em toda venda avulsa (ver
     * VendaService::criarAcessosAvulsos()).
     *
     * @return \Illuminate\Support\Collection<int, array{id:int, nome:string, quantidade:int}>
     */
    public function entradasHojePorTipoEntrada(): \Illuminate\Support\Collection
    {
        $contagens = $this->baseEntradasContabilizadas()
            ->whereDate(DB::raw('COALESCE(validado_em, registrado_em)'), now()->toDateString())
            ->whereNotNull('tipo_entrada_id')
            ->selectRaw('tipo_entrada_id, COUNT(*) as total')
            ->groupBy('tipo_entrada_id')
            ->pluck('total', 'tipo_entrada_id');

        return TipoEntrada::ativos()->orderBy('nome')->get(['id', 'nome'])
            ->map(fn (TipoEntrada $tipo) => [
                'id' => $tipo->id,
                'nome' => $tipo->nome,
                'quantidade' => (int) ($contagens[$tipo->id] ?? 0),
            ]);
    }

    /**
     * "Quanto tem em cada caixa agora" + consolidado geral — um item por
     * caixa ABERTO da empresa (`CaixaService::resumoCaixaAberto`), mais os
     * totais somados. Caixas já fechados não entram aqui (o saldo deles já
     * foi definitivamente registrado no fechamento).
     *
     * @return array{caixas: \Illuminate\Support\Collection, total_entradas: float, total_saidas: float, saldo_geral: float}
     */
    public function saldoConsolidadoCaixas(): array
    {
        $empresaId = \Illuminate\Support\Facades\Auth::user()->empresa_id;

        $caixas = $this->caixaService->caixasAbertosDaEmpresa($empresaId)
            ->map(fn ($caixa) => $this->caixaService->resumoCaixaAberto($caixa));

        return [
            'caixas' => $caixas,
            'total_entradas' => round((float) $caixas->sum('entradas'), 2),
            'total_saidas' => round((float) $caixas->sum('saidas'), 2),
            'saldo_geral' => round((float) $caixas->sum('saldo_atual'), 2),
        ];
    }

    /**
     * Relatório "Movimentação por Caixa" / "Consolidado de todos os
     * Caixas" (são o mesmo dado — a view/PDF de cada um só escolhe quais
     * colunas mostrar). Um item por caixa que existiu durante o período
     * (aberto antes do fim E, se já fechado, fechado depois do início),
     * com saldo inicial (abertura + tudo antes do período), entradas e
     * saídas "normais" separadas de transferências e ajustes, e saldo
     * final. Reconciliação: saldo_final = saldo_inicial + entradas +
     * transferencias_recebidas - saidas - transferencias_enviadas + ajustes.
     *
     * @return Collection<int, array{caixa: Caixa, saldo_inicial: float, entradas: float, saidas: float, transferencias_recebidas: float, transferencias_enviadas: float, ajustes: float, saldo_final: float}>
     */
    public function relatorioPorCaixaPeriodo(Carbon $inicio, Carbon $fim): Collection
    {
        $empresaId = Auth::user()->empresa_id;

        $caixas = Caixa::where('empresa_id', $empresaId)
            ->where('data_abertura', '<=', $fim)
            ->where(fn ($q) => $q->whereNull('data_fechamento')->orWhere('data_fechamento', '>=', $inicio))
            ->with(['terminal', 'unidade'])
            ->orderBy('data_abertura')
            ->get();

        return $caixas->map(function (Caixa $caixa) use ($inicio, $fim) {
            $antesDoInicio = (float) CaixaMovimentacao::where('caixa_id', $caixa->id)
                ->where('created_at', '<', $inicio)
                ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE -valor END), 0) as saldo")
                ->value('saldo');
            $saldoInicial = round((float) $caixa->valor_abertura + $antesDoInicio, 2);

            $noPeriodo = fn () => CaixaMovimentacao::where('caixa_id', $caixa->id)->whereBetween('created_at', [$inicio, $fim]);

            $entradas = (float) $noPeriodo()->where('tipo', 'entrada')->whereNotIn('categoria', ['transferencia_entrada', 'ajuste'])->sum('valor');
            $saidas = (float) $noPeriodo()->where('tipo', 'saida')->whereNotIn('categoria', ['transferencia_saida', 'ajuste'])->sum('valor');
            $transferenciasRecebidas = (float) $noPeriodo()->where('tipo', 'entrada')->where('categoria', 'transferencia_entrada')->sum('valor');
            $transferenciasEnviadas = (float) $noPeriodo()->where('tipo', 'saida')->where('categoria', 'transferencia_saida')->sum('valor');
            $ajustes = round(
                (float) $noPeriodo()->where('tipo', 'entrada')->where('categoria', 'ajuste')->sum('valor')
                - (float) $noPeriodo()->where('tipo', 'saida')->where('categoria', 'ajuste')->sum('valor'),
                2
            );

            return [
                'caixa' => $caixa,
                'saldo_inicial' => $saldoInicial,
                'entradas' => $entradas,
                'saidas' => $saidas,
                'transferencias_recebidas' => $transferenciasRecebidas,
                'transferencias_enviadas' => $transferenciasEnviadas,
                'ajustes' => $ajustes,
                'saldo_final' => round($saldoInicial + $entradas + $transferenciasRecebidas - $saidas - $transferenciasEnviadas + $ajustes, 2),
            ];
        });
    }

    /**
     * Relatórios "Entradas por Período" / "Saídas por Período": lista
     * detalhada das movimentações de um tipo no período, com os mesmos
     * filtros da tela de Movimentações (caixa, categoria, usuário).
     *
     * @return array{itens: Collection<int, CaixaMovimentacao>, total: float}
     */
    public function movimentacoesPeriodo(Carbon $inicio, Carbon $fim, string $tipo, array $filtros = []): array
    {
        $itens = CaixaMovimentacao::daEmpresa(Auth::user()->empresa_id)
            ->with(['caixa.terminal', 'caixa.unidade', 'usuario'])
            ->where('tipo', $tipo)
            ->whereBetween('created_at', [$inicio, $fim])
            ->when($filtros['caixa_id'] ?? null, fn ($q, $v) => $q->where('caixa_id', $v))
            ->when($filtros['categoria'] ?? null, fn ($q, $v) => $q->where('categoria', $v))
            ->when($filtros['usuario_id'] ?? null, fn ($q, $v) => $q->where('usuario_id', $v))
            ->orderBy('created_at')
            ->get();

        return ['itens' => $itens, 'total' => round((float) $itens->sum('valor'), 2)];
    }

    /**
     * Relatório "Fluxo de Caixa": entradas/saídas/saldo do dia (saldo aqui
     * é o líquido DAQUELE dia — entradas menos saídas —, não um saldo
     * acumulado; para o acumulado dia a dia já existe `velasCaixa()`, usado
     * no gráfico do Dashboard). Preenche todos os dias do período, mesmo
     * sem movimentação (fica 0,00), pra never pular data no relatório.
     *
     * @return Collection<int, array{data: Carbon, entradas: float, saidas: float, saldo: float}>
     */
    public function fluxoDeCaixaPeriodo(Carbon $inicio, Carbon $fim): Collection
    {
        $porDia = CaixaMovimentacao::daEmpresa(Auth::user()->empresa_id)
            ->whereBetween('created_at', [$inicio, $fim])
            ->selectRaw("DATE(created_at) as dia, SUM(CASE WHEN tipo = 'entrada' THEN valor ELSE 0 END) as entradas, SUM(CASE WHEN tipo = 'saida' THEN valor ELSE 0 END) as saidas")
            ->groupBy('dia')
            ->get()
            ->keyBy(fn ($linha) => (string) $linha->dia);

        $dias = collect();
        $cursor = $inicio->copy()->startOfDay();
        $fimDia = $fim->copy()->startOfDay();

        while ($cursor->lte($fimDia)) {
            $linha = $porDia->get($cursor->toDateString());
            $entradas = $linha ? (float) $linha->entradas : 0.0;
            $saidas = $linha ? (float) $linha->saidas : 0.0;

            $dias->push([
                'data' => $cursor->copy(),
                'entradas' => $entradas,
                'saidas' => $saidas,
                'saldo' => round($entradas - $saidas, 2),
            ]);

            $cursor->addDay();
        }

        return $dias;
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
