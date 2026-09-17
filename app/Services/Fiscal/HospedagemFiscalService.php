<?php

namespace App\Services\Fiscal;

use App\Exceptions\NegocioException;
use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Models\Hospedagem;
use App\Models\Produto;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class HospedagemFiscalService
{
    public function __construct(
        protected NfceEmissaoService $nfce,
        protected NfseEmissaoService $nfse,
    ) {}

    /**
     * NFC-e dos consumos de produto (tipo_item=produto).
     */
    public function emitirNfceConsumos(Hospedagem $hospedagem, User $operador): DocumentoFiscal
    {
        $this->garantirPodeEmitir($hospedagem);

        $existente = $this->documentoAutorizado($hospedagem, DocumentoFiscal::MODELO_NFCE);
        if ($existente) {
            throw new NegocioException('Já existe NFC-e autorizada para os consumos desta hospedagem (nº '.$existente->numero.').');
        }

        $itens = $this->itensProdutos($hospedagem);
        if ($itens === []) {
            throw new NegocioException('Não há consumos de produto cadastrados para emitir NFC-e.');
        }

        return DB::transaction(function () use ($hospedagem, $operador, $itens) {
            $empresa = Empresa::findOrFail($hospedagem->empresa_id);
            $hospedagem->loadMissing('unidade', 'quarto.unidade', 'cliente');
            $unidade = $hospedagem->unidade ?: $hospedagem->quarto?->unidade;
            if (! $unidade) {
                throw new NegocioException('Unidade da hospedagem não encontrada.');
            }

            $documento = DocumentoFiscal::create([
                'empresa_id' => $hospedagem->empresa_id,
                'unidade_id' => $hospedagem->unidade_id,
                'hospedagem_id' => $hospedagem->id,
                'cliente_id' => $hospedagem->cliente_id,
                'emitido_por_id' => $operador->id,
                'modelo' => DocumentoFiscal::MODELO_NFCE,
                'origem' => 'hospedagem_consumos',
                'ambiente' => (int) ($empresa->ambiente_nfe ?: 2),
                'status' => DocumentoFiscal::STATUS_RASCUNHO,
                'forma_pagamento' => $hospedagem->forma_pagamento,
                'itens' => $itens,
                'valor_total' => collect($itens)->sum(fn ($i) => round($i['quantidade'] * $i['valor_unitario'], 2)),
            ]);

            return $this->nfce->emitir($empresa, $unidade, $hospedagem, $documento, $itens);
        });
    }

    /**
     * NFS-e das diárias + consumos de serviço / avulsos.
     */
    public function emitirNfseServicos(Hospedagem $hospedagem, User $operador): DocumentoFiscal
    {
        $this->garantirPodeEmitir($hospedagem);

        $existente = $this->documentoAutorizado($hospedagem, DocumentoFiscal::MODELO_NFSE);
        if ($existente) {
            throw new NegocioException('Já existe NFS-e autorizada para os serviços desta hospedagem (nº '.$existente->numero.').');
        }

        $itens = $this->itensServicos($hospedagem);
        if ($itens === []) {
            throw new NegocioException('Não há diárias/serviços para emitir NFS-e (verifique check-in e valor da diária).');
        }

        return DB::transaction(function () use ($hospedagem, $operador, $itens) {
            $empresa = Empresa::findOrFail($hospedagem->empresa_id);
            $hospedagem->loadMissing('quarto.unidade', 'cliente');
            $unidade = $hospedagem->quarto?->unidade;
            if (! $unidade) {
                throw new NegocioException('Unidade da hospedagem não encontrada.');
            }

            $documento = DocumentoFiscal::create([
                'empresa_id' => $hospedagem->empresa_id,
                'unidade_id' => $hospedagem->unidade_id,
                'hospedagem_id' => $hospedagem->id,
                'cliente_id' => $hospedagem->cliente_id,
                'emitido_por_id' => $operador->id,
                'modelo' => DocumentoFiscal::MODELO_NFSE,
                'origem' => 'hospedagem_servicos',
                'ambiente' => (int) ($empresa->ambiente_nfe ?: 2),
                'status' => DocumentoFiscal::STATUS_RASCUNHO,
                'forma_pagamento' => $hospedagem->forma_pagamento,
                'itens' => $itens,
                'valor_total' => collect($itens)->sum(fn ($i) => round($i['quantidade'] * $i['valor_unitario'], 2)),
            ]);

            return $this->nfse->emitir($empresa, $unidade, $hospedagem, $documento, $itens);
        });
    }

    /**
     * Reconsulta lote assíncrono municipal (GISS) quando o documento ficou processando.
     */
    public function consultarLoteNfse(DocumentoFiscal $documento): DocumentoFiscal
    {
        if ($documento->modelo !== DocumentoFiscal::MODELO_NFSE) {
            throw new NegocioException('Documento não é NFS-e.');
        }

        $empresa = Empresa::findOrFail($documento->empresa_id);
        $hospedagem = Hospedagem::query()->find($documento->hospedagem_id);
        $hospedagem?->loadMissing('quarto.unidade', 'unidade');
        $unidade = $hospedagem?->unidade ?: $hospedagem?->quarto?->unidade;
        if (! $unidade && $documento->unidade_id) {
            $unidade = \App\Models\Unidade::query()->find($documento->unidade_id);
        }
        if (! $unidade) {
            throw new NegocioException('Unidade do documento fiscal não encontrada.');
        }

        return $this->nfse->consultarLoteMunicipal($empresa, $unidade, $documento);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function itensProdutos(Hospedagem $hospedagem): array
    {
        $hospedagem->loadMissing('consumos.produto');

        $itens = [];
        foreach ($hospedagem->consumos as $consumo) {
            $produto = $consumo->produto;
            if (! $produto || $produto->tipo_item === Produto::TIPO_SERVICO) {
                continue;
            }

            $itens[] = [
                'codigo' => (string) ($produto->sku ?: $produto->id),
                'descricao' => $produto->nome,
                'quantidade' => (float) $consumo->quantidade,
                'valor_unitario' => (float) $consumo->valor_unitario,
                'ncm' => $produto->ncm,
                'cfop' => $produto->cfop ?: '5102',
                'cst_icms' => $produto->cst_icms,
                'csosn' => $produto->csosn ?: '102',
                'origem' => $produto->origem ?? 0,
                'aliq_icms' => $produto->aliq_icms,
                'cst_pis' => $produto->cst_pis ?: '49',
                'cst_cofins' => $produto->cst_cofins ?: '49',
                'unidade' => $produto->unidade_comercial ?: 'UN',
                'ean' => $produto->ean,
                'consumo_id' => $consumo->id,
            ];
        }

        return $itens;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function itensServicos(Hospedagem $hospedagem): array
    {
        $hospedagem->loadMissing('consumos.produto');
        $itens = [];

        $empresa = Empresa::find($hospedagem->empresa_id);

        // Diárias = serviço de hospedagem (usa parâmetros fiscais da empresa)
        $noites = app(\App\Services\HospedagemService::class)->noites($hospedagem);
        $valorDiaria = (float) $hospedagem->valor_diaria;
        if ($valorDiaria > 0 && $noites > 0) {
            $itens[] = [
                'descricao' => 'Hospedagem quarto '.($hospedagem->quarto?->numero ?? '').' — '.$noites.' diária(s)',
                'quantidade' => (float) $noites,
                'valor_unitario' => $valorDiaria,
                'codigo_servico_lc116' => $empresa?->codigo_servico_hospedagem_lc116,
                'codigo_tributacao_municipal' => $empresa?->codigo_tributacao_municipal_hospedagem,
                'aliq_iss' => $empresa?->aliquota_iss_hospedagem,
                'tipo' => 'diaria',
            ];
        }

        foreach ($hospedagem->consumos as $consumo) {
            $produto = $consumo->produto;
            $ehServico = $produto?->tipo_item === Produto::TIPO_SERVICO;
            $ehAvulso = $produto === null;

            if (! $ehServico && ! $ehAvulso) {
                continue;
            }

            $itens[] = [
                'descricao' => $consumo->nomeItem(),
                'quantidade' => (float) $consumo->quantidade,
                'valor_unitario' => (float) $consumo->valor_unitario,
                'codigo_servico_lc116' => $produto?->codigo_servico_lc116 ?: $empresa?->codigo_servico_hospedagem_lc116,
                'codigo_tributacao_municipal' => $produto?->codigo_tributacao_municipal ?: $empresa?->codigo_tributacao_municipal_hospedagem,
                'cnae' => $produto?->cnae_servico,
                'nbs' => $produto?->nbs,
                'aliq_iss' => $produto?->aliq_iss ?? $empresa?->aliquota_iss_hospedagem,
                'consumo_id' => $consumo->id,
                'tipo' => $ehAvulso ? 'avulso' : 'servico',
            ];
        }

        return $itens;
    }

    protected function garantirPodeEmitir(Hospedagem $hospedagem): void
    {
        if (! $hospedagem->estaHospedado() && ! $hospedagem->estaFinalizado()) {
            throw new NegocioException('Só é possível emitir notas após o check-in (estadia em andamento ou finalizada).');
        }
    }

    protected function documentoAutorizado(Hospedagem $hospedagem, string $modelo): ?DocumentoFiscal
    {
        return DocumentoFiscal::query()
            ->where('hospedagem_id', $hospedagem->id)
            ->where('modelo', $modelo)
            ->where('status', DocumentoFiscal::STATUS_AUTORIZADO)
            ->latest('id')
            ->first();
    }
}
