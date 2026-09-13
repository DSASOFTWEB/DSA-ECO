<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Movimentacao\UpdateMovimentacaoRequest;
use App\Models\Caixa;
use App\Models\CaixaMovimentacao;
use App\Models\Empresa;
use App\Services\CaixaService;
use App\Support\Financeiro;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Tela única com TODAS as movimentações de caixa da empresa (entre
 * unidades/terminais), com filtros — complementa a tela `caixas/show`, que
 * só mostra o extrato de UM caixa por vez.
 */
class MovimentacaoController extends Controller
{
    public function __construct(protected CaixaService $caixaService) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CaixaMovimentacao::class);

        $empresaId = $request->user()->empresa_id;

        $movimentacoes = $this->filtrarMovimentacoes($request, $empresaId)
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('financeiro.movimentacoes.index', [
            'movimentacoes' => $movimentacoes,
            'caixas' => $this->caixasDaEmpresa($empresaId),
            'usuarios' => $this->usuariosDaEmpresa($empresaId),
            'categoriasEntrada' => Financeiro::CATEGORIAS_ENTRADA,
            'categoriasSaida' => Financeiro::CATEGORIAS_SAIDA,
            'formasPagamento' => Financeiro::FORMAS_PAGAMENTO,
        ]);
    }

    /**
     * Versão "imprimível" da mesma listagem — sem paginação (até um teto de
     * segurança) e numa página HTML enxuta própria (sem menu/sidebar), com
     * botão de imprimir via `window.print()`, igual ao padrão já usado no
     * cupom de venda (`vendas/comprovante`). O usuário usa o "Imprimir" do
     * próprio navegador (que já oferece "Salvar como PDF"), sem precisar de
     * um PDF gerado no servidor.
     */
    public function imprimir(Request $request): View
    {
        $this->authorize('viewAny', CaixaMovimentacao::class);

        $empresaId = $request->user()->empresa_id;

        $movimentacoes = $this->filtrarMovimentacoes($request, $empresaId)
            ->oldest()
            ->limit(1000)
            ->get();

        return view('financeiro.movimentacoes.imprimir', [
            'movimentacoes' => $movimentacoes,
            'empresa' => Empresa::find($empresaId),
            'filtros' => [
                'dataInicial' => $request->input('data_inicial'),
                'dataFinal' => $request->input('data_final'),
                'caixa' => $request->filled('caixa_id') ? Caixa::with('terminal')->find($request->input('caixa_id')) : null,
                'tipo' => $request->input('tipo'),
                'categoria' => $request->input('categoria'),
                'formaPagamento' => $request->input('forma_pagamento'),
                'usuario' => $request->filled('usuario_id') ? \App\Models\User::find($request->input('usuario_id')) : null,
            ],
            'geradoEm' => now(),
        ]);
    }

    protected function filtrarMovimentacoes(Request $request, int $empresaId): Builder
    {
        return CaixaMovimentacao::daEmpresa($empresaId)
            ->with(['caixa.terminal', 'caixa.unidade', 'usuario', 'estornadoPor'])
            ->when($request->filled('data_inicial'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('data_inicial')))
            ->when($request->filled('data_final'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('data_final')))
            ->when($request->filled('caixa_id'), fn ($q) => $q->where('caixa_id', $request->input('caixa_id')))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->input('tipo')))
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria', $request->input('categoria')))
            ->when($request->filled('forma_pagamento'), fn ($q) => $q->where('forma_pagamento', $request->input('forma_pagamento')))
            ->when($request->filled('usuario_id'), fn ($q) => $q->where('usuario_id', $request->input('usuario_id')));
    }

    // O filtro é por CAIXA (uma sessão de abertura/fechamento), não por
    // terminal — um mesmo terminal tem um `Caixa` novo a cada abre/fecha,
    // então listar por terminal filtraria pelo id errado (bug já corrigido
    // aqui antes: o <select> mandava o id do Terminal como se fosse
    // caixa_id, e a comparação nunca batia com nenhuma movimentação).
    protected function caixasDaEmpresa(int $empresaId)
    {
        return Caixa::where('empresa_id', $empresaId)->with('terminal')->latest('data_abertura')->get();
    }

    protected function usuariosDaEmpresa(int $empresaId)
    {
        return \App\Models\User::where('empresa_id', $empresaId)->orderBy('name')->get();
    }

    public function update(UpdateMovimentacaoRequest $request, CaixaMovimentacao $movimentacao): RedirectResponse
    {
        if (! $movimentacao->podeEditar()) {
            return back()->with('erro', 'Este lançamento não pode ser editado.');
        }

        $movimentacao->update($request->validated());

        return back()->with('sucesso', 'Lançamento atualizado.');
    }

    public function estornar(Request $request, CaixaMovimentacao $movimentacao): RedirectResponse
    {
        $this->authorize('estornar', $movimentacao);

        try {
            $this->caixaService->estornar($movimentacao, $request->user());
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Lançamento estornado.');
    }
}
