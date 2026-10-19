<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Unidade;
use App\Services\Relatorios\RelatorioGerencialExcelExport;
use App\Services\Relatorios\RelatorioGerencialService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Relatórios gerenciais (faturamento, produtos, caixas, mesas, quartos...).
 * Mesmo filtro em todos: período + unidade. A tela, a impressão e o Excel
 * usam os mesmos dados de RelatorioGerencialService.
 */
class RelatorioGerencialController extends Controller
{
    public function __construct(protected RelatorioGerencialService $service) {}

    public function show(Request $request, string $relatorio): View
    {
        Gate::authorize('auditoria.visualizar');
        abort_unless(isset(RelatorioGerencialService::RELATORIOS[$relatorio]), 404);

        [$inicio, $fim, $unidadeId] = $this->filtros($request);

        return view('relatorios.gerencial', [
            'chave' => $relatorio,
            'relatorio' => $this->service->periodo($inicio, $fim, $unidadeId)->gerar($relatorio),
            'inicio' => $inicio,
            'fim' => $fim,
            'unidadeId' => $unidadeId,
            'unidades' => Unidade::ativas()->orderBy('nome')->get(),
            'imprimir' => $request->boolean('imprimir'),
        ]);
    }

    public function excel(Request $request, string $relatorio): Response
    {
        Gate::authorize('auditoria.visualizar');
        abort_unless(isset(RelatorioGerencialService::RELATORIOS[$relatorio]), 404);

        [$inicio, $fim, $unidadeId] = $this->filtros($request);
        $dados = $this->service->periodo($inicio, $fim, $unidadeId)->gerar($relatorio);

        return (new RelatorioGerencialExcelExport($dados))
            ->download("relatorio-{$relatorio}-{$inicio->format('Y-m-d')}-a-{$fim->format('Y-m-d')}.xlsx");
    }

    /** @return array{0: Carbon, 1: Carbon, 2: ?int} */
    protected function filtros(Request $request): array
    {
        $dados = $request->validate([
            'inicio' => ['nullable', 'date'],
            'fim' => ['nullable', 'date'],
            'unidade_id' => ['nullable', 'integer'],
        ]);

        $inicio = isset($dados['inicio']) ? Carbon::parse($dados['inicio']) : now()->startOfMonth();
        $fim = isset($dados['fim']) ? Carbon::parse($dados['fim']) : now();
        if ($fim->lt($inicio)) {
            [$inicio, $fim] = [$fim, $inicio];
        }

        // Unidade só vale se for da empresa (Unidade tem escopo do tenant).
        $unidadeId = isset($dados['unidade_id']) && Unidade::whereKey($dados['unidade_id'])->exists()
            ? (int) $dados['unidade_id']
            : null;

        return [$inicio, $fim, $unidadeId];
    }
}
