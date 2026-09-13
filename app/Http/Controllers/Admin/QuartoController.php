<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Quarto\StoreQuartoRequest;
use App\Models\Produto;
use App\Models\Quarto;
use App\Models\Unidade;
use App\Services\HospedagemService;
use App\Services\QuartoEstoqueService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;

class QuartoController extends Controller
{
    public function __construct(
        protected HospedagemService $hospedagemService,
        protected QuartoEstoqueService $quartoEstoqueService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Quarto::class);

        $quartos = Quarto::with('unidade')->orderBy('unidade_id')->orderBy('numero')->paginate(15);

        return view('quartos.index', compact('quartos'));
    }

    public function create(): View
    {
        $this->authorize('create', Quarto::class);

        $unidades = Unidade::ativas()->orderBy('nome')->get();

        return view('quartos.create', compact('unidades'));
    }

    public function store(StoreQuartoRequest $request): RedirectResponse
    {
        $quarto = Quarto::create($request->validated());

        return redirect()->route('quartos.index')->with('sucesso', "Quarto \"{$quarto->numero}\" criado com sucesso.");
    }

    public function edit(Quarto $quarto): View
    {
        $this->authorize('update', $quarto);

        $unidades = Unidade::ativas()->orderBy('nome')->get();

        return view('quartos.edit', compact('quarto', 'unidades'));
    }

    public function update(StoreQuartoRequest $request, Quarto $quarto): RedirectResponse
    {
        $quarto->update($request->validated());

        return redirect()->route('quartos.index')->with('sucesso', 'Quarto atualizado com sucesso.');
    }

    public function limpar(Quarto $quarto): RedirectResponse
    {
        $this->authorize('limpar', $quarto);

        $this->hospedagemService->marcarQuartoLimpo($quarto);

        return back()->with('sucesso', "Quarto \"{$quarto->numero}\" marcado como limpo.");
    }

    /**
     * Visão geral: o que tem hoje em cada quarto (itens de comodato).
     */
    public function estoqueGeral(): View
    {
        $this->authorize('viewAny', Quarto::class);

        $quartos = $this->quartoEstoqueService->visaoGeral();

        return view('quartos.estoque-geral', compact('quartos'));
    }

    /**
     * Detalhe de um quarto: itens emprestados agora + form de emprestar +
     * histórico de movimentações (comodato/devolução) daquele produto.
     */
    public function estoque(Quarto $quarto): View
    {
        $this->authorize('view', $quarto);

        $itens = $this->quartoEstoqueService->itensDoQuarto($quarto);
        $produtos = Produto::ativos()->orderBy('nome')->get();

        $historico = \App\Models\MovimentacaoEstoque::whereIn('tipo', ['comodato', 'devolucao_comodato'])
            ->where('referencia_type', Quarto::class)
            ->where('referencia_id', $quarto->id)
            ->with(['produto', 'usuario'])
            ->latest()
            ->limit(30)
            ->get();

        return view('quartos.estoque', compact('quarto', 'itens', 'produtos', 'historico'));
    }

    public function emprestar(Request $request, Quarto $quarto): RedirectResponse
    {
        $this->authorize('comodato', $quarto);

        $dados = $request->validate([
            'produto_id' => ['required', Rule::exists('produtos', 'id')->where('empresa_id', $quarto->empresa_id)],
            'quantidade' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        try {
            $this->quartoEstoqueService->emprestar($quarto, Produto::findOrFail($dados['produto_id']), (int) $dados['quantidade'], $request->user());
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Item emprestado ao quarto.');
    }

    public function devolver(Request $request, Quarto $quarto, Produto $produto): RedirectResponse
    {
        $this->authorize('comodato', $quarto);

        $dados = $request->validate([
            'quantidade' => ['required', 'integer', 'min:1', 'max:9999'],
        ]);

        try {
            $this->quartoEstoqueService->devolver($quarto, $produto, (int) $dados['quantidade'], $request->user());
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Item devolvido ao estoque geral.');
    }
}
