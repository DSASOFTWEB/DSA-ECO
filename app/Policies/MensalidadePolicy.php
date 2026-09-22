<?php

namespace App\Policies;

use App\Models\Mensalidade;
use App\Models\User;

class MensalidadePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('financeiro.visualizar');
    }

    public function view(User $user, Mensalidade $mensalidade): bool
    {
        return $user->can('financeiro.visualizar') && $user->empresa_id === $mensalidade->empresa_id;
    }

    /**
     * Baixa manual de pagamento (dinheiro/cartão presencial, sem gateway).
     */
    public function baixarManual(User $user, Mensalidade $mensalidade): bool
    {
        return $user->can('financeiro.baixar_manual') && $user->empresa_id === $mensalidade->empresa_id;
    }

    public function cancelar(User $user, Mensalidade $mensalidade): bool
    {
        return $user->can('financeiro.cancelar_mensalidade') && $user->empresa_id === $mensalidade->empresa_id;
    }

    /**
     * Disparo manual de cobrança por WhatsApp (avulso ou em lote).
     */
    public function cobrar(User $user, Mensalidade $mensalidade): bool
    {
        return $user->can('financeiro.enviar_cobranca') && $user->empresa_id === $mensalidade->empresa_id;
    }

    /**
     * Alterar data de vencimento (detalhe ou lote) — mesma permissão de
     * editar/prorrogar contrato.
     */
    public function alterarVencimento(User $user, Mensalidade $mensalidade): bool
    {
        return $user->can('contratos.editar') && $user->empresa_id === $mensalidade->empresa_id;
    }
}
