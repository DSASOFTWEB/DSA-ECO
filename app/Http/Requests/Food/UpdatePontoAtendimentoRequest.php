<?php

namespace App\Http\Requests\Food;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePontoAtendimentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('ponto'));
    }

    public function rules(): array
    {
        $ponto = $this->route('ponto');

        return [
            'numero' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('pontos_atendimento')->ignore($ponto)->where(fn ($query) => $query
                    ->where('empresa_id', $ponto->empresa_id)
                    ->where('unidade_id', $ponto->unidade_id)
                    ->where('tipo', $ponto->tipo)),
            ],
            'nome' => ['nullable', 'string', 'max:100'],
            'capacidade' => ['nullable', 'integer', 'min:1', 'max:999'],
            'ordem' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'status' => ['required', Rule::in(['livre', 'reservada', 'bloqueada'])],
        ];
    }
}
