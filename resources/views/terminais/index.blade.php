@extends('layouts.app')

@section('titulo', 'Terminais')

@section('conteudo')
    <div class="mb-6 flex items-center justify-between gap-3">
        <p class="text-sm text-slate-500 dark:text-slate-400">Cada terminal pode ter seu próprio caixa aberto — várias pessoas da mesma unidade podem vender ao mesmo tempo, cada uma no seu terminal.</p>
        @can('create', \App\Models\Terminal::class)
            <a href="{{ route('terminais.create') }}" class="shrink-0 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">+ Novo terminal</a>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Terminal</th>
                    <th class="px-4 py-3">Unidade</th>
                    <th class="px-4 py-3">Caixa</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($terminais as $terminal)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ $terminal->nome }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $terminal->unidade->nome }}</td>
                        <td class="px-4 py-3">
                            @if ($terminal->caixas_abertos_count > 0)
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">● Aberto</span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500 dark:bg-white/5 dark:text-gray-400">Fechado</span>
                            @endif
                        </td>
                        <td class="px-4 py-3"><x-status-badge :status="$terminal->status" /></td>
                        <td class="px-4 py-3 text-right">
                            @can('update', $terminal)
                                <a href="{{ route('terminais.edit', $terminal) }}" class="text-sky-700 hover:underline dark:text-sky-400">Editar</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Nenhum terminal cadastrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $terminais->links() }}</div>
@endsection
