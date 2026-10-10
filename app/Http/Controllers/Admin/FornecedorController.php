<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fornecedor\StoreFornecedorRequest;
use App\Models\Fornecedor;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class FornecedorController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Fornecedor::class);

        $fornecedores = Fornecedor::withCount('produtos')->orderBy('nome')->paginate(20);

        return view('fornecedores.index', compact('fornecedores'));
    }

    public function create(): View
    {
        $this->authorize('create', Fornecedor::class);

        return view('fornecedores.create');
    }

    public function store(StoreFornecedorRequest $request): RedirectResponse|JsonResponse
    {
        $fornecedor = Fornecedor::create([
            ...$request->validated(),
            'empresa_id' => $request->user()->empresa_id,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['id' => $fornecedor->id, 'nome' => $fornecedor->nome], Response::HTTP_CREATED);
        }

        return redirect()->route('fornecedores.index')->with('sucesso', "Fornecedor \"{$fornecedor->nome}\" criado com sucesso.");
    }

    public function edit(Fornecedor $fornecedor): View
    {
        $this->authorize('update', $fornecedor);

        return view('fornecedores.edit', compact('fornecedor'));
    }

    public function update(StoreFornecedorRequest $request, Fornecedor $fornecedor): RedirectResponse
    {
        $fornecedor->update($request->validated());

        return redirect()->route('fornecedores.index')->with('sucesso', 'Fornecedor atualizado com sucesso.');
    }

    public function destroy(Fornecedor $fornecedor): RedirectResponse
    {
        $this->authorize('delete', $fornecedor);

        if ($fornecedor->produtos()->exists()) {
            return back()->with('erro', 'Esse fornecedor tem produtos vinculados — mude-os de fornecedor antes de excluir.');
        }

        $fornecedor->delete();

        return redirect()->route('fornecedores.index')->with('sucesso', 'Fornecedor excluído com sucesso.');
    }
}
