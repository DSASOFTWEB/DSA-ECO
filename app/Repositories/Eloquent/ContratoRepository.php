<?php

namespace App\Repositories\Eloquent;

use App\Models\Contrato;
use App\Repositories\Contracts\ContratoRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ContratoRepository extends BaseRepository implements ContratoRepositoryInterface
{
    public function __construct(Contrato $model)
    {
        parent::__construct($model);
    }

    public function ativosComVencimentoNoDia(int $dia, \DateTimeInterface $referencia): Collection
    {
        return $this->model
            ->where('status', 'ativo')
            ->where('dia_vencimento', $dia)
            ->where(function (Builder $query) use ($referencia) {
                $query->whereNull('primeiro_vencimento')
                    ->orWhereDate('primeiro_vencimento', '<=', $referencia);
            })
            ->get();
    }

    public function ativosPorCliente(int $clienteId): Collection
    {
        return $this->model->where('cliente_id', $clienteId)->where('status', 'ativo')->get();
    }

    protected function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        return $query
            ->when($filtros['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filtros['unidade_id'] ?? null, fn ($q, $v) => $q->where('unidade_id', $v))
            ->when($filtros['cliente_id'] ?? null, fn ($q, $v) => $q->where('cliente_id', $v));
    }
}
