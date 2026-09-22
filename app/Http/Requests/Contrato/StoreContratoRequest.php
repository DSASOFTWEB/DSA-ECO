<?php

namespace App\Http\Requests\Contrato;

use App\Models\Contrato;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContratoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Contrato::class);
    }

    public function rules(): array
    {
        // "exists" simples consulta a tabela direto, sem o escopo de tenant
        // do Eloquent — por isso toda referência aqui é filtrada também por
        // empresa_id, para nunca aceitar um id de outra empresa do SaaS.
        // (App\Services\ContratoService::contratar ainda revalida isso de
        // novo antes de gravar, como segunda camada de proteção.)
        $empresaId = $this->user()->empresa_id;

        return [
            'unidade_id' => ['required', Rule::exists('unidades', 'id')->where('empresa_id', $empresaId)],
            'cliente_id' => ['required', Rule::exists('clientes', 'id')->where('empresa_id', $empresaId)],
            'plano_id' => ['required', Rule::exists('planos', 'id')->where('empresa_id', $empresaId)],
            'vendedor_id' => ['nullable', Rule::exists('users', 'id')->where('empresa_id', $empresaId)],
            'data_inicio' => ['required', 'date'],
            'dia_vencimento' => ['required', 'integer', 'between:1,28'],
            'valor_mensal' => ['nullable', 'numeric', 'min:0'],
            'valor_caucao' => ['required', 'numeric', 'min:0'],
            'agendamento_primeiro_vencimento' => ['required', Rule::in(['30_dias', 'data_escolhida'])],
            'primeiro_vencimento' => ['nullable', 'required_if:agendamento_primeiro_vencimento,data_escolhida', 'date', 'after:data_inicio'],
            'desconto_percentual' => ['nullable', 'numeric', 'between:0,100'],
            'dependentes' => ['nullable', 'array'],
            'dependentes.*' => ['integer', 'exists:dependentes,id'],
        ];
    }
}
