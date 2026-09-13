<?php

namespace App\Http\Requests\Caixa;

use App\Support\Financeiro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MovimentarCaixaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('registrarMovimentacao', $this->route('caixa'));
    }

    public function rules(): array
    {
        $categorias = array_keys($this->input('tipo') === 'saida' ? Financeiro::CATEGORIAS_SAIDA : Financeiro::CATEGORIAS_ENTRADA);

        return [
            'tipo' => ['required', 'in:entrada,saida'],
            'categoria' => ['required', Rule::in($categorias)],
            'descricao' => ['nullable', 'string', 'max:255'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'forma_pagamento' => ['nullable', Rule::in(array_keys(Financeiro::FORMAS_PAGAMENTO))],
        ];
    }
}
