<?php

namespace App\Http\Requests\Hospedagem;

use App\Http\Requests\Concerns\UsaFormaPagamentoCadastrada;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutHospedagemRequest extends FormRequest
{
    use UsaFormaPagamentoCadastrada;

    public function authorize(): bool
    {
        return $this->user()->can('checkout', $this->route('hospedagem'));
    }

    public function rules(): array
    {
        return [
            ...$this->regrasFormaPagamento(),
            'desconto' => ['nullable', 'numeric', 'min:0'],
            'caixa_id' => ['nullable', 'integer'],
            'emitir_nfce' => ['sometimes', 'boolean'],
            'emitir_nfse' => ['sometimes', 'boolean'],
        ];
    }
}
