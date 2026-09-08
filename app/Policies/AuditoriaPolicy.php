<?php

namespace App\Policies;

use App\Models\User;

/**
 * Não há um Model "Auditoria" próprio (usa-se Spatie\Activitylog\Models\Activity),
 * então esta policy é usada apenas via Gate::authorize('viewAny', Auditoria::class)
 * com um alvo simbólico — ver AuthServiceProvider.
 */
class AuditoriaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'gerente']) && $user->can('auditoria.visualizar');
    }
}
