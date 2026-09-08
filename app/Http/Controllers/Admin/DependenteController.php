<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Dependente;
use App\Services\ClienteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DependenteController extends Controller
{
    public function __construct(protected ClienteService $clienteService) {}

    public function store(Request $request, Cliente $cliente): RedirectResponse
    {
        $this->authorize('create', Dependente::class);

        $dados = $request->validate([
            'nome' => ['required', 'string', 'max:255'],
            'cpf' => ['nullable', 'string', 'max:14'],
            'data_nascimento' => ['required', 'date'],
            'parentesco' => ['nullable', 'string', 'max:40'],
        ]);

        $this->clienteService->adicionarDependente($cliente, $dados);

        return redirect()->route('clientes.show', $cliente)->with('sucesso', 'Dependente adicionado com sucesso.');
    }

    public function destroy(Cliente $cliente, Dependente $dependente): RedirectResponse
    {
        $this->authorize('delete', $dependente);

        $dependente->delete();

        return redirect()->route('clientes.show', $cliente)->with('sucesso', 'Dependente removido.');
    }
}
