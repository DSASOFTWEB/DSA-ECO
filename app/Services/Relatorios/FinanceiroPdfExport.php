<?php

namespace App\Services\Relatorios;

use App\Models\Empresa;
use App\Services\RelatorioService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Gera o PDF do relatório financeiro mensal (visão gerencial: previsto x
 * recebido x pendente x atrasado, inadimplência por unidade). Usa o
 * pacote barryvdh/laravel-dompdf — a view é apenas HTML/CSS simples,
 * pois o dompdf não suporta CSS avançado (flexbox/grid).
 */
class FinanceiroPdfExport
{
    public function __construct(protected RelatorioService $relatorioService) {}

    public function gerar(Carbon $mes): PdfDocument
    {
        $dados = [
            'mes' => $mes,
            'empresa' => Empresa::find(Auth::user()?->empresa_id),
            'resumo' => $this->relatorioService->resumoFinanceiroMensal($mes),
            'inadimplenciaPorUnidade' => $this->relatorioService->inadimplenciaPorUnidade(),
            'contratosAtivos' => $this->relatorioService->contratosAtivosNoMes($mes),
            'geradoEm' => now(),
        ];

        return Pdf::loadView('relatorios.pdf.financeiro', $dados)->setPaper('a4', 'portrait');
    }
}
