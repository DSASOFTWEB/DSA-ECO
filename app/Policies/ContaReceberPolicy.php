<?php

namespace App\Policies;

use App\Models\ContaReceber;
use App\Models\User;

class ContaReceberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('contas_receber.visualizar');
    }

    public function view(User $user, ContaReceber $contaReceber): bool
    {
        return $user->can('contas_receber.visualizar') && $user->empresa_id === $contaReceber->empresa_id;
    }

    public function create(User $user): bool
    {
        return $user->can('contas_receber.gerenciar');
    }

    public function update(User $user, ContaReceber $contaReceber): bool
    {
        return $user->can('contas_receber.gerenciar') && $user->empresa_id === $contaReceber->empresa_id;
    }

    public function delete(User $user, ContaReceber $contaReceber): bool
    {
        return $user->can('contas_receber.gerenciar') && $user->empresa_id === $contaReceber->empresa_id;
    }
}
