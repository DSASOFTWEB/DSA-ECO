<?php

namespace App\Http\Requests\Venda;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVendaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Venda::class);
    }

    public function rules(): array
    {
        return [
            'cliente_id' => ['nullable', Rule::exists('clientes', 'id')->where('empresa_id', $this->user()->empresa_id)],
            'forma_pagamento' => ['required', 'in:dinheiro,cartao_credito,cartao_debito,pix'],
            'observacao' => ['nullable', 'string', 'max:1000'],
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.produto_id' => ['nullable', 'exists:produtos,id'],
            'itens.*.tipo_entrada_id' => ['nullable', 'exists:tipos_entrada,id'],
            'itens.*.quantidade' => ['required', 'integer', 'min:1'],
            'itens.*.desconto' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('itens', []) as $indice => $item) {
                $temProduto = ! empty($item['produto_id']);
                $temTipoEntrada = ! empty($item['tipo_entrada_id']);

                if ($temProduto === $temTipoEntrada) {
                    $validator->errors()->add("itens.{$indice}.produto_id", 'Informe um produto OU um tipo de entrada para cada item, nunca os dois nem nenhum.');
                }
            }
        });
    }
}
