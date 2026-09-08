<?php

namespace App\Http\Requests\Quarto;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuartoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Quarto::class);
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'unidade_id' => ['required', Rule::exists('unidades', 'id')->where('empresa_id', $empresaId)],
            'numero' => [
                'required', 'string', 'max:30',
                Rule::unique('quartos', 'numero')
                    ->where('unidade_id', $this->input('unidade_id'))
                    ->ignore($this->route('quarto')),
            ],
            'capacidade_maxima' => ['required', 'integer', 'min:1', 'max:50'],
            'valor_diaria' => ['required', 'numeric', 'min:0'],
            'status' => ['required', 'in:ativo,manutencao,inativo'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
