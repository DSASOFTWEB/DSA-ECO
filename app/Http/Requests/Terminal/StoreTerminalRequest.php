<?php

namespace App\Http\Requests\Terminal;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTerminalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('terminal')
            ? $this->user()->can('terminais.editar')
            : $this->user()->can('terminais.criar');
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:100'],
            'unidade_id' => ['required', Rule::exists('unidades', 'id')->where('empresa_id', $this->user()->empresa_id)],
            'status' => ['required', 'in:ativo,inativo'],
        ];
    }
}
