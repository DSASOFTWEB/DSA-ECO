<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Facades\Activity as ActivityFacade;

/**
 * A auditoria de criação/edição/exclusão dos models já é automática via
 * Spatie\Activitylog\Traits\LogsActivity (ver getActivitylogOptions() em
 * cada Model). Este service cobre o que a trait não cobre: eventos de
 * negócio que não são um create/update/delete de um único model
 * (login, tentativa de acesso negada, ação em lote, etc.).
 */
class AuditoriaService
{
    public function registrar(string $descricao, ?Model $sujeito = null, array $propriedades = [], string $log = 'default'): void
    {
        $activity = ActivityFacade::inLog($log)->withProperties($propriedades);

        if ($sujeito) {
            $activity->performedOn($sujeito);
        }

        if (auth()->check()) {
            $activity->causedBy(auth()->user());
        }

        $activity->log($descricao);
    }
}
