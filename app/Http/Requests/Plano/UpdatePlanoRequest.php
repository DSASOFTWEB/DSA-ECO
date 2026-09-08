<?php

namespace App\Http\Requests\Plano;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlanoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('plano'));
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'descricao' => ['nullable', 'string'],
            'valor' => ['required', 'numeric', 'min:0'],
            'periodicidade' => ['required', 'in:mensal,trimestral,semestral,anual'],
            'max_dependentes' => ['required', 'integer', 'min:0'],
            'dias_acesso_semana' => ['required', 'integer', 'between:1,7'],
            'permite_congelamento' => ['boolean'],
            'ativo' => ['boolean'],
        ];
    }
}
