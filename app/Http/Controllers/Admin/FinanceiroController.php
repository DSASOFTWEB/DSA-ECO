<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContaPagar;
use App\Models\ContaReceber;
use App\Services\FinanceiroGestaoService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * "Mini financeiro": hub único com contas a pagar e a receber que não
 * passam pelas mensalidades (contrato) nem pelas vendas (PDV/checkout) —
 * ex: aluguel, energia, salário, patrocínio, reembolso.
 */
class FinanceiroController extends Controller
{
    public function index(Request $request, FinanceiroGestaoService $financeiroGestaoService): View
    {
        abort_unless($request->user()->canAny(['contas_pagar.visualizar', 'contas_receber.visualizar']), 403);

        $resumo = $financeiroGestaoService->resumo();

        $contasPagar = ContaPagar::with('unidade')->orderByRaw("status = 'pago'")->orderBy('data_vencimento')->paginate(10, ['*'], 'pagina_pagar');
        $contasReceber = ContaReceber::with(['unidade', 'cliente'])->orderByRaw("status = 'recebido'")->orderBy('data_vencimento')->paginate(10, ['*'], 'pagina_receber');

        return view('financeiro.index', compact('resumo', 'contasPagar', 'contasReceber'));
    }
}
