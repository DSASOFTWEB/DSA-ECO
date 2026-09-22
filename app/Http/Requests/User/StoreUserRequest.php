<?php

namespace App\Http\Requests\User;

use App\Support\ModulosPermissoes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

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
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->whereNotIn('name', ['super_admin'])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(ModulosPermissoes::todasPermissoes())],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $roles = $this->input('roles', []);
            $permissions = $this->input('permissions', []);
            if ((! is_array($roles) || $roles === []) && (! is_array($permissions) || $permissions === [])) {
                $validator->errors()->add('permissions', 'Selecione ao menos um perfil ou um módulo de acesso.');
            }
        });
    }
}
