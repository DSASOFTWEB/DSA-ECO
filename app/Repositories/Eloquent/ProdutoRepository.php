<?php

namespace App\Repositories\Eloquent;

use App\Models\Produto;
use App\Repositories\Contracts\ProdutoRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ProdutoRepository extends BaseRepository implements ProdutoRepositoryInterface
{
    public function __construct(Produto $model)
    {
        parent::__construct($model);
    }

    public function abaixoDoEstoqueMinimo(): Collection
    {
        return $this->model
            ->where('controla_estoque', true)
            ->whereColumn('estoque_atual', '<=', 'estoque_minimo')
            ->get();
    }

    protected function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        return $query
            // 'like' no MySQL já é case-insensitive com a collation utf8mb4_unicode_ci.
            ->when($filtros['nome'] ?? null, fn ($q, $v) => $q->where('nome', 'like', "%{$v}%"))
            ->when($filtros['categoria_id'] ?? null, fn ($q, $v) => $q->where('categoria_id', $v))
            ->when($filtros['ativo'] ?? null, fn ($q, $v) => $q->where('ativo', $v));
    }
}
