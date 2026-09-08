<?php

namespace App\Repositories\Eloquent;

use App\Models\Cliente;
use App\Repositories\Contracts\ClienteRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ClienteRepository extends BaseRepository implements ClienteRepositoryInterface
{
    public function __construct(Cliente $model)
    {
        parent::__construct($model);
    }

    public function buscarPorCpf(string $cpf): ?Cliente
    {
        return $this->model->where('cpf', $cpf)->first();
    }

    public function buscarComContratosAtivos(): Collection
    {
        return $this->model->whereHas('contratos', fn ($q) => $q->where('status', 'ativo'))->get();
    }

    protected function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        return $query
            // 'like' no MySQL já é case-insensitive com a collation utf8mb4_unicode_ci.
            ->when($filtros['nome'] ?? null, fn ($q, $v) => $q->where('nome', 'like', "%{$v}%"))
            ->when($filtros['cpf'] ?? null, fn ($q, $v) => $q->where('cpf', 'like', "%{$v}%"))
            ->when($filtros['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filtros['unidade_id'] ?? null, fn ($q, $v) => $q->where('unidade_id', $v));
    }
}
