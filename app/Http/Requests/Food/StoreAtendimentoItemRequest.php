<?php

namespace App\Http\Requests\Food;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAtendimentoItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('operate', $this->route('atendimento'));
    }

    public function rules(): array
    {
        return [
            'produto_id' => ['required', Rule::exists('produtos', 'id')->where('empresa_id', $this->user()->empresa_id)->where('ativo', true)],
            'quantidade' => ['required', 'integer', 'min:1', 'max:999'],
            'desconto' => ['nullable', 'numeric', 'min:0'],
            'observacao' => ['nullable', 'string', 'max:500'],
        ];
    }
}
