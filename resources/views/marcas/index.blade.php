@extends('layouts.app')

@section('titulo', 'Marcas')

@section('conteudo')
    <div class="mb-6 flex items-center justify-between gap-3">
        <p class="text-sm text-slate-500 dark:text-slate-400">Marcas usadas no cadastro de produtos. Volte pro <a href="{{ route('produtos.index') }}" class="underline">Estoque</a>.</p>
        @can('create', App\Models\Marca::class)
            <a href="{{ route('marcas.create') }}" class="shrink-0 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">+ Nova marca</a>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Nome</th>
                    <th class="px-4 py-3">Produtos</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($marcas as $marca)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ $marca->nome }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $marca->produtos_count }}</td>
                        <td class="px-4 py-3 text-right">
                            @can('update', $marca)
                                <a href="{{ route('marcas.edit', $marca) }}" class="text-sky-700 hover:underline dark:text-sky-400">Editar</a>
                            @endcan
                            @can('delete', $marca)
                                <form method="POST" action="{{ route('marcas.destroy', $marca) }}" class="ml-2 inline" onsubmit="return confirm('Excluir esta marca?')">
                                    @csrf @method('DELETE')
                                    <button class="text-rose-600 hover:underline">Excluir</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-8 text-center text-slate-400">Nenhuma marca cadastrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $marcas->links() }}</div>
@endsection
