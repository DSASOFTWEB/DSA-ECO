<?php

namespace App\Repositories\Eloquent;

use App\Models\Mensalidade;
use App\Repositories\Contracts\MensalidadeRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class MensalidadeRepository extends BaseRepository implements MensalidadeRepositoryInterface
{
    public function __construct(Mensalidade $model)
    {
        parent::__construct($model);
    }

    public function pendentesVencendoEm(\DateTimeInterface $data): Collection
    {
        return $this->model
            ->where('status', 'pendente')
            ->whereDate('data_vencimento', $data)
            ->with('contrato.cliente')
            ->get();
    }

    public function atrasadas(): Collection
    {
        return $this->model
            ->where('status', 'pendente')
            ->whereDate('data_vencimento', '<', now()->toDateString())
            ->with('contrato.cliente')
            ->get();
    }

    public function porContratoECompetencia(int $contratoId, \DateTimeInterface $competencia): ?Mensalidade
    {
        return $this->model
            ->where('contrato_id', $contratoId)
            ->whereDate('competencia', $competencia)
            ->first();
    }

    protected function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        return $query
            ->when($filtros['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filtros['competencia'] ?? null, fn ($q, $v) => $q->whereDate('competencia', $v))
            ->when($filtros['contrato_id'] ?? null, fn ($q, $v) => $q->where('contrato_id', $v));
    }
}
