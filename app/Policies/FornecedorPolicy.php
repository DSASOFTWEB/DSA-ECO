<?php

namespace App\Policies;

use App\Models\Fornecedor;
use App\Models\User;

class FornecedorPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('estoque.visualizar');
    }

    public function view(User $user, Fornecedor $fornecedor): bool
    {
        return $user->can('estoque.visualizar') && $user->empresa_id === $fornecedor->empresa_id;
    }

    public function create(User $user): bool
    {
        return $user->can('estoque.criar');
    }

    public function update(User $user, Fornecedor $fornecedor): bool
    {
        return $user->can('estoque.editar') && $user->empresa_id === $fornecedor->empresa_id;
    }

    public function delete(User $user, Fornecedor $fornecedor): bool
    {
        return $user->can('estoque.excluir') && $user->empresa_id === $fornecedor->empresa_id;
    }
}
