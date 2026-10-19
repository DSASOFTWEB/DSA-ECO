<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Terminal\StoreTerminalRequest;
use App\Models\Empresa;
use App\Models\Terminal;
use App\Models\Unidade;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class TerminalController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Terminal::class);

        $terminais = Terminal::with('unidade')
            ->withCount(['caixas as caixas_abertos_count' => fn ($q) => $q->where('status', 'aberto')])
            ->orderBy('unidade_id')->orderBy('nome')
            ->paginate(15);

        $empresaSerie = Empresa::findOrFail(request()->user()->empresa_id);

        return view('terminais.index', compact('terminais', 'empresaSerie'));
    }

    public function create(): View
    {
        $this->authorize('create', Terminal::class);

        $unidades = Unidade::ativas()->orderBy('nome')->get();

        $seriePadraoEmpresa = $this->seriePadraoEmpresa();

        return view('terminais.create', compact('unidades', 'seriePadraoEmpresa'));
    }

    public function store(StoreTerminalRequest $request): RedirectResponse
    {
        $terminal = Terminal::create($request->validated());

        return redirect()->route('terminais.index')->with('sucesso', "Terminal \"{$terminal->nome}\" criado com sucesso.");
    }

    public function edit(Terminal $terminal): View
    {
        $this->authorize('update', $terminal);

        $unidades = Unidade::ativas()->orderBy('nome')->get();

        $seriePadraoEmpresa = $this->seriePadraoEmpresa();

        return view('terminais.edit', compact('terminal', 'unidades', 'seriePadraoEmpresa'));
    }

    public function update(StoreTerminalRequest $request, Terminal $terminal): RedirectResponse
    {
        $terminal->update($request->validated());

        return redirect()->route('terminais.index')->with('sucesso', 'Terminal atualizado com sucesso.');
    }

    /** Série de NFC-e usada pelos terminais sem série própria. */
    protected function seriePadraoEmpresa(): int
    {
        return (int) (Empresa::find(request()->user()->empresa_id)?->numero_serie_nfce ?: 1);
    }
}
