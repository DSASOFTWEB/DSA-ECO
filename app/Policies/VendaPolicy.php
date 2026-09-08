<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Venda;

class VendaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('vendas.visualizar');
    }

    public function view(User $user, Venda $venda): bool
    {
        return $user->can('vendas.visualizar') && $user->empresa_id === $venda->empresa_id;
    }

    public function create(User $user): bool
    {
        return $user->can('vendas.criar');
    }

    public function cancelar(User $user, Venda $venda): bool
    {
        return $user->can('vendas.cancelar') && $user->empresa_id === $venda->empresa_id;
    }
}
