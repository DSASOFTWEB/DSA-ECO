<?php

namespace App\Http\Requests\Food;

use App\Models\PontoAtendimento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePontoAtendimentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PontoAtendimento::class);
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'unidade_id' => ['required', Rule::exists('unidades', 'id')->where('empresa_id', $empresaId)],
            'tipo' => ['required', Rule::in(['mesa', 'comanda'])],
            'numero' => [
                'required', 'integer', 'min:1',
                Rule::unique('pontos_atendimento')->where(fn ($query) => $query
                    ->where('empresa_id', $empresaId)
                    ->where('unidade_id', $this->integer('unidade_id'))
                    ->where('tipo', $this->input('tipo'))),
            ],
            'nome' => ['nullable', 'string', 'max:100'],
            'capacidade' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }
}
