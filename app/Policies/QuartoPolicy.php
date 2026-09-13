<?php

namespace App\Policies;

use App\Models\Quarto;
use App\Models\User;

class QuartoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pousada.visualizar');
    }

    public function view(User $user, Quarto $quarto): bool
    {
        return $user->can('pousada.visualizar') && $user->empresa_id === $quarto->empresa_id;
    }

    public function create(User $user): bool
    {
        return $user->can('pousada.gerenciar');
    }

    public function update(User $user, Quarto $quarto): bool
    {
        return $user->can('pousada.gerenciar') && $user->empresa_id === $quarto->empresa_id;
    }

    public function limpar(User $user, Quarto $quarto): bool
    {
        return $user->can('pousada.limpeza') && $user->empresa_id === $quarto->empresa_id;
    }

    public function comodato(User $user, Quarto $quarto): bool
    {
        return $user->can('pousada.comodato') && $user->empresa_id === $quarto->empresa_id;
    }
}
