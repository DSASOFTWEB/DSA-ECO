<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use App\Http\Requests\TransferenciaCaixa\StoreTransferenciaRequest;
use App\Models\Caixa;
use App\Models\TransferenciaCaixa;
use App\Services\CaixaService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class TransferenciaCaixaController extends Controller
{
    public function __construct(protected CaixaService $caixaService) {}

    public function index(): View
    {
        $this->authorize('viewAny', TransferenciaCaixa::class);

        $user = request()->user();

        $transferencias = TransferenciaCaixa::with(['caixaOrigem.terminal', 'caixaDestino.terminal', 'usuario'])
            ->latest()
            ->paginate(15);

        $caixasAbertos = $user->unidade_id
            ? $this->caixaService->caixasAbertosDaUnidade($user->unidade_id)
            : $this->caixaService->caixasAbertosDaEmpresa($user->empresa_id);

        return view('financeiro.transferencias.index', compact('transferencias', 'caixasAbertos'));
    }

    public function store(StoreTransferenciaRequest $request): RedirectResponse
    {
        $dados = $request->validated();

        try {
            $this->caixaService->transferir(
                Caixa::findOrFail($dados['caixa_origem_id']),
                Caixa::findOrFail($dados['caixa_destino_id']),
                (float) $dados['valor'],
                $request->user(),
                $dados['observacao'] ?? null,
            );
        } catch (NegocioException $e) {
            return back()->withInput()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Transferência realizada com sucesso.');
    }
}
