<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RelatorioService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(RelatorioService $relatorioService): View
    {
        $kpis = $relatorioService->kpisDashboard();
        $resumoFinanceiro = $relatorioService->resumoFinanceiroMensal();
        $inadimplenciaPorUnidade = $relatorioService->inadimplenciaPorUnidade();
        $entradasPorHora = $relatorioService->entradasPorHoraHoje();
        $velasCaixa = $relatorioService->velasCaixa();
        $entradasPorTipoEntrada = $relatorioService->entradasHojePorTipoEntrada();

        return view('dashboard', compact('kpis', 'resumoFinanceiro', 'inadimplenciaPorUnidade', 'entradasPorHora', 'velasCaixa', 'entradasPorTipoEntrada'));
    }
}
