<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TipoEntrada\StoreTipoEntradaRequest;
use App\Http\Requests\TipoEntrada\UpdateTipoEntradaRequest;
use App\Models\TipoEntrada;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class TipoEntradaController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', TipoEntrada::class);

        $tiposEntrada = TipoEntrada::withCount('itensVenda')->orderBy('nome')->paginate(15);

        return view('tipos_entrada.index', compact('tiposEntrada'));
    }

    public function create(): View
    {
        $this->authorize('create', TipoEntrada::class);

        return view('tipos_entrada.create');
    }

    public function store(StoreTipoEntradaRequest $request): RedirectResponse
    {
        $tipoEntrada = TipoEntrada::create($request->validated());

        return redirect()->route('tipos-entrada.index')->with('sucesso', "Tipo de entrada \"{$tipoEntrada->nome}\" criado com sucesso.");
    }

    public function edit(TipoEntrada $tipoEntrada): View
    {
        $this->authorize('update', $tipoEntrada);

        return view('tipos_entrada.edit', compact('tipoEntrada'));
    }

    public function update(UpdateTipoEntradaRequest $request, TipoEntrada $tipoEntrada): RedirectResponse
    {
        $tipoEntrada->update($request->validated());

        return redirect()->route('tipos-entrada.index')->with('sucesso', 'Tipo de entrada atualizado com sucesso.');
    }

    public function destroy(TipoEntrada $tipoEntrada): RedirectResponse
    {
        $this->authorize('delete', $tipoEntrada);

        if ($tipoEntrada->itensVenda()->exists()) {
            return back()->with('erro', 'Não é possível excluir um tipo de entrada com vendas vinculadas. Desative-o em vez disso.');
        }

        $tipoEntrada->delete();

        return redirect()->route('tipos-entrada.index')->with('sucesso', 'Tipo de entrada removido.');
    }
}
