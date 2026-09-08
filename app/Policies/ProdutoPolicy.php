<?php

namespace App\Policies;

use App\Models\Produto;
use App\Models\User;

class ProdutoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('estoque.visualizar');
    }

    public function view(User $user, Produto $produto): bool
    {
        return $user->can('estoque.visualizar') && $user->empresa_id === $produto->empresa_id;
    }

    public function create(User $user): bool
    {
        return $user->can('estoque.criar');
    }

    public function update(User $user, Produto $produto): bool
    {
        return $user->can('estoque.editar') && $user->empresa_id === $produto->empresa_id;
    }

    public function delete(User $user, Produto $produto): bool
    {
        return $user->can('estoque.excluir') && $user->empresa_id === $produto->empresa_id;
    }

    public function ajustarEstoque(User $user, Produto $produto): bool
    {
        return $user->can('estoque.ajustar') && $user->empresa_id === $produto->empresa_id;
    }
}
