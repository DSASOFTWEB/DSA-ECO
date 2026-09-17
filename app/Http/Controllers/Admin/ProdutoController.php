<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\IntegrationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Produto\StoreProdutoRequest;
use App\Http\Requests\Produto\UpdateProdutoRequest;
use App\Models\CategoriaProduto;
use App\Models\Empresa;
use App\Models\Ncm;
use App\Models\Produto;
use App\Services\EstoqueService;
use App\Services\Fiscal\NcmSiscomexService;
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
        $totalNcms = Ncm::count();

        $produtoEditando = null;
        $editarId = (int) request('editar', 0);
        if ($editarId > 0) {
            $produtoEditando = Produto::query()->find($editarId);
            if ($produtoEditando) {
                $this->authorize('update', $produtoEditando);
            }
        }

        return view('produtos.index', compact('produtos', 'categorias', 'empresa', 'totalNcms', 'produtoEditando'));
    }

    public function create(): RedirectResponse
    {
        $this->authorize('create', Produto::class);

        return redirect()->route('produtos.index')->with('abrir_incluir', true);
    }

    public function store(StoreProdutoRequest $request): RedirectResponse
    {
        $produto = Produto::create($request->validated());

        if ($request->input('return_to') === 'index') {
            return redirect()->route('produtos.index')->with('sucesso', 'Produto cadastrado com sucesso.');
        }

        return redirect()->route('produtos.index')->with('sucesso', 'Produto cadastrado com sucesso.');
    }

    public function show(Produto $produto): View
    {
        $this->authorize('view', $produto);

        $produto->load(['categoria', 'movimentacoesEstoque' => fn ($q) => $q->latest()->limit(30)]);

        return view('produtos.show', compact('produto'));
    }

    public function edit(Produto $produto): RedirectResponse
    {
        $this->authorize('update', $produto);

        return redirect()->route('produtos.index', ['editar' => $produto->id]);
    }

    public function update(UpdateProdutoRequest $request, Produto $produto): RedirectResponse
    {
        $produto->update($request->validated());

        if ($request->input('return_to') === 'index') {
            return redirect()->route('produtos.index')->with('sucesso', 'Produto atualizado com sucesso.');
        }

        return redirect()->route('produtos.index')->with('sucesso', 'Produto atualizado com sucesso.');
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

    public function sincronizarNcm(NcmSiscomexService $service): RedirectResponse
    {
        $this->authorize('create', Produto::class);

        try {
            $resultado = $service->sincronizarApi(apenasNovos: true);
            $msg = sprintf(
                'NCM atualizado. API: %s | Inseridos: %s | Já existentes: %s',
                number_format($resultado['total_api'], 0, ',', '.'),
                number_format($resultado['inseridos'], 0, ',', '.'),
                number_format($resultado['ignorados'], 0, ',', '.')
            );

            return redirect()->route('produtos.index')->with('sucesso', $msg);
        } catch (IntegrationException $e) {
            return redirect()->route('produtos.index')->with('erro', $e->getMessage());
        }
    }

    public function autocompleteNcm(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Produto::class);

        $busca = trim((string) $request->query('busca', ''));
        if (mb_strlen($busca) < 2) {
            return response()->json([]);
        }

        $digits = preg_replace('/\D/', '', $busca) ?: '';

        $query = Ncm::query()
            ->whereRaw('CHAR_LENGTH(ncm) = 8')
            ->orderBy('ncm')
            ->limit(25);

        $query->where(function ($q) use ($busca, $digits) {
            if ($digits !== '') {
                $q->where('ncm', 'like', $digits.'%');
            }
            $q->orWhere('descricao', 'like', '%'.$busca.'%');
        });

        return response()->json(
            $query->get(['ncm', 'descricao'])->map(fn (Ncm $row) => [
                'ncm' => $row->ncm,
                'descricao' => $row->descricao,
                'text' => $row->ncm.' — '.$row->descricao,
            ])->values()
        );
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
