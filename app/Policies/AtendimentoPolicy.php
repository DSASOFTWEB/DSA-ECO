<?php

namespace App\Policies;

use App\Models\Atendimento;
use App\Models\User;

class AtendimentoPolicy
{
    public function view(User $user, Atendimento $atendimento): bool
    {
        return $user->can('food.visualizar') && $user->empresa_id === $atendimento->empresa_id;
    }

    public function operate(User $user, Atendimento $atendimento): bool
    {
        return $user->can('food.operar') && $user->empresa_id === $atendimento->empresa_id;
    }

    public function close(User $user, Atendimento $atendimento): bool
    {
        return $user->can('food.fechar') && $user->empresa_id === $atendimento->empresa_id;
    }
}
