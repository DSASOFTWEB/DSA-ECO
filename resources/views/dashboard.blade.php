@extends('layouts.app')

@section('titulo', 'Dashboard')

@section('conteudo')
    @php
        $kpiCards = [
            ['card' => 'border-sky-300 bg-sky-100 hover:border-sky-500 dark:border-sky-700 dark:bg-sky-500/[0.15] dark:hover:border-sky-500/60', 'accent' => 'bg-sky-500', 'valor' => 'text-sky-700 dark:text-sky-400'],
            ['card' => 'border-violet-300 bg-violet-100 hover:border-violet-500 dark:border-violet-700 dark:bg-violet-500/[0.15] dark:hover:border-violet-500/60', 'accent' => 'bg-violet-500', 'valor' => 'text-violet-700 dark:text-violet-400'],
            ['card' => 'border-rose-300 bg-rose-100 hover:border-rose-500 dark:border-rose-700 dark:bg-rose-500/[0.15] dark:hover:border-rose-500/60', 'accent' => 'bg-rose-500', 'valor' => 'text-rose-700 dark:text-rose-400'],
            ['card' => 'border-emerald-300 bg-emerald-100 hover:border-emerald-500 dark:border-emerald-700 dark:bg-emerald-500/[0.15] dark:hover:border-emerald-500/60', 'accent' => 'bg-emerald-500', 'valor' => 'text-emerald-700 dark:text-emerald-400'],
            ['card' => 'border-cyan-300 bg-cyan-100 hover:border-cyan-500 dark:border-cyan-700 dark:bg-cyan-500/[0.15] dark:hover:border-cyan-500/60', 'accent' => 'bg-cyan-500', 'valor' => 'text-cyan-700 dark:text-cyan-400'],
            ['card' => 'border-orange-300 bg-orange-100 hover:border-orange-500 dark:border-orange-700 dark:bg-orange-500/[0.15] dark:hover:border-orange-500/60', 'accent' => 'bg-orange-500', 'valor' => 'text-orange-700 dark:text-orange-400'],
            ['card' => 'border-indigo-300 bg-indigo-100 hover:border-indigo-500 dark:border-indigo-700 dark:bg-indigo-500/[0.15] dark:hover:border-indigo-500/60', 'accent' => 'bg-indigo-500', 'valor' => 'text-indigo-700 dark:text-indigo-400'],
            ['card' => 'border-teal-300 bg-teal-100 hover:border-teal-500 dark:border-teal-700 dark:bg-teal-500/[0.15] dark:hover:border-teal-500/60', 'accent' => 'bg-teal-500', 'valor' => 'text-teal-700 dark:text-teal-400'],
            ['card' => 'border-lime-300 bg-lime-100 hover:border-lime-500 dark:border-lime-700 dark:bg-lime-500/[0.15] dark:hover:border-lime-500/60', 'accent' => 'bg-lime-500', 'valor' => 'text-lime-700 dark:text-lime-400'],
        ];
    @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 md:gap-6">
        <div class="overflow-hidden rounded-2xl border p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md md:p-6 {{ $kpiCards[0]['card'] }}">
            <div class="-mx-5 -mt-5 mb-4 h-1.5 md:-mx-6 md:-mt-6 {{ $kpiCards[0]['accent'] }}"></div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Clientes ativos</p>
            <p class="mt-2 text-3xl font-bold tracking-tight {{ $kpiCards[0]['valor'] }}">{{ $kpis['clientes_ativos'] }}</p>
        </div>
        <div class="overflow-hidden rounded-2xl border p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md md:p-6 {{ $kpiCards[1]['card'] }}">
            <div class="-mx-5 -mt-5 mb-4 h-1.5 md:-mx-6 md:-mt-6 {{ $kpiCards[1]['accent'] }}"></div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Contratos ativos</p>
            <p class="mt-2 text-3xl font-bold tracking-tight {{ $kpiCards[1]['valor'] }}">{{ $kpis['contratos_ativos'] }}</p>
        </div>
        <div class="overflow-hidden rounded-2xl border p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md md:p-6 {{ $kpiCards[2]['card'] }}">
            <div class="-mx-5 -mt-5 mb-4 h-1.5 md:-mx-6 md:-mt-6 {{ $kpiCards[2]['accent'] }}"></div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Mensalidades atrasadas</p>
            <p class="mt-2 text-3xl font-bold tracking-tight {{ $kpiCards[2]['valor'] }}">{{ $kpis['mensalidades_atrasadas'] }}</p>
        </div>
        <div class="overflow-hidden rounded-2xl border p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md md:p-6 {{ $kpiCards[3]['card'] }}">
            <div class="-mx-5 -mt-5 mb-4 h-1.5 md:-mx-6 md:-mt-6 {{ $kpiCards[3]['accent'] }}"></div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Faturamento do mês</p>
            <p class="mt-2 text-3xl font-bold tracking-tight {{ $kpiCards[3]['valor'] }}">R$ {{ number_format($kpis['faturamento_mes'], 2, ',', '.') }}</p>
        </div>
        <div class="overflow-hidden rounded-2xl border p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md md:p-6 {{ $kpiCards[4]['card'] }}">
            <div class="-mx-5 -mt-5 mb-4 h-1.5 md:-mx-6 md:-mt-6 {{ $kpiCards[4]['accent'] }}"></div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Entradas hoje</p>
            <p class="mt-2 text-3xl font-bold tracking-tight {{ $kpiCards[4]['valor'] }}">{{ $kpis['entradas_hoje'] }}</p>
        </div>
        <div class="overflow-hidden rounded-2xl border p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md md:p-6 {{ $kpiCards[5]['card'] }}">
            <div class="-mx-5 -mt-5 mb-4 h-1.5 md:-mx-6 md:-mt-6 {{ $kpiCards[5]['accent'] }}"></div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Contas a pagar</p>
            <p class="mt-2 text-3xl font-bold tracking-tight {{ $kpiCards[5]['valor'] }}">R$ {{ number_format($kpis['contas_a_pagar'], 2, ',', '.') }}</p>
            <a href="{{ route('financeiro.index') }}" class="mt-1 inline-block text-xs text-slate-400 hover:underline">Ver financeiro &rarr;</a>
        </div>
        <div class="overflow-hidden rounded-2xl border p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md md:p-6 {{ $kpiCards[8]['card'] }}">
            <div class="-mx-5 -mt-5 mb-4 h-1.5 md:-mx-6 md:-mt-6 {{ $kpiCards[8]['accent'] }}"></div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Contas a receber</p>
            <p class="mt-2 text-3xl font-bold tracking-tight {{ $kpiCards[8]['valor'] }}">R$ {{ number_format($kpis['contas_a_receber'], 2, ',', '.') }}</p>
            <a href="{{ route('financeiro.index') }}" class="mt-1 inline-block text-xs text-slate-400 hover:underline">Ver financeiro &rarr;</a>
        </div>
        <div class="overflow-hidden rounded-2xl border p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md md:p-6 {{ $kpiCards[6]['card'] }}">
            <div class="-mx-5 -mt-5 mb-4 h-1.5 md:-mx-6 md:-mt-6 {{ $kpiCards[6]['accent'] }}"></div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Ticket médio</p>
            <p class="mt-2 text-3xl font-bold tracking-tight {{ $kpiCards[6]['valor'] }}">R$ {{ number_format($kpis['ticket_medio'], 2, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-400">Vendas pagas no mês</p>
        </div>
        <div class="overflow-hidden rounded-2xl border p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md md:p-6 {{ $kpiCards[7]['card'] }}">
            <div class="-mx-5 -mt-5 mb-4 h-1.5 md:-mx-6 md:-mt-6 {{ $kpiCards[7]['accent'] }}"></div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Faturado hoje</p>
            <p class="mt-2 text-3xl font-bold tracking-tight {{ $kpiCards[7]['valor'] }}">R$ {{ number_format($kpis['faturado_hoje'], 2, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-400">Mensalidades + vendas pagas hoje</p>
        </div>
    </div>

    @if ($entradasPorTipoEntrada->isNotEmpty())
        <hr class="my-6 border-gray-200 dark:border-gray-800">
        <div>
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-300">Entrada por tipo de ingresso no parque</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 md:gap-6">
                @foreach ($entradasPorTipoEntrada as $i => $entradaTipo)
                    @php
                        $cor = $kpiCards[$i % count($kpiCards)];
                    @endphp
                    <div class="overflow-hidden rounded-2xl border p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md md:p-6 {{ $cor['card'] }}">
                        <div class="-mx-5 -mt-5 mb-4 h-1.5 md:-mx-6 md:-mt-6 {{ $cor['accent'] }}"></div>
                        <p class="truncate text-xs font-medium uppercase tracking-wide text-slate-400" title="{{ $entradaTipo['nome'] }}">{{ $entradaTipo['nome'] }}</p>
                        <p class="mt-2 text-3xl font-bold tracking-tight {{ $cor['valor'] }}">{{ $entradaTipo['quantidade'] }}</p>
                        <p class="mt-1 text-xs text-slate-400">Entradas hoje</p>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Saldo do caixa — últimos 14 dias</h2>
            <span class="text-xs text-slate-400">Gráfico de velas (abertura/máxima/mínima/fechamento do saldo diário)</span>
        </div>
        <div id="grafico-velas-caixa"></div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const dados = @json($velasCaixa);
                const el = document.getElementById('grafico-velas-caixa');
                if (! el || ! window.ApexCharts || dados.length === 0) return;

                const isDark = document.documentElement.classList.contains('dark');

                const chart = new ApexCharts(el, {
                    chart: { type: 'candlestick', height: 280, toolbar: { show: false }, background: 'transparent' },
                    theme: { mode: isDark ? 'dark' : 'light' },
                    series: [{
                        name: 'Saldo',
                        data: dados.map(v => ({ x: v.data, y: [v.open, v.high, v.low, v.close] })),
                    }],
                    xaxis: { type: 'datetime' },
                    yaxis: { labels: { formatter: (v) => 'R$ ' + Number(v).toFixed(0) } },
                    plotOptions: { candlestick: { colors: { upward: '#10b981', downward: '#f43f5e' } } },
                    grid: { borderColor: isDark ? '#1f2937' : '#f1f5f9' },
                });
                chart.render();
            });
        </script>
    @endpush

    <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
        <div class="mb-4 flex items-center justify-between">
            <h2 class="text-sm font-semibold text-slate-700">Entradas por hora (hoje)</h2>
            <span class="text-sm font-medium text-slate-500">Total: {{ $kpis['entradas_hoje'] }}</span>
        </div>
        @if ($kpis['entradas_hoje'] > 0)
            @php $maxEntradas = max(1, ...array_values($entradasPorHora)); @endphp
            <div class="flex items-end gap-1">
                @foreach ($entradasPorHora as $hora => $total)
                    <div class="group flex flex-1 flex-col items-center gap-1">
                        <div class="flex h-24 w-full items-end">
                            <div class="w-full rounded-t bg-cyan-500 transition group-hover:bg-cyan-600 dark:bg-cyan-500/80" style="height: {{ $total > 0 ? max(6, round(($total / $maxEntradas) * 100)) : 0 }}%" title="{{ $total }} entrada(s) às {{ str_pad((string) $hora, 2, '0', STR_PAD_LEFT) }}h"></div>
                        </div>
                        <span class="text-[10px] text-slate-400">{{ $hora }}h</span>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-slate-400">Nenhuma entrada registrada hoje.</p>
        @endif
    </div>

    <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
            <h2 class="mb-4 text-sm font-semibold text-slate-700">Resumo financeiro do mês</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Previsto</dt><dd class="font-medium">R$ {{ number_format($resumoFinanceiro['previsto'], 2, ',', '.') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Recebido</dt><dd class="font-medium text-emerald-600">R$ {{ number_format($resumoFinanceiro['recebido'], 2, ',', '.') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Pendente</dt><dd class="font-medium text-amber-600">R$ {{ number_format($resumoFinanceiro['pendente'], 2, ',', '.') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Atrasado</dt><dd class="font-medium text-rose-600">R$ {{ number_format($resumoFinanceiro['atrasado'], 2, ',', '.') }}</dd></div>
                <div class="flex justify-between border-t border-slate-100 pt-2"><dt class="text-slate-500">Vendas (produtos + entradas avulsas)</dt><dd class="font-medium">R$ {{ number_format($resumoFinanceiro['vendas_produtos'], 2, ',', '.') }}</dd></div>
            </dl>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
            <h2 class="mb-4 text-sm font-semibold text-slate-700">Inadimplência por unidade</h2>
            @forelse ($inadimplenciaPorUnidade as $linha)
                <div class="flex items-center justify-between border-b border-slate-50 py-2 text-sm last:border-0">
                    <span>{{ $linha->nome }}</span>
                    <span class="text-slate-500">{{ $linha->total_mensalidades }} mensalidade(s)</span>
                    <span class="font-medium text-rose-600">R$ {{ number_format($linha->total_valor, 2, ',', '.') }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-400">Nenhuma inadimplência registrada 🎉</p>
            @endforelse
        </div>
    </div>
@endsection
