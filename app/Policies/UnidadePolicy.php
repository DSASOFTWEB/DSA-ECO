<?php

namespace App\Policies;

use App\Models\Unidade;
use App\Models\User;

class UnidadePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('unidades.visualizar');
    }

    public function view(User $user, Unidade $unidade): bool
    {
        return $user->can('unidades.visualizar') && $user->empresa_id === $unidade->empresa_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') && $user->can('unidades.criar');
    }

    public function update(User $user, Unidade $unidade): bool
    {
        return $user->can('unidades.editar') && $user->empresa_id === $unidade->empresa_id;
    }

    public function delete(User $user, Unidade $unidade): bool
    {
        return $user->hasRole('admin') && $user->empresa_id === $unidade->empresa_id;
    }
}
