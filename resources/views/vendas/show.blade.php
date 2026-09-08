@extends('layouts.app')

@section('titulo', 'Venda #'.$venda->id)

@section('conteudo')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div>
            <h2 class="text-xl font-bold text-slate-800">Venda #{{ $venda->id }} @if ($venda->ehEntradaAvulsa()) <span class="ml-1 inline-flex rounded-full bg-cyan-50 px-2.5 py-1 align-middle text-xs font-medium text-cyan-700 dark:bg-cyan-500/15 dark:text-cyan-400">Entrada avulsa</span> @endif</h2>
            <p class="text-sm text-slate-500">{{ $venda->cliente?->nome ?? 'Consumidor final' }} · vendedor {{ $venda->vendedor?->name ?? 'venda online' }} · <x-status-badge :status="$venda->status" /></p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($venda->status === 'pago' && $venda->ehEntradaAvulsa())
                <a href="{{ route('vendas.voucher', $venda) }}" target="_blank" class="inline-flex items-center rounded-lg border border-cyan-300 px-4 py-2 text-sm font-medium text-cyan-700 hover:bg-cyan-50 dark:border-cyan-500/40 dark:text-cyan-400 dark:hover:bg-cyan-500/10">Baixar voucher (PDF)</a>
            @endif

            @if ($venda->status !== 'cancelado')
                @can('cancelar', $venda)
                    <form method="POST" action="{{ route('vendas.cancelar', $venda) }}" onsubmit="return confirm('Cancelar esta venda e estornar o estoque?')">
                        @csrf
                        <button class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Cancelar venda</button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full text-sm">
            <thead class="text-left text-xs uppercase text-slate-400">
                <tr><th class="py-2">Item</th><th>Qtd</th><th>Preço unit.</th><th>Subtotal</th></tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @foreach ($venda->itens as $item)
                    <tr>
                        <td class="py-2">{{ $item->nomeItem() }} @if ($item->tipo_entrada_id) <span class="text-xs text-cyan-600">(entrada)</span> @endif</td>
                        <td class="py-2">{{ $item->quantidade }}</td>
                        <td class="py-2">R$ {{ number_format($item->preco_unitario, 2, ',', '.') }}</td>
                        <td class="py-2 font-medium">R$ {{ number_format($item->subtotal, 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4 flex justify-end border-t border-slate-100 pt-4 text-sm">
            <dl class="space-y-1 text-right">
                <div><dt class="inline text-slate-400">Bruto: </dt><dd class="inline">R$ {{ number_format($venda->valor_bruto, 2, ',', '.') }}</dd></div>
                <div><dt class="inline text-slate-400">Desconto: </dt><dd class="inline">R$ {{ number_format($venda->desconto, 2, ',', '.') }}</dd></div>
                <div class="text-base font-semibold"><dt class="inline">Total: </dt><dd class="inline">R$ {{ number_format($venda->valor_total, 2, ',', '.') }}</dd></div>
            </dl>
        </div>
        @if ($venda->observacao)
            <div class="mt-4 border-t border-slate-100 pt-4 text-sm">
                <dt class="text-slate-400">Observação</dt>
                <dd class="mt-1">{{ $venda->observacao }}</dd>
            </div>
        @endif
    </div>
@endsection
