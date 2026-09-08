<?php

namespace App\Services;

use App\Exceptions\NegocioException;
use App\Models\Caixa;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Contas a pagar/receber "avulsas" — despesas e recebimentos que não passam
 * pelo fluxo de Mensalidade (contratos) nem de Venda (PDV/checkout), ex:
 * aluguel, energia, salário, reembolso, patrocínio. Quando existe um caixa
 * aberto na unidade, a baixa também lança uma movimentação de caixa; se não
 * houver caixa aberto (ex: pagamento via transferência bancária), a conta é
 * apenas marcada como paga/recebida, sem afetar o caixa físico.
 */
class FinanceiroGestaoService
{
    public function criarContaPagar(array $dados, User $usuario): ContaPagar
    {
        return ContaPagar::create([
            ...$dados,
            'criado_por_id' => $usuario->id,
            'status' => 'pendente',
        ]);
    }

    public function criarContaReceber(array $dados, User $usuario): ContaReceber
    {
        return ContaReceber::create([
            ...$dados,
            'criado_por_id' => $usuario->id,
            'status' => 'pendente',
        ]);
    }

    public function marcarContaPagarPaga(ContaPagar $conta, User $usuario, string $formaPagamento, ?Caixa $caixa = null): ContaPagar
    {
        if ($conta->estaPaga()) {
            throw new NegocioException('Esta conta já está marcada como paga.');
        }

        if ($conta->status === 'cancelado') {
            throw new NegocioException('Não é possível dar baixa em uma conta cancelada.');
        }

        return DB::transaction(function () use ($conta, $usuario, $formaPagamento, $caixa) {
            $movimentacaoId = null;

            if ($caixa && $caixa->estaAberto()) {
                $movimentacaoId = $caixa->movimentacoes()->create([
                    'tipo' => 'saida',
                    'categoria' => 'despesa',
                    'descricao' => "Pagamento: {$conta->descricao} ({$conta->fornecedor})",
                    'valor' => $conta->valor,
                    'forma_pagamento' => $formaPagamento,
                    'referencia_type' => ContaPagar::class,
                    'referencia_id' => $conta->id,
                    'usuario_id' => $usuario->id,
                ])->id;
            }

            $conta->update([
                'status' => 'pago',
                'data_pagamento' => now()->toDateString(),
                'forma_pagamento' => $formaPagamento,
                'caixa_movimentacao_id' => $movimentacaoId,
            ]);

            return $conta->fresh();
        });
    }

    public function marcarContaReceberRecebida(ContaReceber $conta, User $usuario, string $formaPagamento, ?Caixa $caixa = null): ContaReceber
    {
        if ($conta->estaRecebida()) {
            throw new NegocioException('Esta conta já está marcada como recebida.');
        }

        if ($conta->status === 'cancelado') {
            throw new NegocioException('Não é possível dar baixa em uma conta cancelada.');
        }

        return DB::transaction(function () use ($conta, $usuario, $formaPagamento, $caixa) {
            $movimentacaoId = null;

            if ($caixa && $caixa->estaAberto()) {
                $movimentacaoId = $caixa->movimentacoes()->create([
                    'tipo' => 'entrada',
                    'categoria' => 'outro',
                    'descricao' => "Recebimento: {$conta->descricao} ({$conta->nomePagador()})",
                    'valor' => $conta->valor,
                    'forma_pagamento' => $formaPagamento,
                    'referencia_type' => ContaReceber::class,
                    'referencia_id' => $conta->id,
                    'usuario_id' => $usuario->id,
                ])->id;
            }

            $conta->update([
                'status' => 'recebido',
                'data_recebimento' => now()->toDateString(),
                'forma_pagamento' => $formaPagamento,
                'caixa_movimentacao_id' => $movimentacaoId,
            ]);

            return $conta->fresh();
        });
    }

    public function cancelarContaPagar(ContaPagar $conta): void
    {
        if ($conta->estaPaga()) {
            throw new NegocioException('Não é possível cancelar uma conta já paga.');
        }

        $conta->update(['status' => 'cancelado']);
    }

    public function cancelarContaReceber(ContaReceber $conta): void
    {
        if ($conta->estaRecebida()) {
            throw new NegocioException('Não é possível cancelar uma conta já recebida.');
        }

        $conta->update(['status' => 'cancelado']);
    }

    /**
     * Roda diariamente (scheduler) marcando como atrasada toda conta
     * pendente cujo vencimento já passou.
     *
     * @return array{pagar: int, receber: int}
     */
    public function aplicarAtrasos(): array
    {
        $pagar = ContaPagar::where('status', 'pendente')
            ->whereDate('data_vencimento', '<', now()->toDateString())
            ->update(['status' => 'atrasado']);

        $receber = ContaReceber::where('status', 'pendente')
            ->whereDate('data_vencimento', '<', now()->toDateString())
            ->update(['status' => 'atrasado']);

        return ['pagar' => $pagar, 'receber' => $receber];
    }

    /**
     * Totais para o hub "Financeiro" e para o card do Dashboard.
     */
    public function resumo(): array
    {
        $baseP = ContaPagar::whereIn('status', ['pendente', 'atrasado']);
        $baseR = ContaReceber::whereIn('status', ['pendente', 'atrasado']);

        return [
            'a_pagar_total' => (float) (clone $baseP)->sum('valor'),
            'a_pagar_atrasado' => (float) (clone $baseP)->where('status', 'atrasado')->sum('valor'),
            'a_pagar_vence_hoje' => (float) (clone $baseP)->whereDate('data_vencimento', now()->toDateString())->sum('valor'),
            'a_receber_total' => (float) (clone $baseR)->sum('valor'),
            'a_receber_atrasado' => (float) (clone $baseR)->where('status', 'atrasado')->sum('valor'),
            'a_receber_vence_hoje' => (float) (clone $baseR)->whereDate('data_vencimento', now()->toDateString())->sum('valor'),
        ];
    }
}
