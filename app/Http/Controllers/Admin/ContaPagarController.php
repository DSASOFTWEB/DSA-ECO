<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\NegocioException;
use App\Http\Controllers\Controller;
use App\Models\ContaPagar;
use App\Models\User;
use App\Services\CaixaService;
use App\Services\FinanceiroGestaoService;
use App\Support\Financeiro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'forma_pagamento' => ['required', Rule::in(array_keys(Financeiro::FORMAS_PAGAMENTO))],
            'caixa_id' => ['nullable', 'integer'],
        ]);

        [$caixa, $caixasDisponiveis] = $this->resolverCaixaDaConta($contaPagar->unidade_id, $request->user(), $dados['caixa_id'] ?? null);

        if ($caixasDisponiveis->count() > 1 && ! $caixa) {
            return back()->with('erro', 'Selecione em qual caixa esta saída deve ser lançada — há mais de um caixa aberto.');
        }

        try {
            $this->financeiroGestaoService->marcarContaPagarPaga($contaPagar, $request->user(), $dados['forma_pagamento'], $caixa);
        } catch (NegocioException $e) {
            return back()->with('erro', $e->getMessage());
        }

        $mensagem = 'Conta marcada como paga.'.($caixa ? " Lançada no caixa {$caixa->terminal?->nome}." : ' Não havia caixa aberto — apenas a baixa foi registrada.');

        return back()->with('sucesso', $mensagem);
    }

    /**
     * Caixas abertos candidatos pra lançar a baixa: prioriza a unidade da
     * PRÓPRIA conta (quando ela tem uma definida — comportamento já
     * existente), senão usa a unidade do operador logado (ou a empresa
     * toda, se ele não tiver unidade fixa) — mesma ideia de
     * VendaController::resolverCaixaOperador.
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
