<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CadastroEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_nome' => ['required', 'string', 'max:255'],
            'empresa_cnpj' => ['nullable', 'string', 'max:18', 'unique:empresas,cnpj'],
            'empresa_telefone' => ['nullable', 'string', 'max:20'],
            'unidade_nome' => ['required', 'string', 'max:255'],
            'admin_nome' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'confirmed', Password::defaults()],
        ];
    }
}
