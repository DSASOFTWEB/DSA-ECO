<?php

namespace App\Services\Relatorios;

use App\Models\Contrato;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;

/**
 * Gera o PDF do contrato de adesão assinável — o mesmo documento que antes
 * era preenchido em papel. Usado tanto para impressão na recepção quanto
 * para enviar ao cliente (WhatsApp/e-mail) logo após a contratação.
 */
class ContratoPdfExport
{
    public function gerar(Contrato $contrato): PdfDocument
    {
        $contrato->loadMissing(['cliente', 'plano', 'unidade', 'dependentes', 'empresa']);

        return Pdf::loadView('contratos.pdf.contrato', [
            'contrato' => $contrato,
            'cliente' => $contrato->cliente,
            'plano' => $contrato->plano,
            'empresa' => $contrato->empresa,
            'geradoEm' => now(),
        ])->setPaper('a4', 'portrait');
    }
}
