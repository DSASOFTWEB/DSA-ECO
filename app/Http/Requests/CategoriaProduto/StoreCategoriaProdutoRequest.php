<?php

namespace App\Http\Requests\CategoriaProduto;

use App\Models\CategoriaProduto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoriaProdutoRequest extends FormRequest
{
    public function authorize(): bool
    {
        $categoria = $this->route('categoriaProduto');

        return $categoria
            ? $this->user()->can('update', $categoria)
            : $this->user()->can('create', CategoriaProduto::class);
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;
        $categoria = $this->route('categoriaProduto');

        $regrasPai = [
            'nullable',
            Rule::exists('categorias_produtos', 'id')
                ->where('empresa_id', $empresaId)
                ->whereNull('categoria_pai_id'),
        ];

        if ($categoria) {
            // Só categoria RAIZ pode ser escolhida como "categoria mãe" —
            // trava a hierarquia em 2 níveis (categoria > subcategoria),
            // sem risco de ciclo — e uma categoria nunca pode ser mãe dela mesma.
            $regrasPai[] = Rule::notIn([$categoria->id]);
        }

        return [
            'nome' => ['required', 'string', 'max:150'],
            'categoria_pai_id' => $regrasPai,
        ];
    }
}
