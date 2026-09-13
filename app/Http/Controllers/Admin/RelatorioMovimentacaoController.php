<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Caixa;
use App\Models\User;
use App\Services\Relatorios\MovimentacaoPdfExport;
use App\Services\RelatorioService;
use App\Support\Financeiro;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Os 5 relatórios de movimentação de caixa pedidos pelo cliente
 * (Movimentação por Caixa, Entradas por Período, Saídas por Período,
 * Fluxo de Caixa, Consolidado de todos os Caixas) — uma única tela com
 * abas por tipo, já que todos compartilham o mesmo filtro de período e (a
 * maioria) a mesma fonte de dados (CaixaMovimentacao).
 */
class RelatorioMovimentacaoController extends Controller
{
    protected const TIPOS_VALIDOS = ['consolidado', 'por_caixa', 'entradas', 'saidas', 'fluxo'];

    public function __construct(protected RelatorioService $relatorioService) {}

    public function index(): View
    {
        Gate::authorize('auditoria.visualizar');

        $tipo = in_array(request('tipo'), self::TIPOS_VALIDOS, true) ? request('tipo') : 'consolidado';
        [$inicio, $fim] = $this->resolverPeriodo();

        $empresaId = request()->user()->empresa_id;

        return view('relatorios.movimentacoes', [
            'tipo' => $tipo,
            'inicio' => $inicio,
            'fim' => $fim,
            'porCaixa' => in_array($tipo, ['consolidado', 'por_caixa'], true) ? $this->relatorioService->relatorioPorCaixaPeriodo($inicio, $fim) : null,
            'movimentacoes' => in_array($tipo, ['entradas', 'saidas'], true) ? $this->relatorioService->movimentacoesPeriodo($inicio, $fim, $tipo === 'entradas' ? 'entrada' : 'saida', $this->filtros()) : null,
            'fluxo' => $tipo === 'fluxo' ? $this->relatorioService->fluxoDeCaixaPeriodo($inicio, $fim) : null,
            'caixas' => Caixa::where('empresa_id', $empresaId)->with('terminal')->latest('data_abertura')->get(),
            'usuarios' => User::where('empresa_id', $empresaId)->orderBy('name')->get(),
            'categoriasEntrada' => Financeiro::CATEGORIAS_ENTRADA,
            'categoriasSaida' => Financeiro::CATEGORIAS_SAIDA,
        ]);
    }

    public function pdf(MovimentacaoPdfExport $export): Response
    {
        Gate::authorize('auditoria.visualizar');

        $tipo = in_array(request('tipo'), self::TIPOS_VALIDOS, true) ? request('tipo') : 'consolidado';
        [$inicio, $fim] = $this->resolverPeriodo();

        return $export->gerar($tipo, $inicio, $fim, $this->filtros())
            ->download("relatorio-{$tipo}-{$inicio->format('Y-m-d')}-a-{$fim->format('Y-m-d')}.pdf");
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function resolverPeriodo(): array
    {
        $inicio = request('data_inicio') ? Carbon::parse(request('data_inicio'))->startOfDay() : now()->startOfMonth();
        $fim = request('data_fim') ? Carbon::parse(request('data_fim'))->endOfDay() : now()->endOfMonth();

        // Trava o período em no máximo 1 ano — evita um relatório de Fluxo
        // de Caixa (que itera dia a dia) travar a tela com uma faixa de
        // data absurda digitada por engano.
        if ($inicio->diffInDays($fim) > 366) {
            $fim = $inicio->copy()->addDays(366)->endOfDay();
        }

        return [$inicio, $fim];
    }

    /**
     * @return array{caixa_id: ?string, categoria: ?string, usuario_id: ?string}
     */
    protected function filtros(): array
    {
        return [
            'caixa_id' => request('caixa_id'),
            'categoria' => request('categoria'),
            'usuario_id' => request('usuario_id'),
        ];
    }
}
