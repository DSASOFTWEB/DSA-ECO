<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use App\Models\ContaPagar;
use App\Services\CaixaService;
use App\Services\FinanceiroGestaoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContaPagarController extends Controller
{
    public function __construct(
        protected FinanceiroGestaoService $financeiroGestaoService,
        protected CaixaService $caixaService,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ContaPagar::class);

        $dados = $request->validate([
            'fornecedor' => ['required', 'string', 'max:150'],
            'descricao' => ['required', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:60'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'data_vencimento' => ['required', 'date'],
            'unidade_id' => ['nullable', \Illuminate\Validation\Rule::exists('unidades', 'id')->where('empresa_id', $request->user()->empresa_id)],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->financeiroGestaoService->criarContaPagar($dados, $request->user());

        return back()->with('sucesso', 'Conta a pagar cadastrada.');
    }

    public function update(Request $request, ContaPagar $contaPagar): RedirectResponse
    {
        $this->authorize('update', $contaPagar);

        if ($contaPagar->status !== 'pendente' && $contaPagar->status !== 'atrasado') {
            return back()->with('erro', 'Só é possível editar contas ainda em aberto.');
        }

        $dados = $request->validate([
            'fornecedor' => ['required', 'string', 'max:150'],
            'descricao' => ['required', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:60'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'data_vencimento' => ['required', 'date'],
            'unidade_id' => ['nullable', \Illuminate\Validation\Rule::exists('unidades', 'id')->where('empresa_id', $request->user()->empresa_id)],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ]);

        $contaPagar->update($dados);

        return back()->with('sucesso', 'Conta a pagar atualizada.');
    }

    public function pagar(Request $request, ContaPagar $contaPagar): RedirectResponse
    {
        $this->authorize('update', $contaPagar);

        $dados = $request->validate([
            'forma_pagamento' => ['required', 'string', 'max:30'],
        ]);

        $caixa = $this->caixaService->caixaAbertoDaUnidade($contaPagar->unidade);

        try {
            $this->financeiroGestaoService->marcarContaPagarPaga($contaPagar, $request->user(), $dados['forma_pagamento'], $caixa);
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        $mensagem = 'Conta marcada como paga.'.($caixa ? ' Lançada no caixa aberto da unidade.' : ' Não havia caixa aberto na unidade — apenas a baixa foi registrada.');

        return back()->with('sucesso', $mensagem);
    }

    public function destroy(ContaPagar $contaPagar): RedirectResponse
    {
        $this->authorize('delete', $contaPagar);

        try {
            $this->financeiroGestaoService->cancelarContaPagar($contaPagar);
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Conta a pagar cancelada.');
    }
}
