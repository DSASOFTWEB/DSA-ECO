<?php

namespace App\Http\Requests\Contrato;

use App\Models\Contrato;
use Illuminate\Foundation\Http\FormRequest;

class ProrrogarContratoRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Contrato $contrato */
        $contrato = $this->route('contrato');

        return $this->user()->can('update', $contrato);
    }

    public function rules(): array
    {
        return [
            'nova_data_vencimento' => ['required', 'date', 'after:today'],
        ];
    }
}
