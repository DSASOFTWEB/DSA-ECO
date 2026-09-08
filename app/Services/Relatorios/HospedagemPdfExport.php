<?php

namespace App\Services\Relatorios;

use App\Models\Empresa;
use App\Services\HospedagemService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * PDF do relatório da Pousada por período — estadias finalizadas, total
 * faturado, ticket médio e quebra por quarto. Mesmo padrão de
 * FinanceiroPdfExport (HTML/CSS simples, dompdf não suporta flexbox/grid).
 */
class HospedagemPdfExport
{
    public function __construct(protected HospedagemService $hospedagemService) {}

    public function gerar(Carbon $inicio, Carbon $fim): PdfDocument
    {
        $dados = [
            'inicio' => $inicio,
            'fim' => $fim,
            'empresa' => Empresa::find(Auth::user()?->empresa_id),
            'relatorio' => $this->hospedagemService->relatorioPeriodo($inicio, $fim),
            'geradoEm' => now(),
        ];

        return Pdf::loadView('relatorios.pdf.hospedagem', $dados)->setPaper('a4', 'portrait');
    }
}
