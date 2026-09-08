<?php

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\RepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository implements RepositoryInterface
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function find(int|string $id): ?Model
    {
        return $this->model->find($id);
    }

    public function findOrFail(int|string $id): Model
    {
        return $this->model->findOrFail($id);
    }

    public function all(array $with = []): Collection
    {
        return $this->model->with($with)->get();
    }

    public function paginate(int $perPage = 15, array $with = [], array $filtros = []): LengthAwarePaginator
    {
        return $this->aplicarFiltros($this->query()->with($with), $filtros)
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $dados): Model
    {
        return $this->model->create($dados);
    }

    public function update(Model $model, array $dados): Model
    {
        $model->update($dados);

        return $model->refresh();
    }

    public function delete(Model $model): bool
    {
        return (bool) $model->delete();
    }

    public function query(): Builder
    {
        return $this->model->newQuery();
    }

    /**
     * Hook para as subclasses aplicarem filtros de busca (?nome=, ?status=...)
     * de forma consistente. Sobrescreva em cada repositório concreto.
     */
    protected function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        return $query;
    }
}
