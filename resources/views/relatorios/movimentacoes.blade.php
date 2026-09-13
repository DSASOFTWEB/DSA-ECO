@extends('layouts.app')

@section('titulo', 'Relatório de Movimentações')

@php
    $abas = [
        'consolidado' => 'Consolidado dos Caixas',
        'por_caixa' => 'Movimentação por Caixa',
        'entradas' => 'Entradas por Período',
        'saidas' => 'Saídas por Período',
        'fluxo' => 'Fluxo de Caixa',
    ];
@endphp

@section('conteudo')
    <div class="mb-6 flex flex-wrap gap-2 border-b border-gray-200 dark:border-gray-800">
        @foreach ($abas as $chave => $label)
            <a href="{{ route('relatorios.movimentacoes', array_merge(request()->except('tipo'), ['tipo' => $chave])) }}"
               class="border-b-2 px-4 py-2.5 text-sm font-medium transition {{ $tipo === $chave ? 'border-sky-500 text-sky-700 dark:text-sky-400' : 'border-transparent text-slate-500 hover:text-slate-700' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
        <input type="hidden" name="tipo" value="{{ $tipo }}">
        <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">De</label>
            <input type="date" name="data_inicio" value="{{ $inicio->toDateString() }}" class="mt-1 h-11 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Até</label>
            <input type="date" name="data_fim" value="{{ $fim->toDateString() }}" class="mt-1 h-11 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
        </div>

        @if (in_array($tipo, ['entradas', 'saidas']))
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Caixa/terminal</label>
                <select name="caixa_id" class="mt-1 h-11 rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm dark:border-gray-700">
                    <option value="">Todos</option>
                    @foreach ($caixas as $caixa)
                        <option value="{{ $caixa->id }}" @selected(request('caixa_id') == $caixa->id)>{{ $caixa->terminal->nome ?? ('Caixa #'.$caixa->id) }} — {{ $caixa->data_abertura->format('d/m/Y') }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Categoria</label>
                <select name="categoria" class="mt-1 h-11 rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm dark:border-gray-700">
                    <option value="">Todas</option>
                    @foreach (($tipo === 'entradas' ? $categoriasEntrada : $categoriasSaida) as $chave => $label)
                        <option value="{{ $chave }}" @selected(request('categoria') === $chave)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Usuário</label>
                <select name="usuario_id" class="mt-1 h-11 rounded-lg border border-gray-300 bg-transparent px-3 py-2.5 text-sm dark:border-gray-700">
                    <option value="">Todos</option>
                    @foreach ($usuarios as $usuario)
                        <option value="{{ $usuario->id }}" @selected(request('usuario_id') == $usuario->id)>{{ $usuario->name }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <button class="h-11 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">Aplicar</button>
        <a href="{{ route('relatorios.movimentacoes.pdf', request()->all()) }}" class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">⬇ PDF</a>
    </form>

    @if (in_array($tipo, ['consolidado', 'por_caixa']))
        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:bg-white/[0.02] dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Caixa/terminal</th>
                        <th class="px-4 py-3 text-right">Saldo inicial</th>
                        <th class="px-4 py-3 text-right">Entradas</th>
                        <th class="px-4 py-3 text-right">Saídas</th>
                        @if ($tipo === 'por_caixa')
                            <th class="px-4 py-3 text-right">Transf. recebidas</th>
                            <th class="px-4 py-3 text-right">Transf. enviadas</th>
                            <th class="px-4 py-3 text-right">Ajustes</th>
                        @endif
                        <th class="px-4 py-3 text-right">Saldo final</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($porCaixa as $linha)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-white/90">{{ $linha['caixa']->terminal->nome ?? $linha['caixa']->unidade->nome }}</td>
                            <td class="px-4 py-3 text-right text-slate-500">R$ {{ number_format($linha['saldo_inicial'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-emerald-600">+ R$ {{ number_format($linha['entradas'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-rose-600">- R$ {{ number_format($linha['saidas'], 2, ',', '.') }}</td>
                            @if ($tipo === 'por_caixa')
                                <td class="px-4 py-3 text-right text-slate-500">R$ {{ number_format($linha['transferencias_recebidas'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-slate-500">R$ {{ number_format($linha['transferencias_enviadas'], 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-slate-500">R$ {{ number_format($linha['ajustes'], 2, ',', '.') }}</td>
                            @endif
                            <td class="px-4 py-3 text-right font-semibold">R$ {{ number_format($linha['saldo_final'], 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $tipo === 'por_caixa' ? 8 : 5 }}" class="px-4 py-8 text-center text-slate-400">Nenhum caixa no período.</td></tr>
                    @endforelse
                </tbody>
                @if ($porCaixa->isNotEmpty())
                    <tfoot class="border-t-2 border-gray-200 bg-gray-50 text-sm font-semibold dark:border-gray-800 dark:bg-white/[0.02]">
                        <tr>
                            <td class="px-4 py-3">Total geral</td>
                            <td class="px-4 py-3 text-right">R$ {{ number_format($porCaixa->sum('saldo_inicial'), 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-emerald-600">R$ {{ number_format($porCaixa->sum('entradas'), 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-rose-600">R$ {{ number_format($porCaixa->sum('saidas'), 2, ',', '.') }}</td>
                            @if ($tipo === 'por_caixa')
                                <td class="px-4 py-3 text-right">R$ {{ number_format($porCaixa->sum('transferencias_recebidas'), 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">R$ {{ number_format($porCaixa->sum('transferencias_enviadas'), 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right">R$ {{ number_format($porCaixa->sum('ajustes'), 2, ',', '.') }}</td>
                            @endif
                            <td class="px-4 py-3 text-right">R$ {{ number_format($porCaixa->sum('saldo_final'), 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    @endif

    @if (in_array($tipo, ['entradas', 'saidas']))
        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:bg-white/[0.02] dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Data</th>
                        <th class="px-4 py-3">Descrição</th>
                        <th class="px-4 py-3">Categoria</th>
                        <th class="px-4 py-3">Caixa/terminal</th>
                        <th class="px-4 py-3">Usuário</th>
                        <th class="px-4 py-3 text-right">Valor</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($movimentacoes['itens'] as $mov)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-slate-500">{{ $mov->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $mov->descricao }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ \App\Support\Financeiro::labelCategoria($mov->tipo, $mov->categoria) }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $mov->caixa->terminal->nome ?? $mov->caixa->unidade->nome }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $mov->usuario->name }}</td>
                            <td class="px-4 py-3 text-right font-medium {{ $tipo === 'saidas' ? 'text-rose-600' : 'text-emerald-600' }}">R$ {{ number_format($mov->valor, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Nenhuma movimentação no período.</td></tr>
                    @endforelse
                </tbody>
                @if ($movimentacoes['itens']->isNotEmpty())
                    <tfoot class="border-t-2 border-gray-200 bg-gray-50 text-sm font-semibold dark:border-gray-800 dark:bg-white/[0.02]">
                        <tr><td colspan="5" class="px-4 py-3">Total</td><td class="px-4 py-3 text-right">R$ {{ number_format($movimentacoes['total'], 2, ',', '.') }}</td></tr>
                    </tfoot>
                @endif
            </table>
        </div>
    @endif

    @if ($tipo === 'fluxo')
        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:bg-white/[0.02] dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Data</th>
                        <th class="px-4 py-3 text-right">Entradas</th>
                        <th class="px-4 py-3 text-right">Saídas</th>
                        <th class="px-4 py-3 text-right">Saldo do dia</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($fluxo as $dia)
                        <tr>
                            <td class="px-4 py-3 text-slate-500">{{ $dia['data']->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-right text-emerald-600">R$ {{ number_format($dia['entradas'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-rose-600">R$ {{ number_format($dia['saidas'], 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right font-medium {{ $dia['saldo'] < 0 ? 'text-rose-600' : 'text-slate-800 dark:text-white/90' }}">R$ {{ number_format($dia['saldo'], 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Período inválido.</td></tr>
                    @endforelse
                </tbody>
                @if ($fluxo->isNotEmpty())
                    <tfoot class="border-t-2 border-gray-200 bg-gray-50 text-sm font-semibold dark:border-gray-800 dark:bg-white/[0.02]">
                        <tr>
                            <td class="px-4 py-3">Total do período</td>
                            <td class="px-4 py-3 text-right text-emerald-600">R$ {{ number_format($fluxo->sum('entradas'), 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right text-rose-600">R$ {{ number_format($fluxo->sum('saidas'), 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">R$ {{ number_format($fluxo->sum('saldo'), 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    @endif
@endsection
