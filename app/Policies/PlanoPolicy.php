<?php

namespace App\Policies;

use App\Models\Plano;
use App\Models\User;

class PlanoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('planos.visualizar');
    }

    public function view(User $user, Plano $plano): bool
    {
        return $user->can('planos.visualizar') && $user->empresa_id === $plano->empresa_id;
    }

    public function create(User $user): bool
    {
        return $user->can('planos.criar');
    }

    public function update(User $user, Plano $plano): bool
    {
        return $user->can('planos.editar') && $user->empresa_id === $plano->empresa_id;
    }

    public function delete(User $user, Plano $plano): bool
    {
        return $user->can('planos.excluir') && $user->empresa_id === $plano->empresa_id;
    }
}
