<?php

namespace App\Services\Relatorios;

use App\Services\BarcodeService;
use App\Services\QrCodeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Collection;

/**
 * Voucher de entrada cortesia (presente/promocional) — sem valor, gerado
 * pela recepção sem passar por venda. Igual ao voucher de check-in/venda
 * avulsa (mesmo QR de validação na portaria), com o selo "cortesia" para
 * deixar claro que não foi pago.
 */
class VoucherCortesiaPdfExport
{
    public function __construct(
        protected QrCodeService $qrCodeService,
        protected BarcodeService $barcodeService,
    ) {}

    /**
     * @param  Collection<int,\App\Models\Acesso>  $acessos
     */
    public function gerar(Collection $acessos): PdfDocument
    {
        $primeiro = $acessos->first();
        $empresa = $primeiro?->unidade?->empresa;

        $qrCodes = $acessos->mapWithKeys(fn ($acesso) => [
            $acesso->id => $acesso->codigo_validacao ? $this->qrCodeService->gerarDataUri($acesso->codigo_validacao, 160) : null,
        ]);

        $barcodes = $acessos->mapWithKeys(fn ($acesso) => [
            $acesso->id => $acesso->codigo_validacao ? $this->barcodeService->gerarDataUri($acesso->codigo_validacao) : null,
        ]);

        return Pdf::loadView('cortesias.pdf.voucher', [
            'acessos' => $acessos,
            'empresa' => $empresa,
            'qrCodes' => $qrCodes,
            'barcodes' => $barcodes,
            'geradoEm' => now(),
        ])->setPaper('a4', 'portrait');
    }
}
