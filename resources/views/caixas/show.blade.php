@extends('layouts.app')

@section('titulo', 'Caixa #'.$caixa->id)

@section('conteudo')
    @if (session('caixa_recem_fechado') === $caixa->id)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border-2 border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
            <span>Caixa fechado! Confira o resumo e imprima o comprovante de fechamento.</span>
            <a href="{{ route('caixas.pdf', $caixa) }}" target="_blank" class="shrink-0 rounded-lg bg-emerald-600 px-4 py-2 font-semibold text-white hover:bg-emerald-700">Abrir para impressão</a>
        </div>
    @endif

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div>
            <h2 class="text-xl font-bold text-slate-800">{{ $caixa->terminal->nome ?? $caixa->unidade->nome }}</h2>
            <p class="text-sm text-slate-500">{{ $caixa->unidade->nome }} · Aberto por {{ $caixa->usuarioAbertura->name }} em {{ $caixa->data_abertura->format('d/m/Y H:i') }} · <x-status-badge :status="$caixa->status" /></p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('caixas.pdf', $caixa) }}" target="_blank" class="inline-flex h-10 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Ver/imprimir PDF do caixa</a>

            @if ($caixa->estaAberto())
                @can('fechar', $caixa)
                    <form method="POST" action="{{ route('caixas.fechar', $caixa) }}" class="flex gap-2" onsubmit="return confirm('Fechar este caixa?')">
                        @csrf
                        <input type="number" step="0.01" name="valor_fechamento_informado" required placeholder="Valor contado (R$)" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <button class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white hover:bg-rose-700">Fechar caixa</button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    @if ($caixa->estaAberto())
        @can('registrarMovimentacao', $caixa)
            <form method="POST" action="{{ route('caixas.movimentar', $caixa) }}" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                @csrf
                <select name="tipo" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="entrada">Entrada</option>
                    <option value="saida">Saída</option>
                </select>
                <input type="text" name="categoria" placeholder="Categoria (ex: sangria, suprimento)" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input type="text" name="descricao" placeholder="Descrição" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input type="number" step="0.01" name="valor" placeholder="Valor" required class="w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Registrar</button>
            </form>
        @endcan
    @endif

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-3 text-sm font-semibold text-slate-700">Resumo</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-400">Abertura</dt><dd>R$ {{ number_format($caixa->valor_abertura, 2, ',', '.') }}</dd></div>
                @if (! $caixa->estaAberto())
                    <div class="flex justify-between"><dt class="text-slate-400">Sistema</dt><dd>R$ {{ number_format($caixa->valor_fechamento_sistema, 2, ',', '.') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Informado</dt><dd>R$ {{ number_format($caixa->valor_fechamento_informado, 2, ',', '.') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Diferença</dt><dd class="{{ $caixa->diferenca < 0 ? 'text-rose-600' : 'text-emerald-600' }}">R$ {{ number_format($caixa->diferenca, 2, ',', '.') }}</dd></div>
                @endif
            </dl>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:col-span-2">
            <h3 class="mb-3 text-sm font-semibold text-slate-700">Movimentações</h3>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-slate-50">
                    @forelse ($caixa->movimentacoes as $mov)
                        <tr>
                            <td class="py-2">{{ $mov->categoria }}</td>
                            <td class="py-2 text-slate-500">{{ $mov->descricao }}</td>
                            <td class="py-2 text-slate-500">{{ $mov->usuario->name }}</td>
                            <td class="py-2 text-right font-medium {{ $mov->tipo === 'saida' ? 'text-rose-600' : 'text-emerald-600' }}">
                                {{ $mov->tipo === 'saida' ? '-' : '+' }} R$ {{ number_format($mov->valor, 2, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr><td class="py-6 text-center text-slate-400">Nenhuma movimentação ainda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
