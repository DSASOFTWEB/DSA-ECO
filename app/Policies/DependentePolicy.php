<?php

namespace App\Policies;

use App\Models\Dependente;
use App\Models\User;

class DependentePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clientes.visualizar');
    }

    public function view(User $user, Dependente $dependente): bool
    {
        return $user->can('clientes.visualizar') && $user->empresa_id === $dependente->cliente->empresa_id;
    }

    public function create(User $user): bool
    {
        return $user->can('clientes.editar');
    }

    public function update(User $user, Dependente $dependente): bool
    {
        return $user->can('clientes.editar') && $user->empresa_id === $dependente->cliente->empresa_id;
    }

    public function delete(User $user, Dependente $dependente): bool
    {
        return $user->can('clientes.editar') && $user->empresa_id === $dependente->cliente->empresa_id;
    }
}
