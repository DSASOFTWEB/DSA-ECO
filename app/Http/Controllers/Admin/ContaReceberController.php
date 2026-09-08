<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use App\Models\ContaReceber;
use App\Services\CaixaService;
use App\Services\FinanceiroGestaoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContaReceberController extends Controller
{
    public function __construct(
        protected FinanceiroGestaoService $financeiroGestaoService,
        protected CaixaService $caixaService,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ContaReceber::class);

        $dados = $request->validate([
            'pagador' => ['required', 'string', 'max:150'],
            'cliente_id' => ['nullable', \Illuminate\Validation\Rule::exists('clientes', 'id')->where('empresa_id', $request->user()->empresa_id)],
            'descricao' => ['required', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:60'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'data_vencimento' => ['required', 'date'],
            'unidade_id' => ['nullable', \Illuminate\Validation\Rule::exists('unidades', 'id')->where('empresa_id', $request->user()->empresa_id)],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->financeiroGestaoService->criarContaReceber($dados, $request->user());

        return back()->with('sucesso', 'Conta a receber cadastrada.');
    }

    public function update(Request $request, ContaReceber $contaReceber): RedirectResponse
    {
        $this->authorize('update', $contaReceber);

        if ($contaReceber->status !== 'pendente' && $contaReceber->status !== 'atrasado') {
            return back()->with('erro', 'Só é possível editar contas ainda em aberto.');
        }

        $dados = $request->validate([
            'pagador' => ['required', 'string', 'max:150'],
            'cliente_id' => ['nullable', \Illuminate\Validation\Rule::exists('clientes', 'id')->where('empresa_id', $request->user()->empresa_id)],
            'descricao' => ['required', 'string', 'max:255'],
            'categoria' => ['nullable', 'string', 'max:60'],
            'valor' => ['required', 'numeric', 'min:0.01'],
            'data_vencimento' => ['required', 'date'],
            'unidade_id' => ['nullable', \Illuminate\Validation\Rule::exists('unidades', 'id')->where('empresa_id', $request->user()->empresa_id)],
            'observacoes' => ['nullable', 'string', 'max:1000'],
        ]);

        $contaReceber->update($dados);

        return back()->with('sucesso', 'Conta a receber atualizada.');
    }

    public function receber(Request $request, ContaReceber $contaReceber): RedirectResponse
    {
        $this->authorize('update', $contaReceber);

        $dados = $request->validate([
            'forma_pagamento' => ['required', 'string', 'max:30'],
        ]);

        $caixa = $this->caixaService->caixaAbertoDaUnidade($contaReceber->unidade);

        try {
            $this->financeiroGestaoService->marcarContaReceberRecebida($contaReceber, $request->user(), $dados['forma_pagamento'], $caixa);
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        $mensagem = 'Conta marcada como recebida.'.($caixa ? ' Lançada no caixa aberto da unidade.' : ' Não havia caixa aberto na unidade — apenas a baixa foi registrada.');

        return back()->with('sucesso', $mensagem);
    }

    public function destroy(ContaReceber $contaReceber): RedirectResponse
    {
        $this->authorize('delete', $contaReceber);

        try {
            $this->financeiroGestaoService->cancelarContaReceber($contaReceber);
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        return back()->with('sucesso', 'Conta a receber cancelada.');
    }
}
