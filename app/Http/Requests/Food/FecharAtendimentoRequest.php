<?php

namespace App\Http\Requests\Food;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FecharAtendimentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('close', $this->route('atendimento'));
    }

    public function rules(): array
    {
        return [
            'caixa_id' => ['required', Rule::exists('caixas', 'id')->where('empresa_id', $this->user()->empresa_id)->where('status', 'aberto')],
            'desconto' => ['nullable', 'numeric', 'min:0'],
            'percentual_servico' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pagamentos' => ['required', 'array', 'min:1'],
            'pagamentos.*.forma' => ['required', Rule::in(['dinheiro', 'pix', 'credito', 'debito', 'fiado', 'outro'])],
            'pagamentos.*.valor' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
