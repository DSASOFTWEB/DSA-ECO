<?php

namespace App\Http\Requests\Produto;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProdutoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Produto::class);
    }

    public function rules(): array
    {
        return [
            'unidade_id' => ['nullable', Rule::exists('unidades', 'id')->where('empresa_id', $this->user()->empresa_id)],
            'categoria_id' => ['nullable', Rule::exists('categorias_produtos', 'id')->where('empresa_id', $this->user()->empresa_id)],
            'nome' => ['required', 'string', 'max:255'],
            // Espelha o índice único (empresa_id, sku) da migration: no MySQL ele
            // não distingue produtos soft-deleted (ver comentário na migration),
            // então um SKU de um produto excluído continua bloqueado aqui também.
            'sku' => [
                'nullable', 'string', 'max:60',
                Rule::unique('produtos', 'sku')->where('empresa_id', $this->user()->empresa_id),
            ],
            'descricao' => ['nullable', 'string'],
            'preco_custo' => ['required', 'numeric', 'min:0'],
            'preco_venda' => ['required', 'numeric', 'min:0'],
            'controla_estoque' => ['boolean'],
            'estoque_atual' => ['nullable', 'integer', 'min:0'],
            'estoque_minimo' => ['nullable', 'integer', 'min:0'],
            'ativo' => ['boolean'],
        ];
    }
}
