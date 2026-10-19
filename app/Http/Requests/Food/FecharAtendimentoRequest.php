<?php

namespace App\Http\Requests\Food;

use App\Models\FormaPagamento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FecharAtendimentoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('close', $this->route('atendimento'));
    }

    /**
     * A tela manda a forma cadastrada (pagamentos.*.forma_pagamento_id); o
     * fechamento grava o tipo dela, que é o que caixa e relatórios agrupam.
     */
    protected function prepareForValidation(): void
    {
        $pagamentos = $this->input('pagamentos');

        if (! is_array($pagamentos)) {
            return;
        }

        foreach ($pagamentos as $indice => $parcela) {
            $forma = $this->formaPagamentoCadastrada($indice);
            if ($forma && is_array($parcela)) {
                $pagamentos[$indice]['forma'] = $forma->tipo;
            }
        }

        $this->merge(['pagamentos' => $pagamentos]);
    }

    public function formaPagamentoCadastrada(int|string $indice): ?FormaPagamento
    {
        $id = $this->input("pagamentos.{$indice}.forma_pagamento_id");

        if (! is_numeric($id)) {
            return null;
        }

        return FormaPagamento::withoutGlobalScopes()
            ->where('empresa_id', $this->user()->empresa_id)
            ->where('ativo', true)
            ->find((int) $id);
    }

    public function rules(): array
    {
        return [
            'caixa_id' => ['required', Rule::exists('caixas', 'id')->where('empresa_id', $this->user()->empresa_id)->where('status', 'aberto')],
            'desconto' => ['nullable', 'numeric', 'min:0'],
            'percentual_servico' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pagamentos' => ['required', 'array', 'min:1'],
            'pagamentos.*.forma_pagamento_id' => ['nullable', 'integer', Rule::exists('formas_pagamento', 'id')
                ->where('empresa_id', $this->user()->empresa_id)->where('ativo', true)],
            'pagamentos.*.forma' => ['required', Rule::in(array_keys(FormaPagamento::TIPOS))],
            'pagamentos.*.valor' => ['required', 'numeric', 'gt:0'],
            'emitir_nfce' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'caixa_id.required' => 'Selecione o caixa.',
            'caixa_id.exists' => 'O caixa selecionado não está aberto.',
            'pagamentos.*.forma.required' => 'Selecione a forma de pagamento.',
        ];
    }
}
