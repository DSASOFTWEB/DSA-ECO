<?php

namespace App\Http\Requests\Produto;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProdutoRequest extends FormRequest
{
    use ProdutoFiscalRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('produto'));
    }

    protected function prepareForValidation(): void
    {
        $this->prepareFiscalBooleans();
    }

    public function rules(): array
    {
        return array_merge([
            'unidade_id' => ['nullable', Rule::exists('unidades', 'id')->where('empresa_id', $this->user()->empresa_id)],
            'categoria_id' => ['nullable', Rule::exists('categorias_produtos', 'id')->where('empresa_id', $this->user()->empresa_id)],
            'nome' => ['required', 'string', 'max:255'],
            'sku' => [
                'nullable', 'string', 'max:60',
                Rule::unique('produtos', 'sku')
                    ->where('empresa_id', $this->user()->empresa_id)
                    ->ignore($this->route('produto')),
            ],
            'descricao' => ['nullable', 'string'],
            'preco_custo' => ['required', 'numeric', 'min:0'],
            'preco_venda' => ['required', 'numeric', 'min:0'],
            'controla_estoque' => ['boolean'],
            'estoque_minimo' => ['nullable', 'integer', 'min:0'],
            'ativo' => ['boolean'],
        ], $this->regrasFiscaisProduto());
    }
}
