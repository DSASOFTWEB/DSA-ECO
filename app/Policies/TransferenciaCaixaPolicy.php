<?php

namespace App\Policies;

use App\Models\TransferenciaCaixa;
use App\Models\User;

class TransferenciaCaixaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('caixa.visualizar');
    }

    public function view(User $user, TransferenciaCaixa $transferencia): bool
    {
        return $user->can('caixa.visualizar') && $user->empresa_id === $transferencia->empresa_id;
    }

    public function create(User $user): bool
    {
        return $user->can('caixa.transferir');
    }
}
