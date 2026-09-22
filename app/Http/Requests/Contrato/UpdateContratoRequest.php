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
            'dia_vencimento' => ['required', 'integer', 'between:1,31'],
            'valor_mensal' => ['nullable', 'numeric', 'min:0'],
            'valor_caucao' => ['required', 'numeric', 'min:0'],
            'primeiro_vencimento' => ['required', 'date', 'after:data_inicio'],
            'desconto_percentual' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        /** @var Contrato $contrato */
        $contrato = $this->route('contrato');

        $this->merge(['data_inicio' => $contrato->data_inicio->toDateString()]);
    }
}
