<?php

namespace App\Http\Requests\Venda;

use App\Http\Requests\Concerns\UsaFormaPagamentoCadastrada;
use App\Models\FormaPagamento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreVendaRequest extends FormRequest
{
    use UsaFormaPagamentoCadastrada {
        prepareForValidation as prepararFormaCadastrada;
    }

    public const MAX_PARCELAS = 6;

    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Venda::class);
    }

    /**
     * Pagamento dividido (pagamentos[]): cada parcela traz a forma cadastrada
     * e o valor; o tipo e o "Pix integrado" vêm do cadastro (nunca do
     * navegador). A venda grava "multiplo" quando há formas diferentes.
     */
    protected function prepareForValidation(): void
    {
        $this->prepararFormaCadastrada();

        $parcelas = $this->input('pagamentos');
        if (! is_array($parcelas) || $parcelas === []) {
            $this->request->remove('pagamentos');

            return;
        }

        $formas = FormaPagamento::withoutGlobalScopes()
            ->where('empresa_id', $this->user()->empresa_id)
            ->where('ativo', true)
            ->whereIn('id', collect($parcelas)->pluck('forma_pagamento_id')->filter()->all())
            ->get()->keyBy('id');

        $parcelas = collect($parcelas)->values()->map(function ($parcela) use ($formas) {
            $forma = $formas[(int) ($parcela['forma_pagamento_id'] ?? 0)] ?? null;

            return [
                'forma_pagamento_id' => $parcela['forma_pagamento_id'] ?? null,
                'valor' => $parcela['valor'] ?? null,
                'forma' => $forma?->tipo,
                'integrado' => (bool) $forma?->ehPixIntegrado(),
            ];
        })->all();

        $tipos = collect($parcelas)->pluck('forma')->filter()->unique();

        $this->merge([
            'pagamentos' => $parcelas,
            'forma_pagamento' => $tipos->count() === 1 ? $tipos->first() : 'multiplo',
        ]);
    }

    public function rules(): array
    {
        return [
            'caixa_id' => ['required', 'integer', Rule::exists('caixas', 'id')->where('empresa_id', $this->user()->empresa_id)],
            'cliente_id' => ['nullable', Rule::exists('clientes', 'id')->where('empresa_id', $this->user()->empresa_id)],
            ...$this->regrasFormaPagamento(),
            'forma_pagamento' => ['required', Rule::in([...array_keys(FormaPagamento::TIPOS), 'multiplo'])],
            'pagamentos' => ['nullable', 'array', 'max:'.self::MAX_PARCELAS],
            'pagamentos.*.forma_pagamento_id' => ['required', 'integer', Rule::exists('formas_pagamento', 'id')
                ->where('empresa_id', $this->user()->empresa_id)->where('ativo', true)],
            'pagamentos.*.valor' => ['required', 'numeric', 'gt:0', 'max:9999999'],
            'pagamentos.*.forma' => ['required', Rule::in(array_keys(FormaPagamento::TIPOS))],
            'pagamentos.*.integrado' => ['boolean'],
            'observacao' => ['nullable', 'string', 'max:1000'],
            'emitir_nfce' => ['sometimes', 'boolean'],
            'itens' => ['required', 'array', 'min:1'],
            'itens.*.produto_id' => ['nullable', 'exists:produtos,id'],
            'itens.*.tipo_entrada_id' => ['nullable', 'exists:tipos_entrada,id'],
            'itens.*.quantidade' => ['required', 'integer', 'min:1'],
            'itens.*.desconto' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('itens', []) as $indice => $item) {
                $temProduto = ! empty($item['produto_id']);
                $temTipoEntrada = ! empty($item['tipo_entrada_id']);

                if ($temProduto === $temTipoEntrada) {
                    $validator->errors()->add("itens.{$indice}.produto_id", 'Informe um produto OU um tipo de entrada para cada item, nunca os dois nem nenhum.');
                }
            }

            if (collect($this->input('pagamentos', []))->where('integrado', true)->count() > 1) {
                $validator->errors()->add('pagamentos', 'Use no máximo uma parcela de Pix com QR Code por venda.');
            }
        });
    }
}
