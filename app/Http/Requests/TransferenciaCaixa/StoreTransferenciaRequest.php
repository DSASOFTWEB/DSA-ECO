<?php

namespace App\Http\Requests\TransferenciaCaixa;

use App\Models\TransferenciaCaixa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', TransferenciaCaixa::class);
    }

    public function rules(): array
    {
        $caixaAbertoDaEmpresa = Rule::exists('caixas', 'id')
            ->where('empresa_id', $this->user()->empresa_id)
            ->where('status', 'aberto');

        return [
            'caixa_origem_id' => ['required', 'integer', 'different:caixa_destino_id', $caixaAbertoDaEmpresa],
            'caixa_destino_id' => ['required', 'integer', $caixaAbertoDaEmpresa],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'observacao' => ['nullable', 'string', 'max:255'],
        ];
    }
}
