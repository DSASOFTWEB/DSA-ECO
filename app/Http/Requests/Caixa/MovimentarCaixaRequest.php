<?php

namespace App\Http\Requests\Caixa;

use Illuminate\Foundation\Http\FormRequest;

class MovimentarCaixaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('registrarMovimentacao', $this->route('caixa'));
    }

    public function rules(): array
    {
        return [
            'tipo' => ['required', 'in:entrada,saida'],
            'categoria' => ['required', 'string', 'max:40'],
            'descricao' => ['nullable', 'string', 'max:255'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'forma_pagamento' => ['nullable', 'string', 'max:30'],
        ];
    }
}
