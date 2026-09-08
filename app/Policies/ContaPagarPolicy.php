<?php

namespace App\Policies;

use App\Models\ContaPagar;
use App\Models\User;

class ContaPagarPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('contas_pagar.visualizar');
    }

    public function view(User $user, ContaPagar $contaPagar): bool
    {
        return $user->can('contas_pagar.visualizar') && $user->empresa_id === $contaPagar->empresa_id;
    }

    public function create(User $user): bool
    {
        return $user->can('contas_pagar.gerenciar');
    }

    public function update(User $user, ContaPagar $contaPagar): bool
    {
        return $user->can('contas_pagar.gerenciar') && $user->empresa_id === $contaPagar->empresa_id;
    }

    public function delete(User $user, ContaPagar $contaPagar): bool
    {
        return $user->can('contas_pagar.gerenciar') && $user->empresa_id === $contaPagar->empresa_id;
    }
}
