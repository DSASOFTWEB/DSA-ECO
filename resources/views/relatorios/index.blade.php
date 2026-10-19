@extends('layouts.app')

@section('titulo', 'Relatórios')

@section('conteudo')
    @php
        $coresRelatorio = [
            ['topo' => 'bg-sky-500', 'icone' => 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300'],
            ['topo' => 'bg-emerald-500', 'icone' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'],
            ['topo' => 'bg-amber-500', 'icone' => 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'],
            ['topo' => 'bg-violet-500', 'icone' => 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300'],
            ['topo' => 'bg-rose-500', 'icone' => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300'],
        ];
    @endphp
    <section class="mb-8">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Relatórios gerenciais</h2>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach (\App\Services\Relatorios\RelatorioGerencialService::RELATORIOS as $chaveRel => $rel)
                @php $cor = $coresRelatorio[$loop->index % count($coresRelatorio)]; @endphp
                <a href="{{ route('relatorios.gerencial', $chaveRel) }}" class="group relative flex items-start gap-3 overflow-hidden rounded-2xl border border-gray-200 bg-white p-4 pt-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-gray-800 dark:bg-white/[0.03]">
                    <span class="absolute inset-x-0 top-0 h-1 {{ $cor['topo'] }}"></span>
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $cor['icone'] }}">
                        <x-nav-icon :name="$rel['icone']" class="h-5 w-5" />
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-gray-800 group-hover:text-brand-600 dark:text-white/90">{{ $rel['titulo'] }}</span>
                        <span class="mt-0.5 block text-xs leading-snug text-gray-500 dark:text-gray-400">{{ $rel['descricao'] }}</span>
                    </span>
                </a>
            @endforeach
        </div>
    </section>

    <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Mensalidades e inadimplência</h2>
    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
        <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Mês de referência</label>
            <input type="month" name="mes" value="{{ $mes->format('Y-m') }}" class="mt-1 h-11 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
        </div>
        <button class="h-11 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">Aplicar</button>
        <a href="{{ route('relatorios.financeiro.pdf', ['mes' => $mes->format('Y-m')]) }}" class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">⬇ PDF</a>
        <a href="{{ route('relatorios.financeiro.excel', ['mes' => $mes->format('Y-m')]) }}" class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">⬇ Excel</a>
        <a href="{{ route('relatorios.movimentacoes') }}" class="ml-auto inline-flex h-11 items-center rounded-lg bg-slate-700 px-4 text-sm font-medium text-white hover:bg-slate-800">Relatório de Movimentações →</a>
    </form>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
            <h3 class="mb-3 text-sm font-semibold text-slate-700">Financeiro — {{ $mes->translatedFormat('F/Y') }}</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-400">Previsto</dt><dd>R$ {{ number_format($resumoFinanceiro['previsto'], 2, ',', '.') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Recebido</dt><dd class="text-emerald-600">R$ {{ number_format($resumoFinanceiro['recebido'], 2, ',', '.') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Pendente</dt><dd class="text-amber-600">R$ {{ number_format($resumoFinanceiro['pendente'], 2, ',', '.') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Atrasado</dt><dd class="text-rose-600">R$ {{ number_format($resumoFinanceiro['atrasado'], 2, ',', '.') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">Vendas de produtos</dt><dd>R$ {{ number_format($resumoFinanceiro['vendas_produtos'], 2, ',', '.') }}</dd></div>
            </dl>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
            <h3 class="mb-3 text-sm font-semibold text-slate-700">Ranking de vendedores</h3>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-slate-50">
                    @forelse ($rankingVendedores as $linha)
                        <tr>
                            <td class="py-2">{{ $linha->name }}</td>
                            <td class="py-2 text-slate-500">{{ $linha->total_negocios }} negócio(s)</td>
                            <td class="py-2 text-right font-medium">R$ {{ number_format($linha->total_comissao, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td class="py-6 text-center text-slate-400">Sem dados no período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-6 lg:col-span-2">
            <h3 class="mb-3 text-sm font-semibold text-slate-700">Inadimplência por unidade</h3>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-slate-50">
                    @forelse ($inadimplenciaPorUnidade as $linha)
                        <tr>
                            <td class="py-2">{{ $linha->nome }}</td>
                            <td class="py-2 text-slate-500">{{ $linha->total_mensalidades }} mensalidade(s)</td>
                            <td class="py-2 text-right font-medium text-rose-600">R$ {{ number_format($linha->total_valor, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td class="py-6 text-center text-slate-400">Nenhuma inadimplência 🎉</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
