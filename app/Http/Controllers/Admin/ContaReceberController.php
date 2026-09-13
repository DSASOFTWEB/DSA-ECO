<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use App\Models\ContaReceber;
use App\Models\User;
use App\Services\CaixaService;
use App\Services\FinanceiroGestaoService;
use App\Support\Financeiro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'forma_pagamento' => ['required', Rule::in(array_keys(Financeiro::FORMAS_PAGAMENTO))],
            'caixa_id' => ['nullable', 'integer'],
        ]);

        [$caixa, $caixasDisponiveis] = $this->resolverCaixaDaConta($contaReceber->unidade_id, $request->user(), $dados['caixa_id'] ?? null);

        if ($caixasDisponiveis->count() > 1 && ! $caixa) {
            return back()->with('erro', 'Selecione em qual caixa esta entrada deve ser lançada — há mais de um caixa aberto.');
        }

        try {
            $this->financeiroGestaoService->marcarContaReceberRecebida($contaReceber, $request->user(), $dados['forma_pagamento'], $caixa);
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        $mensagem = 'Conta marcada como recebida.'.($caixa ? " Lançada no caixa {$caixa->terminal?->nome}." : ' Não havia caixa aberto — apenas a baixa foi registrada.');

        return back()->with('sucesso', $mensagem);
    }

    /**
     * Mesma lógica de ContaPagarController::resolverCaixaDaConta.
     */
    protected function resolverCaixaDaConta(?int $unidadeIdDaConta, User $user, null|string|int $caixaIdEscolhido): array
    {
        $unidadeId = $unidadeIdDaConta ?? $user->unidade_id;

        $caixasDisponiveis = $unidadeId
            ? $this->caixaService->caixasAbertosDaUnidade($unidadeId)
            : $this->caixaService->caixasAbertosDaEmpresa($user->empresa_id);

        if ($caixasDisponiveis->count() <= 1) {
            return [$caixasDisponiveis->first(), $caixasDisponiveis];
        }

        $caixaEscolhido = $caixaIdEscolhido ? $caixasDisponiveis->firstWhere('id', (int) $caixaIdEscolhido) : null;

        return [$caixaEscolhido, $caixasDisponiveis];
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
