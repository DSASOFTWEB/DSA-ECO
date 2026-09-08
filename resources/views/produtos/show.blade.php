@extends('layouts.app')

@section('titulo', $produto->nome)

@section('conteudo')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div>
            <h2 class="text-xl font-bold text-slate-800">{{ $produto->nome }}</h2>
            <p class="text-sm text-slate-500">{{ $produto->categoria?->nome ?? 'Sem categoria' }} · R$ {{ number_format($produto->preco_venda, 2, ',', '.') }}</p>
        </div>
        @can('update', $produto)
            <a href="{{ route('produtos.edit', $produto) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Editar</a>
        @endcan
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-3 text-sm font-semibold text-slate-700">Estoque atual</h3>
            <p class="text-3xl font-bold {{ $produto->estoqueAbaixoDoMinimo() ? 'text-rose-600' : 'text-slate-800' }}">{{ $produto->estoque_atual }}</p>
            <p class="text-xs text-slate-400">mínimo: {{ $produto->estoque_minimo }}</p>

            @can('ajustarEstoque', $produto)
                <form method="POST" action="{{ route('produtos.ajustar-estoque', $produto) }}" class="mt-4 space-y-2">
                    @csrf
                    <input type="number" min="0" name="quantidade" placeholder="Nova quantidade" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input type="text" name="motivo" placeholder="Motivo do ajuste" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <button class="w-full rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Ajustar estoque</button>
                </form>
            @endcan
        </div>

        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:col-span-2">
            <h3 class="mb-3 text-sm font-semibold text-slate-700">Últimas movimentações</h3>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-slate-50">
                    @forelse ($produto->movimentacoesEstoque as $mov)
                        <tr>
                            <td class="py-2">{{ ucfirst($mov->tipo) }}</td>
                            <td class="py-2 text-slate-500">{{ $mov->motivo }}</td>
                            <td class="py-2 text-slate-500">{{ $mov->quantidade_anterior }} → {{ $mov->quantidade_atual }}</td>
                            <td class="py-2 text-right text-slate-400">{{ $mov->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td class="py-6 text-center text-slate-400">Nenhuma movimentação registrada.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
