@extends('layouts.app')

@section('titulo', 'Indicadores da Pousada')

@section('conteudo')
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
        <a href="{{ route('hospedagens.indicadores') }}" class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Hoje</a>
    </form>

    @php
        $cards = [
            ['label' => 'Taxa de ocupação', 'valor' => number_format($indicadores['taxa_ocupacao'], 1, ',', '.').'%', 'cor' => 'sky'],
            ['label' => 'RevPAR', 'valor' => 'R$ '.number_format($indicadores['revpar'], 2, ',', '.'), 'cor' => 'violet'],
            ['label' => 'Diária média (ADR)', 'valor' => 'R$ '.number_format($indicadores['diaria_media'], 2, ',', '.'), 'cor' => 'orange'],
            ['label' => 'Receita', 'valor' => 'R$ '.number_format($indicadores['receita'], 2, ',', '.'), 'cor' => 'emerald'],
            ['label' => 'Novas reservas', 'valor' => $indicadores['novas_reservas'], 'cor' => 'cyan'],
            ['label' => 'Nº de hóspedes', 'valor' => $indicadores['numero_hospedes'], 'cor' => 'indigo'],
            ['label' => 'Reservas canceladas', 'valor' => $indicadores['reservas_canceladas'], 'cor' => 'rose'],
        ];
        $classesCor = [
            'sky' => ['borda' => 'border-sky-300 bg-sky-100 dark:border-sky-700 dark:bg-sky-500/[0.15]', 'topo' => 'bg-sky-500', 'texto' => 'text-sky-700 dark:text-sky-400'],
            'violet' => ['borda' => 'border-violet-300 bg-violet-100 dark:border-violet-700 dark:bg-violet-500/[0.15]', 'topo' => 'bg-violet-500', 'texto' => 'text-violet-700 dark:text-violet-400'],
            'orange' => ['borda' => 'border-orange-300 bg-orange-100 dark:border-orange-700 dark:bg-orange-500/[0.15]', 'topo' => 'bg-orange-500', 'texto' => 'text-orange-700 dark:text-orange-400'],
            'emerald' => ['borda' => 'border-emerald-300 bg-emerald-100 dark:border-emerald-700 dark:bg-emerald-500/[0.15]', 'topo' => 'bg-emerald-500', 'texto' => 'text-emerald-700 dark:text-emerald-400'],
            'cyan' => ['borda' => 'border-cyan-300 bg-cyan-100 dark:border-cyan-700 dark:bg-cyan-500/[0.15]', 'topo' => 'bg-cyan-500', 'texto' => 'text-cyan-700 dark:text-cyan-400'],
            'indigo' => ['borda' => 'border-indigo-300 bg-indigo-100 dark:border-indigo-700 dark:bg-indigo-500/[0.15]', 'topo' => 'bg-indigo-500', 'texto' => 'text-indigo-700 dark:text-indigo-400'],
            'rose' => ['borda' => 'border-rose-300 bg-rose-100 dark:border-rose-700 dark:bg-rose-500/[0.15]', 'topo' => 'bg-rose-500', 'texto' => 'text-rose-700 dark:text-rose-400'],
        ];
    @endphp

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($cards as $card)
            @php $c = $classesCor[$card['cor']]; @endphp
            <div class="overflow-hidden rounded-2xl border {{ $c['borda'] }} p-5 shadow-sm">
                <div class="-mx-5 -mt-5 mb-4 h-1.5 {{ $c['topo'] }}"></div>
                <p class="text-xs font-medium uppercase tracking-wide text-slate-400">{{ $card['label'] }}</p>
                <p class="mt-2 text-2xl font-bold tracking-tight {{ $c['texto'] }}">{{ $card['valor'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
        <h2 class="mb-4 text-sm font-semibold text-slate-700 dark:text-slate-300">Reservas por dia ({{ $inicio->format('d/m') }} — {{ $fim->format('d/m') }})</h2>
        <div id="grafico-reservas-pousada"></div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const dados = @json($porDia);
                const el = document.getElementById('grafico-reservas-pousada');
                if (! el || ! window.ApexCharts || dados.length === 0) return;

                const isDark = document.documentElement.classList.contains('dark');

                const chart = new ApexCharts(el, {
                    chart: { type: 'bar', height: 280, toolbar: { show: false }, background: 'transparent' },
                    theme: { mode: isDark ? 'dark' : 'light' },
                    series: [
                        { name: 'Novas', data: dados.map(d => d.novas) },
                        { name: 'Canceladas', data: dados.map(d => d.canceladas) },
                    ],
                    xaxis: { categories: dados.map(d => new Date(d.data).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })) },
                    colors: ['#0ea5e9', '#f43f5e'],
                    plotOptions: { bar: { columnWidth: '55%', borderRadius: 3 } },
                    grid: { borderColor: isDark ? '#1f2937' : '#f1f5f9' },
                });
                chart.render();
            });
        </script>
    @endpush
@endsection
