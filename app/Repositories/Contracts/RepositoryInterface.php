<?php

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Contrato genérico de repositório: encapsula o acesso a dados (Eloquent)
 * para que Services nunca precisem montar queries diretamente. Isso separa
 * "como buscar" (Repository) de "o que fazer com o resultado" (Service).
 */
interface RepositoryInterface
{
    public function find(int|string $id): ?Model;

    public function findOrFail(int|string $id): Model;

    public function all(array $with = []): Collection;

    public function paginate(int $perPage = 15, array $with = [], array $filtros = []): LengthAwarePaginator;

    public function create(array $dados): Model;

    public function update(Model $model, array $dados): Model;

    public function delete(Model $model): bool;

    public function query(): Builder;
}
