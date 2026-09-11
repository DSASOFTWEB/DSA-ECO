<?php

namespace App\Http\Requests\Empresa;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmpresaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('empresa.gerenciar');
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'razao_social' => ['nullable', 'string', 'max:255'],
            'cnpj' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefone' => ['nullable', 'string', 'max:20'],
            'endereco' => ['nullable', 'string', 'max:500'],
            // svg de propósito fora da lista: o dompdf (usado nos PDFs de
            // contrato/caixa/relatório/voucher) tem suporte instável a SVG —
            // um logo em SVG apareceria certo na tela mas quebrado no PDF.
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'],

            // Integrações — deixar em branco mantém o que já estava salvo
            // (ver EmpresaService::atualizar, que só sobrescreve campo
            // preenchido) e, se nunca foi preenchido, usa a credencial
            // padrão da plataforma.
            'mercadopago_access_token' => ['nullable', 'string', 'max:255'],
            'mercadopago_public_key' => ['nullable', 'string', 'max:255'],
            'mercadopago_webhook_secret' => ['nullable', 'string', 'max:255'],
            'evolution_base_url' => ['nullable', 'url', 'max:255'],
            'evolution_api_key' => ['nullable', 'string', 'max:255'],
            'evolution_instance' => ['nullable', 'string', 'max:100'],

            // Impressão do cupom PDV
            'impressao_modo' => ['required', 'in:dom,escpos,ambos'],
            'impressao_colunas' => ['required', 'integer', 'in:32,40,42,48'],
            'impressao_agente_url' => ['nullable', 'url', 'max:255'],
            'impressao_auto_imprimir' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'impressao_auto_imprimir' => $this->boolean('impressao_auto_imprimir'),
        ]);
    }
}
