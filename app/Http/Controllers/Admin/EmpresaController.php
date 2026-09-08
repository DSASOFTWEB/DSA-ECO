<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Empresa\UpdateEmpresaRequest;
use App\Models\Empresa;
use App\Services\EmpresaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Dados cadastrais da própria empresa (nome, CNPJ, endereço e logo) — não é
 * um CRUD de várias empresas (isso é operação SaaS interna da DSA), é a
 * tela onde CADA empresa cliente edita os próprios dados. Por isso não há
 * index/create/destroy: sempre edita a empresa do usuário logado.
 */
class EmpresaController extends Controller
{
    public function __construct(protected EmpresaService $empresaService) {}

    public function edit(): View
    {
        Gate::authorize('empresa.gerenciar');

        $empresa = Empresa::findOrFail(request()->user()->empresa_id);

        return view('empresa.edit', compact('empresa'));
    }

    public function update(UpdateEmpresaRequest $request): RedirectResponse
    {
        $empresa = Empresa::findOrFail($request->user()->empresa_id);

        $this->empresaService->atualizar($empresa, $request->safe()->except('logo'), $request->file('logo'));

        return redirect()->route('empresa.edit')->with('sucesso', 'Dados da empresa atualizados com sucesso.');
    }
}
