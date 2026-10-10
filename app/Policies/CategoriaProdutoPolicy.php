<?php

namespace App\Policies;

use App\Models\CategoriaProduto;
use App\Models\User;

class CategoriaProdutoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('estoque.visualizar');
    }

    public function view(User $user, CategoriaProduto $categoriaProduto): bool
    {
        return $user->can('estoque.visualizar') && $user->empresa_id === $categoriaProduto->empresa_id;
    }

    public function create(User $user): bool
    {
        return $user->can('estoque.criar');
    }

    public function update(User $user, CategoriaProduto $categoriaProduto): bool
    {
        return $user->can('estoque.editar') && $user->empresa_id === $categoriaProduto->empresa_id;
    }

    public function delete(User $user, CategoriaProduto $categoriaProduto): bool
    {
        return $user->can('estoque.excluir') && $user->empresa_id === $categoriaProduto->empresa_id;
    }
}
