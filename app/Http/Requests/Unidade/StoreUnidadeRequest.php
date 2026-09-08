<?php

namespace App\Http\Requests\Unidade;

use Illuminate\Foundation\Http\FormRequest;

class StoreUnidadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Unidade::class);
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:18'],
            'cep' => ['nullable', 'string', 'max:9'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', 'string', 'size:2'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'capacidade_maxima' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
