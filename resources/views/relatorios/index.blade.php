@extends('layouts.app')

@section('titulo', 'Relatórios')

@section('conteudo')
    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
        <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Mês de referência</label>
            <input type="month" name="mes" value="{{ $mes->format('Y-m') }}" class="mt-1 h-11 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
        </div>
        <button class="h-11 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">Aplicar</button>
        <a href="{{ route('relatorios.financeiro.pdf', ['mes' => $mes->format('Y-m')]) }}" class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">⬇ PDF</a>
        <a href="{{ route('relatorios.financeiro.excel', ['mes' => $mes->format('Y-m')]) }}" class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">⬇ Excel</a>
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
