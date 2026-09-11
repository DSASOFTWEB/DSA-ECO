<?php

namespace App\Http\Requests\Food;

use App\Models\PontoAtendimento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePontoAtendimentoRequest extends FormRequest
{
    public const MAX_FAIXA = 200;

    public function authorize(): bool
    {
        return $this->user()->can('create', PontoAtendimento::class);
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'unidade_id' => ['required', Rule::exists('unidades', 'id')->where('empresa_id', $empresaId)],
            'tipo' => ['required', Rule::in(['mesa', 'comanda'])],
            'numero_inicial' => ['required', 'integer', 'min:1', 'max:9999'],
            'numero_final' => ['required', 'integer', 'min:1', 'max:9999', 'gte:numero_inicial'],
            'capacidade' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $inicial = (int) $this->input('numero_inicial');
            $final = (int) $this->input('numero_final');
            $qtd = $final - $inicial + 1;

            if ($qtd > self::MAX_FAIXA) {
                $validator->errors()->add(
                    'numero_final',
                    'A faixa não pode ter mais de '.self::MAX_FAIXA.' números por vez.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'numero_final.gte' => 'O número final deve ser maior ou igual ao inicial.',
        ];
    }
}
