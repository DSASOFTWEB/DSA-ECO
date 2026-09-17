<?php

namespace App\Http\Requests\Hospedagem;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutHospedagemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('checkout', $this->route('hospedagem'));
    }

    public function rules(): array
    {
        return [
            'forma_pagamento' => ['required', 'string', 'in:dinheiro,cartao_credito,cartao_debito,pix'],
            'desconto' => ['nullable', 'numeric', 'min:0'],
            'caixa_id' => ['nullable', 'integer'],
            'emitir_nfce' => ['sometimes', 'boolean'],
            'emitir_nfse' => ['sometimes', 'boolean'],
        ];
    }
}
