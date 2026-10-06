<?php

namespace App\Http\Requests\Empresa;

use App\Models\Empresa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'codigo_tributacao_municipal_hospedagem' => ['nullable', 'string', 'max:20'],
            'aliquota_iss_hospedagem' => ['nullable', 'numeric', 'min:0', 'max:5'],
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

            // Gateway Pix do PDV — mesma regra: segredo/arquivo em branco mantém o salvo.
            'gateway_pix_provedor' => ['required', Rule::in(array_keys(Empresa::provedoresGatewayPix()))],
            'itau_client_id' => ['nullable', 'string', 'max:120'],
            'itau_client_secret' => ['nullable', 'string', 'max:255'],
            'itau_chave_pix' => ['nullable', 'string', 'max:77'],
            'gateway_pix_expiracao_minutos' => ['nullable', 'integer', 'min:1', 'max:60'],
            'itau_certificado' => ['nullable', 'file', 'extensions:crt,pem,cer', 'max:64'],
            'itau_chave_privada' => ['nullable', 'file', 'extensions:key,pem', 'max:64'],

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
            'nfse_provider' => ['required', Rule::in(['nacional_gov', 'integranotas', 'municipio'])],
            'nfse_auth_mode' => ['required', Rule::in(array_keys(Empresa::nfseAuthModes()))],
            'nfse_nacional_habilitado' => ['boolean'],
            'token_nfse' => ['nullable', 'string', 'max:2000'],
            'nfse_ws_user' => [
                'nullable',
                'string',
                'max:120',
                Rule::requiredIf(fn () => $this->input('nfse_provider') === 'municipio'
                    && $this->input('nfse_auth_mode') === Empresa::NFSE_AUTH_USUARIO_SENHA),
            ],
            'nfse_ws_senha' => ['nullable', 'string', 'max:255'],
            'nfse_ws_chave_acesso' => ['nullable', 'string', 'max:255'],
            'token_ibpt' => ['nullable', 'string', 'max:120'],
            'bluesoft_token' => ['nullable', 'string', 'max:255'],
            'certificado' => ['nullable', 'file', 'extensions:pfx,p12,bin', 'max:15360'],
            'certificado_senha' => ['nullable', 'string', 'max:100'],
            'observacao_padrao_nfe' => ['nullable', 'string', 'max:500'],
            'observacao_padrao_nfce' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $empresa = Empresa::find($this->user()->empresa_id);

            if ($this->input('gateway_pix_provedor') === Empresa::GATEWAY_PIX_MERCADOPAGO) {
                if (! filled($this->input('mercadopago_access_token')) && ! $empresa?->temMercadoPagoUtilizavel()) {
                    $validator->errors()->add('mercadopago_access_token', 'Informe o Access Token do Mercado Pago para usar o Pix no PDV.');
                }

                return;
            }

            if ($this->input('gateway_pix_provedor') !== Empresa::GATEWAY_PIX_ITAU) {
                return;
            }

            $atual = $empresa?->configuracaoGatewayPix()['itau'] ?? [];

            $faltando = [
                'itau_client_id' => ! filled($this->input('itau_client_id')) && ! filled($atual['client_id'] ?? null),
                'itau_client_secret' => ! filled($this->input('itau_client_secret')) && ! filled($atual['client_secret'] ?? null),
                'itau_chave_pix' => ! filled($this->input('itau_chave_pix')) && ! filled($atual['chave_pix'] ?? null),
            ];

            foreach (array_filter($faltando) as $campo => $_) {
                $validator->errors()->add($campo, 'Obrigatório para ativar o Pix Itaú.');
            }

            $temCertificado = (bool) ($atual['tem_certificado'] ?? false);
            if (! $temCertificado && (! $this->hasFile('itau_certificado') || ! $this->hasFile('itau_chave_privada'))) {
                $validator->errors()->add('itau_certificado', 'Envie o certificado (.crt) e a chave privada (.key) emitidos pelo Itaú.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'gateway_pix_provedor' => $this->input('gateway_pix_provedor', Empresa::GATEWAY_PIX_NENHUM),
            'impressao_auto_imprimir' => $this->boolean('impressao_auto_imprimir'),
            'nfse_nacional_habilitado' => $this->boolean('nfse_nacional_habilitado'),
            'nfse_auth_mode' => $this->input('nfse_auth_mode', Empresa::NFSE_AUTH_CERTIFICADO),
            'cnae' => preg_replace('/\D+/', '', (string) $this->input('cnae', '')) ?: null,
            'aut_xml' => preg_replace('/\D+/', '', (string) $this->input('aut_xml', '')) ?: null,
            'codigo_municipio_ibge' => preg_replace('/\D+/', '', (string) $this->input('codigo_municipio_ibge', '')) ?: null,
        ]);
    }
}
