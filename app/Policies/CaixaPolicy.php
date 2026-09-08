<?php

namespace App\Policies;

use App\Models\Caixa;
use App\Models\User;

class CaixaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('caixa.visualizar');
    }

    public function view(User $user, Caixa $caixa): bool
    {
        return $user->can('caixa.visualizar') && $user->empresa_id === $caixa->empresa_id;
    }

    public function abrir(User $user): bool
    {
        return $user->can('caixa.abrir');
    }

    public function fechar(User $user, Caixa $caixa): bool
    {
        return $user->can('caixa.fechar')
            && $user->empresa_id === $caixa->empresa_id
            && ($user->id === $caixa->usuario_abertura_id || $user->hasRole(['admin', 'gerente']));
    }

    public function registrarMovimentacao(User $user, Caixa $caixa): bool
    {
        return $user->can('caixa.movimentar') && $user->empresa_id === $caixa->empresa_id && $caixa->estaAberto();
    }
}
