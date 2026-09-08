<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContratoResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource) {
            return [];
        }

        return [
            'id' => $this->id,
            'numero_contrato' => $this->numero_contrato,
            'plano' => $this->whenLoaded('plano', fn () => $this->plano->nome),
            'status' => $this->status,
            'valor_mensal' => (float) $this->valor_mensal,
            'dia_vencimento' => $this->dia_vencimento,
            'data_inicio' => $this->data_inicio?->toDateString(),
            'data_fim' => $this->data_fim?->toDateString(),
        ];
    }
}
