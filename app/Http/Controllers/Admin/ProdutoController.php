<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\IntegrationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Produto\StoreProdutoRequest;
use App\Http\Requests\Produto\UpdateProdutoRequest;
use App\Models\CategoriaProduto;
use App\Models\Empresa;
use App\Models\Produto;
use App\Services\EstoqueService;
use App\Services\Integrations\CosmosProdutoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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
            ->when(request('tipo_item'), fn ($q, $v) => $q->where('tipo_item', $v))
            ->orderBy('nome')
            ->paginate(20)
            ->withQueryString();

        $categorias = CategoriaProduto::orderBy('nome')->get();
        $empresa = Empresa::find(auth()->user()->empresa_id);

        return view('produtos.index', compact('produtos', 'categorias', 'empresa'));
    }

    public function create(): View
    {
        $this->authorize('create', Produto::class);

        $categorias = CategoriaProduto::orderBy('nome')->get();
        $empresa = Empresa::find(auth()->user()->empresa_id);

        return view('produtos.create', compact('categorias', 'empresa'));
    }

    public function store(StoreProdutoRequest $request): RedirectResponse
    {
        $produto = Produto::create($request->validated());

        if ($request->input('return_to') === 'index') {
            return redirect()->route('produtos.index')->with('sucesso', 'Produto cadastrado com sucesso.');
        }

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
        $empresa = Empresa::find(auth()->user()->empresa_id);

        return view('produtos.edit', compact('produto', 'categorias', 'empresa'));
    }

    public function update(UpdateProdutoRequest $request, Produto $produto): RedirectResponse
    {
        $produto->update($request->validated());

        return redirect()->route('produtos.show', $produto)->with('sucesso', 'Produto atualizado com sucesso.');
    }

    public function consultarEan(Request $request, CosmosProdutoService $cosmos): JsonResponse
    {
        $this->authorize('create', Produto::class);

        $request->validate([
            'ean' => ['required', 'string', 'max:14'],
        ]);

        try {
            $empresa = Empresa::find($request->user()->empresa_id);

            return response()->json($cosmos->consultarPorEan((string) $request->query('ean'), $empresa));
        } catch (IntegrationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
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
