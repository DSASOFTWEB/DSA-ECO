<?php

namespace App\Http\Requests\Fornecedor;

use App\Models\Fornecedor;
use Illuminate\Foundation\Http\FormRequest;

class StoreFornecedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $fornecedor = $this->route('fornecedor');

        return $fornecedor ? $this->user()->can('update', $fornecedor) : $this->user()->can('create', Fornecedor::class);
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:150'],
            'documento' => ['nullable', 'string', 'max:20'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'contato' => ['nullable', 'string', 'max:150'],
        ];
    }
}
