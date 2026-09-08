@extends('layouts.app')

@section('titulo', 'Estoque de produtos')

@section('conteudo')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <form method="GET" class="flex flex-wrap gap-2">
            <input type="text" name="nome" value="{{ request('nome') }}" placeholder="Buscar produto" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <button class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-900 dark:bg-white/10 dark:hover:bg-white/15">Filtrar</button>
        </form>

        @can('create', \App\Models\Produto::class)
            <a href="{{ route('produtos.create') }}" class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">+ Novo produto</a>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Produto</th>
                    <th class="px-4 py-3">Categoria</th>
                    <th class="px-4 py-3">Preço venda</th>
                    <th class="px-4 py-3">Estoque</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($produtos as $produto)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $produto->nome }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $produto->categoria?->nome ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">R$ {{ number_format($produto->preco_venda, 2, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            <span class="{{ $produto->estoqueAbaixoDoMinimo() ? 'font-semibold text-rose-600' : 'text-slate-500' }}">
                                {{ $produto->controla_estoque ? $produto->estoque_atual : '—' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('produtos.show', $produto) }}" class="text-sky-700 hover:underline">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Nenhum produto cadastrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $produtos->links() }}</div>
@endsection
