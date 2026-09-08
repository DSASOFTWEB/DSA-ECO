@extends('layouts.app')

@section('titulo', 'Pousada')

@section('conteudo')
    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <div class="overflow-hidden rounded-2xl border border-rose-300 bg-rose-100 p-5 shadow-sm dark:border-rose-700 dark:bg-rose-500/[0.15]">
            <div class="-mx-5 -mt-5 mb-4 h-1.5 bg-rose-500"></div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Ocupados</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-rose-700 dark:text-rose-400">{{ $resumo['ocupados'] }}</p>
        </div>
        <div class="overflow-hidden rounded-2xl border border-emerald-300 bg-emerald-100 p-5 shadow-sm dark:border-emerald-700 dark:bg-emerald-500/[0.15]">
            <div class="-mx-5 -mt-5 mb-4 h-1.5 bg-emerald-500"></div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Livres</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-emerald-700 dark:text-emerald-400">{{ $resumo['livres'] }}</p>
        </div>
        <div class="overflow-hidden rounded-2xl border border-sky-300 bg-sky-100 p-5 shadow-sm dark:border-sky-700 dark:bg-sky-500/[0.15]">
            <div class="-mx-5 -mt-5 mb-4 h-1.5 bg-sky-500"></div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Check-ins futuros</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-sky-700 dark:text-sky-400">{{ $resumo['checkins_futuros'] }}</p>
        </div>
        <div class="overflow-hidden rounded-2xl border border-violet-300 bg-violet-100 p-5 shadow-sm dark:border-violet-700 dark:bg-violet-500/[0.15]">
            <div class="-mx-5 -mt-5 mb-4 h-1.5 bg-violet-500"></div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Faturado hoje</p>
            <p class="mt-2 text-2xl font-bold tracking-tight text-violet-700 dark:text-violet-400">R$ {{ number_format($resumo['faturado_hoje'], 2, ',', '.') }}</p>
        </div>
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Faturamento do mês</p>
            <p class="mt-2 text-2xl font-bold tracking-tight text-gray-800 dark:text-white/90">R$ {{ number_format($resumo['faturado_mes'], 2, ',', '.') }}</p>
        </div>
    </div>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <select name="status" onchange="this.form.submit()" class="rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                <option value="">Todos os status</option>
                @foreach (['reservado', 'hospedado', 'finalizado', 'cancelado'] as $opcao)
                    <option value="{{ $opcao }}" @selected(request('status') === $opcao)>{{ ucfirst($opcao) }}</option>
                @endforeach
            </select>
        </form>
        <div class="flex shrink-0 gap-2">
            <a href="{{ route('hospedagens.relatorio') }}" class="inline-flex items-center rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Relatório por período</a>
            @can('create', \App\Models\Hospedagem::class)
                <a href="{{ route('hospedagens.create') }}" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">+ Nova reserva</a>
            @endcan
        </div>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Quarto</th>
                    <th class="px-4 py-3">Hóspede</th>
                    <th class="px-4 py-3">Check-in</th>
                    <th class="px-4 py-3">Check-out</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($hospedagens as $hospedagem)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ $hospedagem->quarto->numero }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $hospedagem->cliente->nome }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $hospedagem->data_checkin_prevista->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $hospedagem->data_checkout_prevista->format('d/m/Y') }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$hospedagem->status" /></td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('hospedagens.show', $hospedagem) }}" class="text-sky-700 hover:underline dark:text-sky-400">Ver</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Nenhuma reserva/hospedagem encontrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $hospedagens->links() }}</div>
@endsection
