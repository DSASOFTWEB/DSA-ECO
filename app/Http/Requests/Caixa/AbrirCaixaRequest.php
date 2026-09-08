<?php

namespace App\Http\Requests\Caixa;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AbrirCaixaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('abrir', \App\Models\Caixa::class);
    }

    public function rules(): array
    {
        // Operador com unidade fixa só pode abrir terminal da própria
        // unidade (senão daria pra abrir caixa em unidade alheia da mesma
        // empresa); operador "todas as unidades" pode abrir qualquer um.
        $terminalRule = Rule::exists('terminais', 'id')->where('empresa_id', $this->user()->empresa_id);

        if ($this->user()->unidade_id) {
            $terminalRule->where('unidade_id', $this->user()->unidade_id);
        }

        return [
            'terminal_id' => ['required', $terminalRule],
            'valor_abertura' => ['required', 'numeric', 'min:0'],
        ];
    }
}
