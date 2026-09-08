<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarteirinhaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'codigo' => $this->codigo,
            'status' => $this->status,
            'emitida_em' => $this->emitida_em?->toDateTimeString(),
            'expira_em' => $this->expira_em?->toDateString(),
        ];
    }
}
