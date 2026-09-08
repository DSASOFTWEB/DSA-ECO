<?php

namespace App\Services\Relatorios;

use App\Models\Venda;
use App\Services\BarcodeService;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;

/**
 * Voucher de entrada avulsa — comprovante impresso/enviado ao cliente
 * (venda no PDV ou compra pelo link público de autoatendimento) para
 * apresentar na portaria. Um ticket por PESSOA (por Acesso), não por item
 * da venda — uma compra de "2x Adulto" vira 2 vouchers com QR próprio cada
 * um, porque a validação na portaria é individual (ver AcessoService::validarVoucher).
 */
class VoucherEntradaPdfExport
{
    public function __construct(
        protected QrCodeService $qrCodeService,
        protected BarcodeService $barcodeService,
    ) {}

    public function gerar(Venda $venda): PdfDocument
    {
        $venda->loadMissing(['unidade', 'empresa', 'cliente', 'acessos.vendaItem.tipoEntrada']);

        $acessos = $venda->acessos()->whereNotNull('venda_item_id')->with('vendaItem.tipoEntrada')->get();

        $qrCodes = $acessos->mapWithKeys(fn ($acesso) => [
            $acesso->id => $acesso->codigo_validacao ? $this->qrCodeService->gerarDataUri($acesso->codigo_validacao, 160) : null,
        ]);

        $barcodes = $acessos->mapWithKeys(fn ($acesso) => [
            $acesso->id => $acesso->codigo_validacao ? $this->barcodeService->gerarDataUri($acesso->codigo_validacao) : null,
        ]);

        return Pdf::loadView('vendas.pdf.voucher', [
            'venda' => $venda,
            'empresa' => $venda->empresa,
            'acessos' => $acessos,
            'qrCodes' => $qrCodes,
            'barcodes' => $barcodes,
            'geradoEm' => now(),
        ])->setPaper('a4', 'portrait');
    }
}
