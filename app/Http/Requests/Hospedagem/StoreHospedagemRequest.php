<?php

namespace App\Http\Requests\Hospedagem;

use App\Models\Hospedagem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHospedagemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Hospedagem::class);
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'quarto_id' => ['required', Rule::exists('quartos', 'id')->where('empresa_id', $empresaId)],
            'cliente_id' => ['required', Rule::exists('clientes', 'id')->where('empresa_id', $empresaId)],
            'quantidade_hospedes' => ['nullable', 'integer', 'min:1', 'max:50'],
            'data_checkin_prevista' => ['required', 'date'],
            'data_checkout_prevista' => ['required', 'date', 'after:data_checkin_prevista'],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
