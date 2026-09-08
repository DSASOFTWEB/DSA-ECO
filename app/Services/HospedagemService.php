<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Caixa;
use App\Models\Cliente;
use App\Models\Hospedagem;
use App\Models\HospedagemConsumo;
use App\Models\Produto;
use App\Models\Quarto;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Controle de pousada: reserva -> check-in -> consumos durante a estadia ->
 * check-out (cobra diárias + consumos de uma vez, lançando no caixa aberto
 * do operador — mesmo mecanismo do PDV). Uma única Hospedagem cobre o ciclo
 * inteiro, com status indicando a fase atual.
 */
class HospedagemService
{
    public function __construct(
        protected EstoqueService $estoqueService,
        protected CaixaService $caixaService,
    ) {}

    /**
     * @param  array{quarto_id:int, cliente_id:int, quantidade_hospedes?:int, data_checkin_prevista:string, data_checkout_prevista:string, observacoes?:string}  $dados
     */
    public function reservar(array $dados, User $operador): Hospedagem
    {
        $checkin = Carbon::parse($dados['data_checkin_prevista'])->startOfDay();
        $checkout = Carbon::parse($dados['data_checkout_prevista'])->startOfDay();

        if (! $checkout->gt($checkin)) {
            throw new NegocioException('A data de check-out precisa ser depois da data de check-in.');
        }

        return DB::transaction(function () use ($dados, $operador, $checkin, $checkout) {
            // Trava a linha do quarto durante a checagem de disponibilidade
            // — mesmo padrão de CaixaService::abrir() — pra duas reservas
            // simultâneas no mesmo quarto/período não passarem juntas.
            $quarto = Quarto::whereKey($dados['quarto_id'])->lockForUpdate()->firstOrFail();
            $cliente = Cliente::findOrFail($dados['cliente_id']);

            $this->garantirDisponibilidade($quarto, $checkin, $checkout);

            return Hospedagem::create([
                'empresa_id' => $quarto->empresa_id,
                'unidade_id' => $quarto->unidade_id,
                'quarto_id' => $quarto->id,
                'cliente_id' => $cliente->id,
                'quantidade_hospedes' => $dados['quantidade_hospedes'] ?? 1,
                'valor_diaria' => $quarto->valor_diaria,
                'data_checkin_prevista' => $checkin->toDateString(),
                'data_checkout_prevista' => $checkout->toDateString(),
                'status' => 'reservado',
                'observacoes' => $dados['observacoes'] ?? null,
                'registrado_por_id' => $operador->id,
            ]);
        });
    }

    public function fazerCheckin(Hospedagem $hospedagem): Hospedagem
    {
        if (! $hospedagem->estaReservado()) {
            throw new NegocioException('Só é possível fazer check-in de uma reserva pendente.');
        }

        $hospedagem->update([
            'status' => 'hospedado',
            'data_checkin_real' => now(),
        ]);

        return $hospedagem;
    }

    /**
     * @param  array{produto_id?:int, descricao?:string, quantidade:int, valor_unitario?:float}  $dados
     */
    public function adicionarConsumo(Hospedagem $hospedagem, array $dados, User $operador): HospedagemConsumo
    {
        if (! $hospedagem->estaHospedado()) {
            throw new NegocioException('Só é possível lançar consumo durante a estadia (depois do check-in e antes do check-out).');
        }

        $quantidade = (int) $dados['quantidade'];

        return DB::transaction(function () use ($hospedagem, $dados, $operador, $quantidade) {
            if (! empty($dados['produto_id'])) {
                $produto = Produto::findOrFail($dados['produto_id']);

                if (! $produto->ativo) {
                    throw new NegocioException("O produto \"{$produto->nome}\" está inativo.");
                }

                $this->estoqueService->saidaPorVenda($produto, $quantidade, $hospedagem, $operador->id);

                $valorUnitario = (float) $produto->preco_venda;
                $descricao = null;
                $produtoId = $produto->id;
            } else {
                if (empty($dados['descricao']) || ! isset($dados['valor_unitario'])) {
                    throw new NegocioException('Informe a descrição e o valor do item avulso.');
                }

                $valorUnitario = (float) $dados['valor_unitario'];
                $descricao = $dados['descricao'];
                $produtoId = null;
            }

            return HospedagemConsumo::create([
                'hospedagem_id' => $hospedagem->id,
                'produto_id' => $produtoId,
                'descricao' => $descricao,
                'quantidade' => $quantidade,
                'valor_unitario' => $valorUnitario,
                'subtotal' => round($valorUnitario * $quantidade, 2),
                'registrado_por_id' => $operador->id,
            ]);
        });
    }

    /**
     * Total que vai ser cobrado no check-out — exposto à parte pra tela de
     * fechamento mostrar o resumo antes de confirmar.
     */
    public function calcularTotal(Hospedagem $hospedagem, float $desconto = 0): float
    {
        $totalConsumos = (float) $hospedagem->consumos()->sum('subtotal');

        return round(($this->noites($hospedagem) * (float) $hospedagem->valor_diaria) + $totalConsumos - $desconto, 2);
    }

    public function noites(Hospedagem $hospedagem): int
    {
        $inicio = $hospedagem->data_checkin_real ?? $hospedagem->data_checkin_prevista;

        return max(1, $inicio->diffInDays(now()));
    }

    public function checkout(Hospedagem $hospedagem, Caixa $caixa, User $operador, string $formaPagamento, float $desconto = 0): Hospedagem
    {
        if (! $hospedagem->estaHospedado()) {
            throw new NegocioException('Só é possível fazer check-out de quem já fez check-in.');
        }

        return DB::transaction(function () use ($hospedagem, $caixa, $operador, $formaPagamento, $desconto) {
            $valorTotal = $this->calcularTotal($hospedagem, $desconto);

            if ($valorTotal > 0) {
                $this->caixaService->registrarMovimentacao($caixa, [
                    'tipo' => 'entrada',
                    'categoria' => 'hospedagem',
                    'descricao' => "Check-out hospedagem #{$hospedagem->id} — quarto {$hospedagem->quarto->numero}",
                    'valor' => $valorTotal,
                    'forma_pagamento' => $formaPagamento,
                    'referencia_type' => Hospedagem::class,
                    'referencia_id' => $hospedagem->id,
                    'usuario_id' => $operador->id,
                ]);
            }

            $hospedagem->update([
                'status' => 'finalizado',
                'data_checkout_real' => now(),
                'valor_total' => $valorTotal,
                'desconto' => $desconto,
                'forma_pagamento' => $formaPagamento,
                'caixa_id' => $caixa->id,
            ]);

            return $hospedagem->fresh(['quarto', 'cliente', 'consumos']);
        });
    }

    public function cancelar(Hospedagem $hospedagem): Hospedagem
    {
        if (! $hospedagem->estaReservado()) {
            throw new NegocioException('Só é possível cancelar uma reserva que ainda não teve check-in.');
        }

        $hospedagem->update(['status' => 'cancelado']);

        return $hospedagem;
    }

    /**
     * KPIs pra tela da Pousada — cada consulta já sai automaticamente
     * restrita à empresa do usuário logado via TenantScope (Quarto e
     * Hospedagem usam BelongsToTenant).
     *
     * @return array{ocupados:int, livres:int, checkins_futuros:int, faturado_hoje:float, faturado_mes:float}
     */
    public function resumo(): array
    {
        $quartosAtivos = Quarto::ativos()->count();

        $ocupados = Hospedagem::where('status', 'hospedado')->distinct('quarto_id')->count('quarto_id');

        $checkinsFuturos = Hospedagem::where('status', 'reservado')
            ->whereDate('data_checkin_prevista', '>=', now()->toDateString())
            ->count();

        $faturadoHoje = (float) Hospedagem::where('status', 'finalizado')
            ->whereDate('data_checkout_real', now()->toDateString())
            ->sum('valor_total');

        $faturadoMes = (float) Hospedagem::where('status', 'finalizado')
            ->whereBetween('data_checkout_real', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('valor_total');

        return [
            'ocupados' => $ocupados,
            'livres' => max(0, $quartosAtivos - $ocupados),
            'checkins_futuros' => $checkinsFuturos,
            'faturado_hoje' => $faturadoHoje,
            'faturado_mes' => $faturadoMes,
        ];
    }

    /**
     * Relatório por período — hospedagens finalizadas (check-out já feito e
     * cobrado) com data de check-out real dentro do intervalo informado.
     *
     * @return array{hospedagens:\Illuminate\Support\Collection, total_estadias:int, total_faturado:float, ticket_medio:float, por_quarto:\Illuminate\Support\Collection}
     */
    public function relatorioPeriodo(Carbon $inicio, Carbon $fim): array
    {
        $hospedagens = Hospedagem::with(['quarto', 'cliente'])
            ->where('status', 'finalizado')
            ->whereBetween('data_checkout_real', [$inicio->copy()->startOfDay(), $fim->copy()->endOfDay()])
            ->orderBy('data_checkout_real')
            ->get();

        $totalEstadias = $hospedagens->count();
        $totalFaturado = (float) $hospedagens->sum('valor_total');

        $porQuarto = $hospedagens->groupBy('quarto_id')
            ->map(fn ($grupo) => [
                'quarto' => $grupo->first()->quarto,
                'total_estadias' => $grupo->count(),
                'total_faturado' => (float) $grupo->sum('valor_total'),
            ])
            ->sortByDesc('total_faturado')
            ->values();

        return [
            'hospedagens' => $hospedagens,
            'total_estadias' => $totalEstadias,
            'total_faturado' => $totalFaturado,
            'ticket_medio' => $totalEstadias > 0 ? round($totalFaturado / $totalEstadias, 2) : 0.0,
            'por_quarto' => $porQuarto,
        ];
    }

    /**
     * Impede overbooking: nenhuma outra hospedagem reservada/hospedada pode
     * ter período sobreposto ao pedido, no mesmo quarto.
     */
    protected function garantirDisponibilidade(Quarto $quarto, Carbon $checkin, Carbon $checkout, ?int $ignorarHospedagemId = null): void
    {
        $existeConflito = Hospedagem::where('quarto_id', $quarto->id)
            ->whereIn('status', ['reservado', 'hospedado'])
            ->when($ignorarHospedagemId, fn ($q, $id) => $q->where('id', '!=', $id))
            ->where('data_checkin_prevista', '<', $checkout->toDateString())
            ->where('data_checkout_prevista', '>', $checkin->toDateString())
            ->exists();

        if ($existeConflito) {
            throw new NegocioException("O quarto \"{$quarto->numero}\" já está reservado/ocupado nesse período.");
        }
    }
}
