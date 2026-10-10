<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoriaProduto\StoreCategoriaProdutoRequest;
use App\Models\CategoriaProduto;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class CategoriaProdutoController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', CategoriaProduto::class);

        $categorias = CategoriaProduto::with('categoriaPai', 'subcategorias')
            ->withCount('produtos')
            ->orderBy('nome')
            ->paginate(20);

        return view('categorias-produtos.index', compact('categorias'));
    }

    public function create(): View
    {
        $this->authorize('create', CategoriaProduto::class);

        $categoriasRaizes = CategoriaProduto::raizes()->orderBy('nome')->get();

        return view('categorias-produtos.create', compact('categoriasRaizes'));
    }

    /**
     * Também serve o "criar rápido" do formulário de produto: quando a
     * requisição pede JSON (fetch do modal), responde com {id, nome} em
     * vez de redirecionar — mesmo método, mesma validação/autorização.
     */
    public function store(StoreCategoriaProdutoRequest $request): RedirectResponse|JsonResponse
    {
        $categoria = CategoriaProduto::create([
            ...$request->validated(),
            'empresa_id' => $request->user()->empresa_id,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['id' => $categoria->id, 'nome' => $categoria->nome], Response::HTTP_CREATED);
        }

        return redirect()->route('categorias-produtos.index')->with('sucesso', "Categoria \"{$categoria->nome}\" criada com sucesso.");
    }

    public function edit(CategoriaProduto $categoriaProduto): View
    {
        $this->authorize('update', $categoriaProduto);

        $categoriasRaizes = CategoriaProduto::raizes()->where('id', '!=', $categoriaProduto->id)->orderBy('nome')->get();

        return view('categorias-produtos.edit', ['categoria' => $categoriaProduto, 'categoriasRaizes' => $categoriasRaizes]);
    }

    public function update(StoreCategoriaProdutoRequest $request, CategoriaProduto $categoriaProduto): RedirectResponse
    {
        $categoriaProduto->update($request->validated());

        return redirect()->route('categorias-produtos.index')->with('sucesso', 'Categoria atualizada com sucesso.');
    }

    public function destroy(CategoriaProduto $categoriaProduto): RedirectResponse
    {
        $this->authorize('delete', $categoriaProduto);

        if ($categoriaProduto->produtos()->exists() || $categoriaProduto->subcategorias()->exists()) {
            return back()->with('erro', 'Essa categoria tem produtos ou subcategorias vinculadas — mude-os de categoria antes de excluir.');
        }

        $categoriaProduto->delete();

        return redirect()->route('categorias-produtos.index')->with('sucesso', 'Categoria excluída com sucesso.');
    }
}
