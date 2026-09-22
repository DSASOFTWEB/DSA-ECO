<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cliente\StoreClienteRequest;
use App\Http\Requests\Cliente\UpdateClienteRequest;
use App\Models\Cliente;
use App\Models\Unidade;
use App\Services\AcessoService;
use App\Services\ClienteService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    public function __construct(
        protected ClienteService $clienteService,
        protected AcessoService $acessoService,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Cliente::class);

        $clientes = $this->clienteService->listar(request()->only(['nome', 'cpf', 'status', 'unidade_id']));

        return view('clientes.index', compact('clientes'));
    }

    /**
     * Busca rápida (CPF/nome/código) usada pelo campo de cliente do PDV.
     */
    public function buscar(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Cliente::class);

        $clientes = $this->acessoService->pesquisarClientes((string) $request->query('termo', ''), $request->user()->empresa_id);

        return response()->json($clientes->map(fn (Cliente $cliente) => [
            'id' => $cliente->id,
            'nome' => $cliente->nome,
            'cpf' => $cliente->cpf,
        ]));
    }

    public function create(): View
    {
        $this->authorize('create', Cliente::class);

        $unidades = Unidade::ativas()->get();

        return view('clientes.create', compact('unidades'));
    }

    public function store(StoreClienteRequest $request): JsonResponse|RedirectResponse
    {
        $cliente = $this->clienteService->criar($request->safe()->except('foto'), $request->file('foto'));

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $cliente->id,
                'nome' => $cliente->nome,
                'cpf' => $cliente->cpf,
            ], 201);
        }

        return redirect()->route('clientes.show', $cliente)->with('sucesso', 'Cliente cadastrado com sucesso.');
    }

    public function show(Cliente $cliente): View
    {
        $this->authorize('view', $cliente);

        $cliente->load([
            'dependentes.carteirinha',
            'contratos' => fn ($q) => $q->latest('data_inicio'),
            'contratos.plano',
            'contratos.mensalidades' => fn ($q) => $q->orderByDesc('competencia')->orderByDesc('data_vencimento'),
            'carteirinha',
            'unidade',
            'contratoAtivo.plano',
        ]);

        $historicoPagamentos = $cliente->contratos
            ->flatMap(fn ($contrato) => $contrato->mensalidades->map(function ($mensalidade) use ($contrato) {
                $mensalidade->setRelation('contrato', $contrato);

                return $mensalidade;
            }))
            ->sortByDesc(fn ($m) => $m->competencia?->format('Y-m-d').'|'.$m->data_vencimento?->format('Y-m-d'))
            ->values();

        return view('clientes.show', compact('cliente', 'historicoPagamentos'));
    }

    public function edit(Cliente $cliente): View
    {
        $this->authorize('update', $cliente);

        $unidades = Unidade::ativas()->get();

        return view('clientes.edit', compact('cliente', 'unidades'));
    }

    public function update(UpdateClienteRequest $request, Cliente $cliente): RedirectResponse
    {
        $this->clienteService->atualizar($cliente, $request->safe()->except('foto'), $request->file('foto'));

        return redirect()->route('clientes.show', $cliente)->with('sucesso', 'Cliente atualizado com sucesso.');
    }

    public function destroy(Cliente $cliente): RedirectResponse
    {
        $this->authorize('delete', $cliente);

        $this->clienteService->inativar($cliente);

        return redirect()->route('clientes.index')->with('sucesso', 'Cliente inativado com sucesso.');
    }
}
