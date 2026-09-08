@extends('layouts.app')

@section('titulo', 'Clientes')

@section('conteudo')
    <div class="mb-6 flex flex-col gap-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" class="flex flex-1 flex-wrap gap-3">
            <input type="text" name="nome" value="{{ request('nome') }}" placeholder="Buscar por nome"
                   class="h-11 min-w-48 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90">
            <input type="text" name="cpf" value="{{ request('cpf') }}" placeholder="Buscar por CPF"
                   class="h-11 min-w-44 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90">
            <select name="status" class="h-11 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                <option value="">Todos os status</option>
                <option value="ativo" @selected(request('status') === 'ativo')>Ativo</option>
                <option value="inativo" @selected(request('status') === 'inativo')>Inativo</option>
                <option value="bloqueado" @selected(request('status') === 'bloqueado')>Bloqueado</option>
            </select>
            <button class="h-11 rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-white/[0.03]">Filtrar</button>
        </form>

        @can('create', \App\Models\Cliente::class)
            <a href="{{ route('clientes.create') }}" class="inline-flex h-11 items-center justify-center rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">
                + Novo cliente
            </a>
        @endcan
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="border-b border-gray-100 bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-white/[0.02] dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Nome</th>
                    <th class="px-4 py-3">CPF</th>
                    <th class="px-4 py-3">Contrato ativo</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse ($clientes as $cliente)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $cliente->nome }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $cliente->cpf }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $cliente->contratoAtivo?->plano->nome ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <x-status-badge :status="$cliente->status" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('clientes.show', $cliente) }}" class="text-sky-700 hover:underline">Ver</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Nenhum cliente encontrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $clientes->links() }}</div>
@endsection
