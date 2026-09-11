<?php

namespace App\Policies;

use App\Models\PontoAtendimento;
use App\Models\User;

class PontoAtendimentoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('food.visualizar');
    }

    public function view(User $user, PontoAtendimento $ponto): bool
    {
        return $user->can('food.visualizar') && $user->empresa_id === $ponto->empresa_id;
    }

    public function create(User $user): bool
    {
        return $user->can('food.configurar');
    }

    public function update(User $user, PontoAtendimento $ponto): bool
    {
        return $user->can('food.configurar') && $user->empresa_id === $ponto->empresa_id;
    }

    public function operate(User $user, PontoAtendimento $ponto): bool
    {
        return $user->can('food.operar') && $user->empresa_id === $ponto->empresa_id;
    }
}
