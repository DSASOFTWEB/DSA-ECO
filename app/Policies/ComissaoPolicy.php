<?php

namespace App\Policies;

use App\Models\Comissao;
use App\Models\User;

class ComissaoPolicy
{
    public function viewAny(User $user): bool
    {
        // Vendedor vê as próprias comissões; gerente/financeiro vê todas.
        return $user->can('comissoes.visualizar') || $user->can('comissoes.visualizar_proprias');
    }

    public function view(User $user, Comissao $comissao): bool
    {
        if ($user->empresa_id !== $comissao->empresa_id) {
            return false;
        }

        return $user->can('comissoes.visualizar')
            || ($user->can('comissoes.visualizar_proprias') && $user->id === $comissao->vendedor_id);
    }

    public function pagar(User $user, Comissao $comissao): bool
    {
        return $user->can('comissoes.pagar') && $user->empresa_id === $comissao->empresa_id;
    }
}
