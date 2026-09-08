<?php

namespace App\Services\Relatorios;

use App\Models\Caixa;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;

/**
 * Gera o PDF do caixa (aberto ou fechado) — resumo + extrato de
 * movimentações, para impressão na recepção ou conferência ao fechar.
 */
class CaixaPdfExport
{
    public function gerar(Caixa $caixa): PdfDocument
    {
        $caixa->loadMissing(['unidade', 'terminal', 'usuarioAbertura', 'usuarioFechamento', 'movimentacoes.usuario', 'empresa']);

        $entradas = (float) $caixa->movimentacoes->where('tipo', 'entrada')->sum('valor');
        $saidas = (float) $caixa->movimentacoes->where('tipo', 'saida')->sum('valor');

        return Pdf::loadView('caixas.pdf.caixa', [
            'caixa' => $caixa,
            'empresa' => $caixa->empresa,
            'entradas' => $entradas,
            'saidas' => $saidas,
            'geradoEm' => now(),
        ])->setPaper('a4', 'portrait');
    }
}
