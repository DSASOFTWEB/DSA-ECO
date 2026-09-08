<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;

class ClientePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('clientes.visualizar');
    }

    public function view(User $user, Cliente $cliente): bool
    {
        return $user->can('clientes.visualizar') && $user->empresa_id === $cliente->empresa_id;
    }

    public function create(User $user): bool
    {
        return $user->can('clientes.criar');
    }

    public function update(User $user, Cliente $cliente): bool
    {
        return $user->can('clientes.editar') && $user->empresa_id === $cliente->empresa_id;
    }

    public function delete(User $user, Cliente $cliente): bool
    {
        return $user->can('clientes.excluir') && $user->empresa_id === $cliente->empresa_id;
    }

    public function restore(User $user, Cliente $cliente): bool
    {
        return $user->can('clientes.excluir') && $user->empresa_id === $cliente->empresa_id;
    }

    public function forceDelete(User $user, Cliente $cliente): bool
    {
        return $user->hasRole('admin') && $user->empresa_id === $cliente->empresa_id;
    }
}
