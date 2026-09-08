<?php

namespace App\Http\Requests\Caixa;

use Illuminate\Foundation\Http\FormRequest;

class FecharCaixaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fechar', $this->route('caixa'));
    }

    public function rules(): array
    {
        return [
            'valor_fechamento_informado' => ['required', 'numeric', 'min:0'],
        ];
    }
}
