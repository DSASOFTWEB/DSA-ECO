<?php

namespace App\Http\Requests\Produto;

use App\Support\FiscalTabelas;
use Illuminate\Validation\Rule;

trait ProdutoFiscalRules
{
    /**
     * Campos fiscais para NFC-e (produto) e NFS-e Nacional (serviço).
     * Obrigatoriedade rígida fica na emissão; no cadastro exigimos formato válido.
     */
    protected function regrasFiscaisProduto(): array
    {
        $cstSaida = array_keys(FiscalTabelas::cstPisCofinsSaida());
        $cstEntrada = array_keys(FiscalTabelas::cstPisCofinsEntrada());

        return [
            'tipo_item' => ['required', Rule::in(['produto', 'servico'])],
            'ean' => ['nullable', 'string', 'max:14', 'regex:/^[0-9]{8,14}$/'],
            'unidade_comercial' => ['required', 'string', 'max:6'],
            'imagem_url' => ['nullable', 'url', 'max:500'],

            'ncm' => ['nullable', 'string', 'size:8', 'regex:/^[0-9]{8}$/'],
            'cest' => ['nullable', 'string', 'max:7', 'regex:/^[0-9]{7}$/'],
            'cfop' => ['nullable', 'string', 'size:4', 'regex:/^[0-9]{4}$/'],
            'origem' => ['nullable', 'integer', 'min:0', 'max:8'],
            'cst_icms' => ['nullable', 'string', Rule::in(array_keys(FiscalTabelas::cstIcms()))],
            'csosn' => ['nullable', 'string', Rule::in(array_keys(FiscalTabelas::csosn()))],
            'aliq_icms' => ['nullable', 'numeric', 'min:0', 'max:100'],

            // PIS/COFINS saída (venda)
            'cst_pis' => ['nullable', 'string', Rule::in($cstSaida)],
            'aliq_pis' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cst_cofins' => ['nullable', 'string', Rule::in($cstSaida)],
            'aliq_cofins' => ['nullable', 'numeric', 'min:0', 'max:100'],

            // PIS/COFINS entrada (compra)
            'cst_pis_entrada' => ['nullable', 'string', Rule::in($cstEntrada)],
            'aliq_pis_entrada' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cst_cofins_entrada' => ['nullable', 'string', Rule::in($cstEntrada)],
            'aliq_cofins_entrada' => ['nullable', 'numeric', 'min:0', 'max:100'],

            'cst_ipi' => ['nullable', 'string', 'max:2'],
            'aliq_ipi' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cod_beneficio' => ['nullable', 'string', 'max:10'],

            'codigo_servico_lc116' => ['nullable', 'string', 'max:10'],
            'codigo_tributacao_municipal' => ['nullable', 'string', 'max:20'],
            'cnae_servico' => ['nullable', 'string', 'max:7', 'regex:/^[0-9]{7}$/'],
            'nbs' => ['nullable', 'string', 'max:9', 'regex:/^[0-9]{9}$/'],
            'aliq_iss' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'iss_retido' => ['boolean'],
        ];
    }

    protected function prepareFiscalBooleans(): void
    {
        $digits = static function (mixed $v): ?string {
            $clean = preg_replace('/\D+/', '', (string) $v) ?: '';

            return $clean !== '' ? $clean : null;
        };

        $imagemUrl = trim((string) $this->input('imagem_url', ''));
        $emptyToNull = static fn (mixed $v): ?string => ($v === null || $v === '') ? null : (string) $v;

        $this->merge([
            'iss_retido' => $this->boolean('iss_retido'),
            'controla_estoque' => $this->boolean('controla_estoque'),
            'ativo' => $this->boolean('ativo'),
            'tipo_item' => $this->input('tipo_item', 'produto'),
            'unidade_comercial' => strtoupper((string) ($this->input('unidade_comercial') ?: 'UN')),
            'ean' => $digits($this->input('ean')),
            'ncm' => $digits($this->input('ncm')),
            'cest' => $digits($this->input('cest')),
            'cfop' => $digits($this->input('cfop')),
            'cnae_servico' => $digits($this->input('cnae_servico')),
            'nbs' => $digits($this->input('nbs')),
            'imagem_url' => $imagemUrl !== '' ? $imagemUrl : null,
            'origem' => $this->filled('origem') ? (int) $this->input('origem') : null,
            'cst_icms' => $emptyToNull($this->input('cst_icms')),
            'csosn' => $emptyToNull($this->input('csosn')),
            'cst_pis' => $emptyToNull($this->input('cst_pis')),
            'cst_cofins' => $emptyToNull($this->input('cst_cofins')),
            'cst_pis_entrada' => $emptyToNull($this->input('cst_pis_entrada')),
            'cst_cofins_entrada' => $emptyToNull($this->input('cst_cofins_entrada')),
            'cst_ipi' => $emptyToNull($this->input('cst_ipi')),
            'cod_beneficio' => $emptyToNull($this->input('cod_beneficio')),
            'codigo_servico_lc116' => $emptyToNull($this->input('codigo_servico_lc116')),
            'codigo_tributacao_municipal' => $emptyToNull($this->input('codigo_tributacao_municipal')),
        ]);
    }
}
