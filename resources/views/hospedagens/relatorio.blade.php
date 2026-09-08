@extends('layouts.app')

@section('titulo', 'Relatório da Pousada')

@section('conteudo')
    <div class="mb-6 flex items-center justify-between gap-3">
        <a href="{{ route('hospedagens.index') }}" class="text-sm text-brand-600 hover:underline">&larr; Voltar pra Pousada</a>
    </div>

    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
        <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">De</label>
            <input type="date" name="data_inicio" value="{{ $inicio->toDateString() }}" class="mt-1 h-11 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Até</label>
            <input type="date" name="data_fim" value="{{ $fim->toDateString() }}" class="mt-1 h-11 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
        </div>
        <button class="h-11 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">Aplicar</button>
        <a href="{{ route('hospedagens.relatorio.pdf', ['data_inicio' => $inicio->toDateString(), 'data_fim' => $fim->toDateString()]) }}" class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">⬇ PDF</a>
    </form>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="overflow-hidden rounded-2xl border border-sky-300 bg-sky-100 p-5 shadow-sm dark:border-sky-700 dark:bg-sky-500/[0.15]">
            <div class="-mx-5 -mt-5 mb-4 h-1.5 bg-sky-500"></div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Estadias finalizadas</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-sky-700 dark:text-sky-400">{{ $relatorio['total_estadias'] }}</p>
        </div>
        <div class="overflow-hidden rounded-2xl border border-emerald-300 bg-emerald-100 p-5 shadow-sm dark:border-emerald-700 dark:bg-emerald-500/[0.15]">
            <div class="-mx-5 -mt-5 mb-4 h-1.5 bg-emerald-500"></div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Total faturado</p>
            <p class="mt-2 text-2xl font-bold tracking-tight text-emerald-700 dark:text-emerald-400">R$ {{ number_format($relatorio['total_faturado'], 2, ',', '.') }}</p>
        </div>
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Ticket médio</p>
            <p class="mt-2 text-2xl font-bold tracking-tight text-gray-800 dark:text-white/90">R$ {{ number_format($relatorio['ticket_medio'], 2, ',', '.') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
            <h3 class="mb-3 text-sm font-semibold text-slate-700">Por quarto</h3>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-slate-50">
                    @forelse ($relatorio['por_quarto'] as $linha)
                        <tr>
                            <td class="py-2">{{ $linha['quarto']->numero }}</td>
                            <td class="py-2 text-slate-500">{{ $linha['total_estadias'] }} estadia(s)</td>
                            <td class="py-2 text-right font-medium">R$ {{ number_format($linha['total_faturado'], 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td class="py-6 text-center text-slate-400">Sem dados no período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:col-span-2">
            <div class="max-w-full overflow-x-auto"><table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Quarto</th>
                        <th class="px-4 py-3">Hóspede</th>
                        <th class="px-4 py-3">Check-out</th>
                        <th class="px-4 py-3">Noites</th>
                        <th class="px-4 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($relatorio['hospedagens'] as $hospedagem)
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ $hospedagem->quarto->numero }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $hospedagem->cliente->nome }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $hospedagem->data_checkout_real->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ max(1, $hospedagem->data_checkin_real->diffInDays($hospedagem->data_checkout_real)) }}</td>
                            <td class="px-4 py-3 text-right font-medium">R$ {{ number_format($hospedagem->valor_total, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Nenhuma estadia finalizada nesse período.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
@endsection
