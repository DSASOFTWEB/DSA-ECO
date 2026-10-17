<?php

namespace App\Http\Requests\FormaPagamento;

use App\Models\Empresa;
use App\Models\FormaPagamento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFormaPagamentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $forma = $this->route('formaPagamento');

        return $forma ? $this->user()->can('update', $forma) : $this->user()->can('create', FormaPagamento::class);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'integrado' => $this->boolean('integrado') && $this->input('tipo') === 'pix',
            'ativo' => $this->boolean('ativo'),
            'ordem' => $this->input('ordem') ?: 0,
        ]);
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:60'],
            'tipo' => ['required', Rule::in(array_keys(FormaPagamento::TIPOS))],
            'integrado' => ['boolean'],
            'ativo' => ['boolean'],
            'ordem' => ['integer', 'min:0', 'max:999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->boolean('integrado') && ! Empresa::find($this->user()->empresa_id)?->gatewayPixAtivo()) {
                $validator->errors()->add('integrado', 'Configure o gateway Pix (Itaú ou Mercado Pago) em Minha Empresa antes de usar Pix integrado.');
            }
        });
    }
}
