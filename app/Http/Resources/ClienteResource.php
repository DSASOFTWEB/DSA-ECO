<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClienteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'cpf' => $this->cpf,
            'email' => $this->email,
            'telefone' => $this->telefone,
            'whatsapp' => $this->whatsapp,
            'status' => $this->status,
            'foto_url' => $this->foto_path ? asset('storage/'.$this->foto_path) : null,
            'contrato_ativo' => ContratoResource::make($this->whenLoaded('contratoAtivo')),
            'dependentes' => $this->whenLoaded('dependentes', fn () => $this->dependentes->map(fn ($d) => [
                'id' => $d->id,
                'nome' => $d->nome,
                'parentesco' => $d->parentesco,
            ])),
            'carteirinha' => $this->whenLoaded('carteirinha', fn () => [
                'codigo' => $this->carteirinha->codigo,
                'status' => $this->carteirinha->status,
            ]),
        ];
    }
}
