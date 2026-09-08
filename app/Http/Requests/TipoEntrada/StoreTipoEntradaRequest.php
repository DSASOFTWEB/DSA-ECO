<?php

namespace App\Http\Requests\TipoEntrada;

use Illuminate\Foundation\Http\FormRequest;

class StoreTipoEntradaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\TipoEntrada::class);
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'valor' => ['required', 'numeric', 'min:0'],
            'eh_plano' => ['boolean'],
            'ativo' => ['boolean'],
        ];
    }
}
