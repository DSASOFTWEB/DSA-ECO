<?php

namespace App\Http\Requests\Contrato;

use Illuminate\Foundation\Http\FormRequest;

class CancelarContratoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cancelar', $this->route('contrato'));
    }

    public function rules(): array
    {
        return [
            'motivo_cancelamento' => ['required', 'string', 'max:1000'],
        ];
    }
}
