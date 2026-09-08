<?php

namespace App\Policies;

use App\Models\TipoEntrada;
use App\Models\User;

class TipoEntradaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tipos_entrada.visualizar');
    }

    public function view(User $user, TipoEntrada $tipoEntrada): bool
    {
        return $user->can('tipos_entrada.visualizar') && $user->empresa_id === $tipoEntrada->empresa_id;
    }

    public function create(User $user): bool
    {
        return $user->can('tipos_entrada.gerenciar');
    }

    public function update(User $user, TipoEntrada $tipoEntrada): bool
    {
        return $user->can('tipos_entrada.gerenciar') && $user->empresa_id === $tipoEntrada->empresa_id;
    }

    public function delete(User $user, TipoEntrada $tipoEntrada): bool
    {
        return $user->can('tipos_entrada.gerenciar') && $user->empresa_id === $tipoEntrada->empresa_id;
    }
}
