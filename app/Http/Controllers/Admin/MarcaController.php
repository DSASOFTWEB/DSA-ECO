<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marca\StoreMarcaRequest;
use App\Models\Marca;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

class MarcaController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Marca::class);

        $marcas = Marca::withCount('produtos')->orderBy('nome')->paginate(20);

        return view('marcas.index', compact('marcas'));
    }

    public function create(): View
    {
        $this->authorize('create', Marca::class);

        return view('marcas.create');
    }

    public function store(StoreMarcaRequest $request): RedirectResponse|JsonResponse
    {
        $marca = Marca::create([
            ...$request->validated(),
            'empresa_id' => $request->user()->empresa_id,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['id' => $marca->id, 'nome' => $marca->nome], Response::HTTP_CREATED);
        }

        return redirect()->route('marcas.index')->with('sucesso', "Marca \"{$marca->nome}\" criada com sucesso.");
    }

    public function edit(Marca $marca): View
    {
        $this->authorize('update', $marca);

        return view('marcas.edit', compact('marca'));
    }

    public function update(StoreMarcaRequest $request, Marca $marca): RedirectResponse
    {
        $marca->update($request->validated());

        return redirect()->route('marcas.index')->with('sucesso', 'Marca atualizada com sucesso.');
    }

    public function destroy(Marca $marca): RedirectResponse
    {
        $this->authorize('delete', $marca);

        if ($marca->produtos()->exists()) {
            return back()->with('erro', 'Essa marca tem produtos vinculados — mude-os de marca antes de excluir.');
        }

        $marca->delete();

        return redirect()->route('marcas.index')->with('sucesso', 'Marca excluída com sucesso.');
    }
}
