<?php

namespace App\Http\Requests\Empresa;

use App\Models\Empresa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'ie' => ['nullable', 'string', 'max:20'],
            'im' => ['nullable', 'string', 'max:20'],
            'cnae' => ['nullable', 'string', 'max:7', 'regex:/^[0-9]{0,7}$/'],
            'regime_tributario' => ['required', Rule::in(array_keys(Empresa::regimesTributarios()))],
            'aut_xml' => ['nullable', 'string', 'max:18'],
            'codigo_municipio_ibge' => ['nullable', 'string', 'size:7', 'regex:/^[0-9]{7}$/'],
            'codigo_servico_hospedagem_lc116' => ['nullable', 'string', 'max:10'],
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

            // Fiscal emitente (NF-e / NFC-e / NFS-e)
            'ambiente_nfe' => ['required', 'integer', Rule::in([1, 2])],
            'csc' => ['nullable', 'string', 'max:60'],
            'csc_id' => ['nullable', 'string', 'max:10'],
            'numero_serie_nfe' => ['required', 'integer', 'min:1', 'max:999'],
            'numero_serie_nfce' => ['required', 'integer', 'min:1', 'max:999'],
            'numero_serie_nfse' => ['required', 'integer', 'min:1', 'max:999'],
            'numero_ultima_nfe_producao' => ['required', 'integer', 'min:0'],
            'numero_ultima_nfe_homologacao' => ['required', 'integer', 'min:0'],
            'numero_ultima_nfce_producao' => ['required', 'integer', 'min:0'],
            'numero_ultima_nfce_homologacao' => ['required', 'integer', 'min:0'],
            'numero_ultima_nfse' => ['required', 'integer', 'min:0'],
            'nfse_provider' => ['required', Rule::in(['nacional_gov', 'integranotas'])],
            'nfse_nacional_habilitado' => ['boolean'],
            'token_nfse' => ['nullable', 'string', 'max:2000'],
            'token_ibpt' => ['nullable', 'string', 'max:120'],
            'bluesoft_token' => ['nullable', 'string', 'max:255'],
            'certificado' => ['nullable', 'file', 'extensions:pfx,p12', 'max:15360'],
            'certificado_senha' => ['nullable', 'string', 'max:100'],
            'observacao_padrao_nfe' => ['nullable', 'string', 'max:500'],
            'observacao_padrao_nfce' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'impressao_auto_imprimir' => $this->boolean('impressao_auto_imprimir'),
            'nfse_nacional_habilitado' => $this->boolean('nfse_nacional_habilitado'),
            'cnae' => preg_replace('/\D+/', '', (string) $this->input('cnae', '')) ?: null,
            'aut_xml' => preg_replace('/\D+/', '', (string) $this->input('aut_xml', '')) ?: null,
            'codigo_municipio_ibge' => preg_replace('/\D+/', '', (string) $this->input('codigo_municipio_ibge', '')) ?: null,
        ]);
    }
}
