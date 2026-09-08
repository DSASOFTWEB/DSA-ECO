<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\User::class);
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'unidade_id' => ['nullable', Rule::exists('unidades', 'id')->where('empresa_id', $empresaId)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'percentual_comissao' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'percentual_comissao_reativacao' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'password' => ['required', Password::defaults()],
            'roles' => ['required', 'array', 'min:1'],
            // super_admin é reservado à equipe interna — nunca atribuível por
            // este formulário, mesmo por um admin do próprio tenant.
            'roles.*' => ['string', Rule::exists('roles', 'name')->whereNotIn('name', ['super_admin'])],
        ];
    }
}
