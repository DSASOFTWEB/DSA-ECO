<?php

namespace App\Services\Relatorios;

use App\Models\Empresa;
use App\Services\RelatorioService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * PDF de qualquer um dos 5 relatórios de movimentação (ver
 * RelatorioMovimentacaoController) — uma view só, com uma seção por tipo,
 * pra não duplicar 5 templates quase idênticos.
 */
class MovimentacaoPdfExport
{
    public function __construct(protected RelatorioService $relatorioService) {}

    public function gerar(string $tipo, Carbon $inicio, Carbon $fim, array $filtros = []): PdfDocument
    {
        $dados = [
            'tipo' => $tipo,
            'inicio' => $inicio,
            'fim' => $fim,
            'empresa' => Empresa::find(Auth::user()?->empresa_id),
            'geradoEm' => now(),
            'porCaixa' => in_array($tipo, ['consolidado', 'por_caixa'], true) ? $this->relatorioService->relatorioPorCaixaPeriodo($inicio, $fim) : null,
            'movimentacoes' => in_array($tipo, ['entradas', 'saidas'], true) ? $this->relatorioService->movimentacoesPeriodo($inicio, $fim, $tipo === 'entradas' ? 'entrada' : 'saida', $filtros) : null,
            'fluxo' => $tipo === 'fluxo' ? $this->relatorioService->fluxoDeCaixaPeriodo($inicio, $fim) : null,
        ];

        return Pdf::loadView('relatorios.pdf.movimentacoes', $dados)->setPaper('a4', 'portrait');
    }
}
