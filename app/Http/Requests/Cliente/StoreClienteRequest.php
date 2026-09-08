<?php

namespace App\Http\Requests\Cliente;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Cliente::class);
    }

    public function rules(): array
    {
        return [
            'unidade_id' => ['nullable', Rule::exists('unidades', 'id')->where('empresa_id', $this->user()->empresa_id)],
            'nome' => ['required', 'string', 'max:255'],
            'cpf' => ['required', 'string', 'max:14'],
            'rg' => ['nullable', 'string', 'max:20'],
            'data_nascimento' => ['nullable', 'date'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'cep' => ['nullable', 'string', 'max:9'],
            'endereco' => ['nullable', 'string', 'max:255'],
            'numero' => ['nullable', 'string', 'max:20'],
            'complemento' => ['nullable', 'string', 'max:255'],
            'bairro' => ['nullable', 'string', 'max:255'],
            'cidade' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', 'string', 'size:2'],
            'observacoes' => ['nullable', 'string'],
            'foto' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'nome.required' => 'Informe o nome do cliente.',
            'cpf.required' => 'Informe o CPF do cliente.',
        ];
    }
}
