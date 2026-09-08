<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MensalidadeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'competencia' => $this->competencia->format('m/Y'),
            'valor_total' => (float) $this->valor_total,
            'data_vencimento' => $this->data_vencimento->toDateString(),
            'data_pagamento' => $this->data_pagamento?->toDateString(),
            'status' => $this->status,
            'dias_em_atraso' => $this->diasEmAtraso(),
        ];
    }
}
