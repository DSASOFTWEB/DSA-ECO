<?php

namespace App\Policies;

use App\Models\CaixaMovimentacao;
use App\Models\User;

class CaixaMovimentacaoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('caixa.visualizar');
    }

    public function view(User $user, CaixaMovimentacao $movimentacao): bool
    {
        return $user->can('caixa.visualizar') && $user->empresa_id === $movimentacao->caixa->empresa_id;
    }

    public function update(User $user, CaixaMovimentacao $movimentacao): bool
    {
        return $user->can('caixa.editar_movimentacao') && $user->empresa_id === $movimentacao->caixa->empresa_id;
    }

    public function estornar(User $user, CaixaMovimentacao $movimentacao): bool
    {
        return $user->can('caixa.estornar') && $user->empresa_id === $movimentacao->caixa->empresa_id;
    }
}
