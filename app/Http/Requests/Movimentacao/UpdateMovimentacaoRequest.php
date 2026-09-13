<?php

namespace App\Http\Requests\Movimentacao;

use App\Support\Financeiro;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMovimentacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('movimentacao'));
    }

    /**
     * Só descrição/categoria/forma de pagamento são editáveis — valor, tipo
     * e caixa nunca (ver CaixaMovimentacao::podeEditar()/estornar() pra
     * corrigir valor via lançamento reverso, preservando o histórico).
     */
    public function rules(): array
    {
        $categorias = array_keys($this->route('movimentacao')->tipo === 'entrada' ? Financeiro::CATEGORIAS_ENTRADA : Financeiro::CATEGORIAS_SAIDA);

        return [
            'descricao' => ['nullable', 'string', 'max:255'],
            'categoria' => ['required', Rule::in($categorias)],
            'forma_pagamento' => ['nullable', Rule::in(array_keys(Financeiro::FORMAS_PAGAMENTO))],
        ];
    }
}
