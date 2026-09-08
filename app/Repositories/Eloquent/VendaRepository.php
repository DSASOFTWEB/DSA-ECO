<?php

namespace App\Repositories\Eloquent;

use App\Models\Venda;
use App\Repositories\Contracts\VendaRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class VendaRepository extends BaseRepository implements VendaRepositoryInterface
{
    public function __construct(Venda $model)
    {
        parent::__construct($model);
    }

    public function porCaixa(int $caixaId): Collection
    {
        return $this->model->where('caixa_id', $caixaId)->with('itens.produto')->get();
    }

    protected function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        return $query
            ->when($filtros['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filtros['vendedor_id'] ?? null, fn ($q, $v) => $q->where('vendedor_id', $v))
            ->when($filtros['unidade_id'] ?? null, fn ($q, $v) => $q->where('unidade_id', $v))
            ->when($filtros['data_inicio'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filtros['data_fim'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
    }
}
