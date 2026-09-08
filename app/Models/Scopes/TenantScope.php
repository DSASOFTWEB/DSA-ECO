<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Isola automaticamente todas as queries pela empresa (tenant) do usuário
 * autenticado. Aplicado via a trait App\Models\Concerns\BelongsToTenant.
 *
 * Importante para Jobs/Commands executados fora de um contexto HTTP
 * autenticado (scheduler, filas): nesse caso não há usuário logado, então
 * o escopo não filtra nada automaticamente — o Service/Job é responsável
 * por filtrar explicitamente por empresa_id quando necessário (evita
 * vazamento de dados entre tenants em rotinas em lote que processam
 * "todas as empresas").
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Usar hasUser() — e NÃO check()/user() — evita recursão infinita:
        // Auth::check()/user() disparam retrieveById, que reaplica este scope,
        // que chama Auth de novo, até esgotar a memória (erro 500 no login).
        if (Auth::hasUser() && Auth::user()->empresa_id) {
            $builder->where($model->getTable().'.empresa_id', Auth::user()->empresa_id);
        }
    }
}
