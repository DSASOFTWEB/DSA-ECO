<?php

namespace App\Http\Requests\Food;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferirAtendimentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('operate', $this->route('atendimento'));
    }

    public function rules(): array
    {
        return [
            'ponto_destino_id' => ['required', Rule::exists('pontos_atendimento', 'id')->where('empresa_id', $this->user()->empresa_id)],
        ];
    }
}
