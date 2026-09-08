<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Plano\StorePlanoRequest;
use App\Http\Requests\Plano\UpdatePlanoRequest;
use App\Models\Plano;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PlanoController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Plano::class);

        $planos = Plano::withCount('contratos')->orderBy('nome')->paginate(15);

        return view('planos.index', compact('planos'));
    }

    public function create(): View
    {
        $this->authorize('create', Plano::class);

        return view('planos.create');
    }

    public function store(StorePlanoRequest $request): RedirectResponse
    {
        $plano = Plano::create($request->validated());

        return redirect()->route('planos.index')->with('sucesso', "Plano \"{$plano->nome}\" criado com sucesso.");
    }

    public function edit(Plano $plano): View
    {
        $this->authorize('update', $plano);

        return view('planos.edit', compact('plano'));
    }

    public function update(UpdatePlanoRequest $request, Plano $plano): RedirectResponse
    {
        $plano->update($request->validated());

        return redirect()->route('planos.index')->with('sucesso', 'Plano atualizado com sucesso.');
    }

    public function destroy(Plano $plano): RedirectResponse
    {
        $this->authorize('delete', $plano);

        if ($plano->contratos()->exists()) {
            return back()->with('erro', 'Não é possível excluir um plano com contratos vinculados. Desative-o em vez disso.');
        }

        $plano->delete();

        return redirect()->route('planos.index')->with('sucesso', 'Plano removido.');
    }
}
