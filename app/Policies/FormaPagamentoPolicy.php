<?php

namespace App\Policies;

use App\Models\FormaPagamento;
use App\Models\User;

class FormaPagamentoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('empresa.gerenciar');
    }

    public function create(User $user): bool
    {
        return $user->can('empresa.gerenciar');
    }

    public function update(User $user, FormaPagamento $forma): bool
    {
        return $user->can('empresa.gerenciar') && $user->empresa_id === $forma->empresa_id;
    }

    public function delete(User $user, FormaPagamento $forma): bool
    {
        return $user->can('empresa.gerenciar') && $user->empresa_id === $forma->empresa_id;
    }
}
