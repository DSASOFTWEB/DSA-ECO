<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\FormaPagamento\StoreFormaPagamentoRequest;
use App\Models\Empresa;
use App\Models\FormaPagamento;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class FormaPagamentoController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', FormaPagamento::class);

        $empresaId = (int) request()->user()->empresa_id;
        FormaPagamento::garantirPadroes($empresaId);
        $formas = FormaPagamento::orderBy('ordem')->orderBy('id')->get();
        $gatewayPixAtivo = (bool) Empresa::find($empresaId)?->gatewayPixAtivo();

        return view('formas-pagamento.index', compact('formas', 'gatewayPixAtivo'));
    }

    public function create(): View
    {
        $this->authorize('create', FormaPagamento::class);

        return view('formas-pagamento.create', ['gatewayPixAtivo' => $this->gatewayPixAtivo()]);
    }

    public function store(StoreFormaPagamentoRequest $request): RedirectResponse
    {
        $forma = FormaPagamento::create([
            ...$request->validated(),
            'empresa_id' => $request->user()->empresa_id,
        ]);

        return redirect()->route('formas-pagamento.index')->with('sucesso', "Forma de pagamento \"{$forma->nome}\" criada com sucesso.");
    }

    public function edit(FormaPagamento $formaPagamento): View
    {
        $this->authorize('update', $formaPagamento);

        return view('formas-pagamento.edit', [
            'forma' => $formaPagamento,
            'gatewayPixAtivo' => $this->gatewayPixAtivo(),
        ]);
    }

    public function update(StoreFormaPagamentoRequest $request, FormaPagamento $formaPagamento): RedirectResponse
    {
        $formaPagamento->update($request->validated());

        return redirect()->route('formas-pagamento.index')->with('sucesso', 'Forma de pagamento atualizada com sucesso.');
    }

    public function destroy(FormaPagamento $formaPagamento): RedirectResponse
    {
        $this->authorize('delete', $formaPagamento);

        $formaPagamento->delete();

        return redirect()->route('formas-pagamento.index')->with('sucesso', 'Forma de pagamento excluída com sucesso.');
    }

    protected function gatewayPixAtivo(): bool
    {
        return (bool) Empresa::find(request()->user()->empresa_id)?->gatewayPixAtivo();
    }
}
