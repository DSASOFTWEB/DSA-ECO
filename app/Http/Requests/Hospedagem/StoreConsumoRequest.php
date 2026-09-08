<?php

namespace App\Http\Requests\Hospedagem;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreConsumoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('consumos', $this->route('hospedagem'));
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'produto_id' => ['nullable', Rule::exists('produtos', 'id')->where('empresa_id', $empresaId)],
            'descricao' => ['nullable', 'string', 'max:255'],
            'quantidade' => ['required', 'integer', 'min:1', 'max:999'],
            'valor_unitario' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (empty($this->input('produto_id')) && (empty($this->input('descricao')) || $this->input('valor_unitario') === null)) {
                $validator->errors()->add('produto_id', 'Escolha um produto do estoque OU preencha descrição + valor do item avulso.');
            }
        });
    }
}
