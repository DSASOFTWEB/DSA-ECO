<?php

namespace App\Policies;

use App\Models\Marca;
use App\Models\User;

class MarcaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('estoque.visualizar');
    }

    public function view(User $user, Marca $marca): bool
    {
        return $user->can('estoque.visualizar') && $user->empresa_id === $marca->empresa_id;
    }

    public function create(User $user): bool
    {
        return $user->can('estoque.criar');
    }

    public function update(User $user, Marca $marca): bool
    {
        return $user->can('estoque.editar') && $user->empresa_id === $marca->empresa_id;
    }

    public function delete(User $user, Marca $marca): bool
    {
        return $user->can('estoque.excluir') && $user->empresa_id === $marca->empresa_id;
    }
}
