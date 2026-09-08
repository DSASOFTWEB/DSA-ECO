<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Produto\StoreProdutoRequest;
use App\Http\Requests\Produto\UpdateProdutoRequest;
use App\Models\CategoriaProduto;
use App\Models\Produto;
use App\Services\EstoqueService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProdutoController extends Controller
{
    public function __construct(protected EstoqueService $estoqueService) {}

    public function index(): View
    {
        $this->authorize('viewAny', Produto::class);

        $produtos = Produto::with('categoria')
            // 'like' no MySQL já é case-insensitive com a collation utf8mb4_unicode_ci
            // (configurada em config/database.php / .env.example) — sem precisar de 'ilike'.
            ->when(request('nome'), fn ($q, $v) => $q->where('nome', 'like', "%{$v}%"))
            ->when(request('categoria_id'), fn ($q, $v) => $q->where('categoria_id', $v))
            ->orderBy('nome')
            ->paginate(20)
            ->withQueryString();

        return view('produtos.index', compact('produtos'));
    }

    public function create(): View
    {
        $this->authorize('create', Produto::class);

        $categorias = CategoriaProduto::orderBy('nome')->get();

        return view('produtos.create', compact('categorias'));
    }

    public function store(StoreProdutoRequest $request): RedirectResponse
    {
        $produto = Produto::create($request->validated());

        return redirect()->route('produtos.show', $produto)->with('sucesso', 'Produto cadastrado com sucesso.');
    }

    public function show(Produto $produto): View
    {
        $this->authorize('view', $produto);

        $produto->load(['categoria', 'movimentacoesEstoque' => fn ($q) => $q->latest()->limit(30)]);

        return view('produtos.show', compact('produto'));
    }

    public function edit(Produto $produto): View
    {
        $this->authorize('update', $produto);

        $categorias = CategoriaProduto::orderBy('nome')->get();

        return view('produtos.edit', compact('produto', 'categorias'));
    }

    public function update(UpdateProdutoRequest $request, Produto $produto): RedirectResponse
    {
        $produto->update($request->validated());

        return redirect()->route('produtos.show', $produto)->with('sucesso', 'Produto atualizado com sucesso.');
    }

    public function ajustarEstoque(Request $request, Produto $produto): RedirectResponse
    {
        $this->authorize('ajustarEstoque', $produto);

        $dados = $request->validate([
            'quantidade' => ['required', 'integer', 'min:0'],
            'motivo' => ['required', 'string', 'max:255'],
        ]);

        $this->estoqueService->ajuste($produto, $dados['quantidade'], $dados['motivo'], $request->user()->id);

        return back()->with('sucesso', 'Estoque ajustado com sucesso.');
    }

    public function destroy(Produto $produto): RedirectResponse
    {
        $this->authorize('delete', $produto);

        $produto->delete();

        return redirect()->route('produtos.index')->with('sucesso', 'Produto removido.');
    }
}
