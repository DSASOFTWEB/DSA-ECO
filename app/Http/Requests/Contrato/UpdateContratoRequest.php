<?php

namespace App\Http\Requests\Contrato;

use App\Models\Contrato;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContratoRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Contrato $contrato */
        $contrato = $this->route('contrato');

        return $this->user()->can('update', $contrato);
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'plano_id' => ['required', Rule::exists('planos', 'id')->where('empresa_id', $empresaId)],
            'dia_vencimento' => ['required', 'integer', 'between:1,28'],
            'valor_mensal' => ['nullable', 'numeric', 'min:0'],
            'desconto_percentual' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }
}
