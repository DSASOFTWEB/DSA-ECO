<?php

namespace App\Services\Fiscal;

use App\Exceptions\IntegrationException;
use App\Exceptions\NegocioException;
use App\Models\DocumentoFiscal;
use App\Models\Empresa;
use App\Models\Produto;
use App\Models\User;
use App\Models\Venda;
use Illuminate\Support\Facades\DB;

/**
 * NFC-e da venda (PDV, mesa e comanda), escolhida na finalização ("F4
 * emitir NFC-e / F2 apenas pedido"). Só mercadorias entram na nota:
 * ingressos (tipos de entrada), produtos marcados como serviço e a taxa
 * de serviço da mesa são ISS e ficam de fora.
 */
class VendaFiscalService
{
    public function __construct(protected NfceEmissaoService $nfce) {}

    /** Venda tem algo que caiba numa NFC-e? (decide se a pergunta aparece) */
    public function temItensNfce(Venda $venda): bool
    {
        return $this->itensProdutos($venda) !== [];
    }

    /**
     * Emite sem desfazer a venda em caso de falha: devolve a mensagem para
     * a tela mostrar ao operador.
     *
     * @return array{ok: bool, mensagem: string, documento_id?: int}
     */
    public function emitirAposVenda(Venda $venda, User $operador): array
    {
        try {
            $documento = $this->emitirNfce($venda, $operador);

            return ['ok' => true, 'mensagem' => "NFC-e nº {$documento->numero} autorizada.", 'documento_id' => $documento->id];
        } catch (NegocioException|IntegrationException $e) {
            return ['ok' => false, 'mensagem' => 'A venda foi registrada, mas a NFC-e não foi emitida: '.$e->getMessage()];
        }
    }

    public function emitirNfce(Venda $venda, User $operador): DocumentoFiscal
    {
        if ($venda->status !== 'pago') {
            throw new NegocioException('Só é possível emitir NFC-e de venda paga.');
        }

        $existente = DocumentoFiscal::query()
            ->where('venda_id', $venda->id)
            ->where('modelo', DocumentoFiscal::MODELO_NFCE)
            ->where('status', DocumentoFiscal::STATUS_AUTORIZADO)
            ->first();
        if ($existente) {
            throw new NegocioException("Esta venda já tem NFC-e autorizada (nº {$existente->numero}).");
        }

        $itens = $this->itensProdutos($venda);
        if ($itens === []) {
            throw new NegocioException('A venda não tem produtos para NFC-e (ingressos e serviços não entram na nota).');
        }

        $empresa = Empresa::findOrFail($venda->empresa_id);
        if (! $empresa->nfceHabilitada()) {
            throw new NegocioException('Configure o certificado digital e o CSC da NFC-e em Minha Empresa.');
        }

        $venda->loadMissing('unidade', 'cliente');
        if (! $venda->unidade) {
            throw new NegocioException('Unidade da venda não encontrada.');
        }

        return DB::transaction(function () use ($venda, $operador, $itens, $empresa) {
            $documento = DocumentoFiscal::create([
                'empresa_id' => $venda->empresa_id,
                'unidade_id' => $venda->unidade_id,
                'venda_id' => $venda->id,
                'cliente_id' => $venda->cliente_id,
                'emitido_por_id' => $operador->id,
                'modelo' => DocumentoFiscal::MODELO_NFCE,
                'origem' => $venda->origem === Venda::ORIGEM_MESA ? 'venda_mesa' : 'venda_pdv',
                'ambiente' => (int) ($empresa->ambiente_nfe ?: 2),
                'status' => DocumentoFiscal::STATUS_RASCUNHO,
                'forma_pagamento' => $venda->forma_pagamento,
                'itens' => $itens,
                'valor_total' => NfceEmissaoService::totalItens($itens),
            ]);

            $formaFiscal = $venda->forma_pagamento === 'multiplo' ? 'outro' : $venda->forma_pagamento;

            return $this->nfce->emitir($empresa, $venda->unidade, $venda->cliente, $formaFiscal, $documento, $itens);
        });
    }

    /**
     * Itens de mercadoria da venda, com o desconto de cada um. Na mesa, o
     * desconto é dado na conta inteira: a parte que não está nos itens é
     * rateada entre os produtos, para a nota bater com o que foi cobrado.
     *
     * @return list<array<string, mixed>>
     */
    public function itensProdutos(Venda $venda): array
    {
        $venda->loadMissing('itens.produto');

        $produtos = $venda->itens->filter(fn ($item) => $item->produto && $item->produto->tipo_item !== Produto::TIPO_SERVICO)->values();
        if ($produtos->isEmpty()) {
            return [];
        }

        $descontoNosItens = (float) $venda->itens->sum('desconto');
        $descontoExtra = max(0, round((float) $venda->desconto - $descontoNosItens, 2));
        $baseRateio = (float) $produtos->sum(fn ($i) => (float) $i->subtotal);

        $itens = [];
        $rateado = 0.0;
        foreach ($produtos as $indice => $item) {
            $produto = $item->produto;
            $parteExtra = 0.0;
            if ($descontoExtra > 0 && $baseRateio > 0) {
                $parteExtra = $indice === $produtos->count() - 1
                    ? round($descontoExtra - $rateado, 2)
                    : round($descontoExtra * ((float) $item->subtotal / $baseRateio), 2);
                $rateado += $parteExtra;
            }

            $itens[] = [
                'codigo' => (string) ($produto->sku ?: $produto->id),
                'descricao' => $produto->nome,
                'quantidade' => (float) $item->quantidade,
                'valor_unitario' => (float) $item->preco_unitario,
                'desconto' => round((float) $item->desconto + $parteExtra, 2),
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
                'venda_item_id' => $item->id,
            ];
        }

        return $itens;
    }
}
