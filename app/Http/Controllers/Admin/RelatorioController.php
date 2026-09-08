<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Relatorios\FinanceiroExcelExport;
use App\Services\Relatorios\FinanceiroPdfExport;
use App\Services\RelatorioService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class RelatorioController extends Controller
{
    public function __construct(protected RelatorioService $relatorioService) {}

    public function index(): View
    {
        Gate::authorize('auditoria.visualizar');

        $mes = request('mes') ? Carbon::parse(request('mes')) : now();

        $resumoFinanceiro = $this->relatorioService->resumoFinanceiroMensal($mes);
        $inadimplenciaPorUnidade = $this->relatorioService->inadimplenciaPorUnidade();
        $rankingVendedores = $this->relatorioService->rankingVendedores($mes->copy()->startOfMonth(), $mes->copy()->endOfMonth());

        return view('relatorios.index', compact('resumoFinanceiro', 'inadimplenciaPorUnidade', 'rankingVendedores', 'mes'));
    }

    public function financeiroPdf(FinanceiroPdfExport $export): Response
    {
        Gate::authorize('auditoria.visualizar');

        $mes = request('mes') ? Carbon::parse(request('mes')) : now();

        return $export->gerar($mes)->download("relatorio-financeiro-{$mes->format('Y-m')}.pdf");
    }

    public function financeiroExcel(FinanceiroExcelExport $export): Response
    {
        Gate::authorize('auditoria.visualizar');

        $mes = request('mes') ? Carbon::parse(request('mes')) : now();

        return $export->comMes($mes)->download("relatorio-financeiro-{$mes->format('Y-m')}.xlsx");
    }
}
