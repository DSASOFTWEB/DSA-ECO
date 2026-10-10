@extends('layouts.app')

@section('titulo', 'Fornecedores')

@section('conteudo')
    <div class="mb-6 flex items-center justify-between gap-3">
        <p class="text-sm text-slate-500 dark:text-slate-400">Fornecedores usados no cadastro de produtos. Volte pro <a href="{{ route('produtos.index') }}" class="underline">Estoque</a>.</p>
        @can('create', App\Models\Fornecedor::class)
            <a href="{{ route('fornecedores.create') }}" class="shrink-0 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">+ Novo fornecedor</a>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Nome</th>
                    <th class="px-4 py-3">Documento</th>
                    <th class="px-4 py-3">Telefone</th>
                    <th class="px-4 py-3">Contato</th>
                    <th class="px-4 py-3">Produtos</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($fornecedores as $fornecedor)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ $fornecedor->nome }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $fornecedor->documento ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $fornecedor->telefone ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $fornecedor->contato ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $fornecedor->produtos_count }}</td>
                        <td class="px-4 py-3 text-right">
                            @can('update', $fornecedor)
                                <a href="{{ route('fornecedores.edit', $fornecedor) }}" class="text-sky-700 hover:underline dark:text-sky-400">Editar</a>
                            @endcan
                            @can('delete', $fornecedor)
                                <form method="POST" action="{{ route('fornecedores.destroy', $fornecedor) }}" class="ml-2 inline" onsubmit="return confirm('Excluir este fornecedor?')">
                                    @csrf @method('DELETE')
                                    <button class="text-rose-600 hover:underline">Excluir</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Nenhum fornecedor cadastrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $fornecedores->links() }}</div>
@endsection
