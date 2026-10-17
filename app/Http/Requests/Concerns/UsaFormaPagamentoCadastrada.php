<?php

namespace App\Http\Requests\Concerns;

use App\Models\FormaPagamento;
use Illuminate\Validation\Rule;

/**
 * Para telas que mandam a forma cadastrada (forma_pagamento_id): o registro
 * grava o tipo dela em forma_pagamento, que é o que caixa e relatórios agrupam.
 */
trait UsaFormaPagamentoCadastrada
{
    protected function prepareForValidation(): void
    {
        $forma = $this->formaPagamentoCadastrada();

        if ($forma) {
            $this->merge(['forma_pagamento' => $forma->tipo]);
        }
    }

    public function formaPagamentoCadastrada(): ?FormaPagamento
    {
        if (! $this->filled('forma_pagamento_id')) {
            return null;
        }

        return FormaPagamento::withoutGlobalScopes()
            ->where('empresa_id', $this->user()->empresa_id)
            ->where('ativo', true)
            ->find($this->integer('forma_pagamento_id'));
    }

    protected function regrasFormaPagamento(): array
    {
        return [
            'forma_pagamento_id' => ['nullable', 'integer', Rule::exists('formas_pagamento', 'id')
                ->where('empresa_id', $this->user()->empresa_id)->where('ativo', true)],
            'forma_pagamento' => ['required', Rule::in(array_keys(FormaPagamento::TIPOS))],
        ];
    }
}
