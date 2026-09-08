<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quarto\StoreQuartoRequest;
use App\Models\Quarto;
use App\Models\Unidade;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class QuartoController extends Controller
{
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
}
