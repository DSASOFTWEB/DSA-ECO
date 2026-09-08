<?php

namespace App\Models\Concerns;

use App\Models\Empresa;
use App\Models\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * Trait aplicada a todo model que pertence a uma empresa (tenant) do SaaS.
 * - Registra o TenantScope (filtra automaticamente pela empresa do usuário logado)
 * - Preenche empresa_id automaticamente na criação, se não informado
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if (! $model->empresa_id && Auth::check()) {
                $model->empresa_id = Auth::user()->empresa_id;
            }
        });
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
