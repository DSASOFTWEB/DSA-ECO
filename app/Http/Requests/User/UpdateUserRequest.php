<?php

namespace App\Http\Requests\User;

use App\Support\ModulosPermissoes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('usuario'));
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'unidade_id' => ['nullable', Rule::exists('unidades', 'id')->where('empresa_id', $empresaId)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('usuario'))],
            'telefone' => ['nullable', 'string', 'max:20'],
            'percentual_comissao' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'percentual_comissao_reativacao' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['nullable', 'in:ativo,inativo'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->whereNotIn('name', ['super_admin'])],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(ModulosPermissoes::todasPermissoes())],
        ];
    }
}
