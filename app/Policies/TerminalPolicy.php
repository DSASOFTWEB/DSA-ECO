<?php

namespace App\Policies;

use App\Models\Terminal;
use App\Models\User;

class TerminalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('terminais.visualizar');
    }

    public function view(User $user, Terminal $terminal): bool
    {
        return $user->can('terminais.visualizar') && $user->empresa_id === $terminal->empresa_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') && $user->can('terminais.criar');
    }

    public function update(User $user, Terminal $terminal): bool
    {
        return $user->can('terminais.editar') && $user->empresa_id === $terminal->empresa_id;
    }
}
