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
}
