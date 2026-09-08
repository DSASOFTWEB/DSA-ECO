@extends('layouts.app')

@section('titulo', 'Vendas')

@section('conteudo')
    <div class="mb-6 flex justify-end">
        @can('create', \App\Models\Venda::class)
            <a href="{{ route('vendas.create') }}" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">+ Nova venda (PDV)</a>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">#</th>
                    <th class="px-4 py-3">Tipo</th>
                    <th class="px-4 py-3">Vendedor</th>
                    <th class="px-4 py-3">Cliente</th>
                    <th class="px-4 py-3">Valor total</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Data</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($vendas as $venda)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                        <td class="px-4 py-3 text-slate-500">#{{ $venda->id }}</td>
                        <td class="px-4 py-3">
                            @if ($venda->ehEntradaAvulsa())
                                <span class="inline-flex rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-medium text-cyan-700 dark:bg-cyan-500/15 dark:text-cyan-400">Entrada avulsa</span>
                            @else
                                <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">Produtos</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $venda->vendedor?->name ?? '—' }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $venda->cliente?->nome ?? 'Consumidor final' }}</td>
                        <td class="px-4 py-3 text-slate-500">R$ {{ number_format($venda->valor_total, 2, ',', '.') }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$venda->status" /></td>
                        <td class="px-4 py-3 text-slate-400">{{ $venda->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('vendas.show', $venda) }}" class="text-sky-700 hover:underline">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">Nenhuma venda registrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $vendas->links() }}</div>
@endsection
