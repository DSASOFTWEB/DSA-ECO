<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Contrato\CancelarContratoRequest;
use App\Http\Requests\Contrato\StoreContratoRequest;
use App\Http\Requests\Contrato\UpdateContratoRequest;
use App\Models\Cliente;
use App\Models\Contrato;
use App\Models\Plano;
use App\Models\Unidade;
use App\Services\ContratoService;
use App\Services\Relatorios\ContratoPdfExport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class ContratoController extends Controller
{
    public function __construct(protected ContratoService $contratoService) {}

    public function index(): View
    {
        $this->authorize('viewAny', Contrato::class);

        $contratos = $this->contratoService->listar(request()->only(['status', 'unidade_id', 'cliente_id']));

        return view('contratos.index', compact('contratos'));
    }

    public function create(): View
    {
        $this->authorize('create', Contrato::class);

        $unidades = Unidade::ativas()->get();
        $planos = Plano::ativos()->get();
        $clienteId = request('cliente_id') ?? old('cliente_id');
        $clientePreSelecionado = $clienteId ? Cliente::find($clienteId) : null;

        return view('contratos.create', compact('unidades', 'planos', 'clientePreSelecionado'));
    }

    public function store(StoreContratoRequest $request): RedirectResponse
    {
        try {
            $contrato = $this->contratoService->contratar($request->validated());
        } catch (NegocioException $e) {
            return back()->withInput()->with('erro', $e->getMessage());
        }

        return redirect()->route('contratos.show', $contrato)->with('sucesso', 'Contrato criado com sucesso.')->with('contrato_recem_criado', $contrato->id);
    }

    public function show(Contrato $contrato): View
    {
        $this->authorize('view', $contrato);

        $contrato->load(['cliente', 'plano', 'unidade', 'vendedor', 'dependentes', 'mensalidades' => fn ($q) => $q->latest('competencia')]);

        return view('contratos.show', compact('contrato'));
    }

    public function edit(Contrato $contrato): View
    {
        $this->authorize('update', $contrato);

        $planos = Plano::ativos()->orderBy('nome')->get();

        return view('contratos.edit', compact('contrato', 'planos'));
    }

    public function update(UpdateContratoRequest $request, Contrato $contrato): RedirectResponse
    {
        try {
            $contrato = $this->contratoService->atualizar($contrato, $request->validated());
        } catch (NegocioException $e) {
            return back()->withInput()->with('erro', $e->getMessage());
        }

        return redirect()->route('contratos.show', $contrato)->with('sucesso', 'Plano e vencimento atualizados. Mensalidades abertas foram recalculadas.');
    }

    public function pdf(Contrato $contrato, ContratoPdfExport $export): Response
    {
        $this->authorize('view', $contrato);

        return $export->gerar($contrato)->stream("contrato-{$contrato->numero_contrato}.pdf");
    }

    public function cancelar(CancelarContratoRequest $request, Contrato $contrato): RedirectResponse
    {
        try {
            $this->contratoService->cancelar($contrato, $request->validated()['motivo_cancelamento']);
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return redirect()->route('contratos.show', $contrato)->with('sucesso', 'Contrato cancelado.');
    }

    public function reativar(Contrato $contrato): RedirectResponse
    {
        $this->authorize('reativar', $contrato);

        try {
            $this->contratoService->reativar($contrato);
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return redirect()->route('contratos.show', $contrato)->with('sucesso', 'Contrato reativado com sucesso.');
    }
}
