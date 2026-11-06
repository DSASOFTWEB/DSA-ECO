@extends('layouts.app')

@section('titulo', 'Caixa #'.$caixa->id)

@section('conteudo')
    @if (session('caixa_recem_fechado') === $caixa->id)
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border-2 border-emerald-300 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
            <span>Caixa fechado! Confira o resumo e imprima o comprovante de fechamento.</span>
            <span class="flex shrink-0 flex-wrap gap-2">
                <a href="{{ route('caixas.bobina', $caixa) }}" class="rounded-lg bg-emerald-600 px-4 py-2 font-semibold text-white hover:bg-emerald-700">🧾 Imprimir na bobina 80mm</a>
                <a href="{{ route('caixas.pdf', $caixa) }}" target="_blank" class="rounded-lg border border-emerald-400 px-4 py-2 font-semibold text-emerald-800 hover:bg-emerald-100 dark:text-emerald-300">PDF A4</a>
            </span>
        </div>
    @endif

    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div>
            <h2 class="text-xl font-bold text-slate-800">{{ $caixa->terminal->nome ?? $caixa->unidade->nome }}</h2>
            <p class="text-sm text-slate-500">{{ $caixa->unidade->nome }} · Aberto por {{ $caixa->usuarioAbertura->name }} em {{ $caixa->data_abertura->format('d/m/Y H:i') }} · <x-status-badge :status="$caixa->status" /></p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('caixas.bobina', $caixa) }}" class="inline-flex h-10 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">🧾 {{ $caixa->estaAberto() ? 'Parcial na bobina 80mm' : 'Fechamento na bobina 80mm' }}</a>
            <a href="{{ route('caixas.pdf', $caixa) }}" target="_blank" class="inline-flex h-10 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Ver/imprimir PDF do caixa</a>

            @if ($caixa->estaAberto())
                @can('registrarMovimentacao', $caixa)
                    <button type="button" @click="$dispatch('abrir-sangria', 'sangria')" class="inline-flex h-10 items-center rounded-lg border border-rose-300 bg-rose-50 px-4 text-sm font-semibold text-rose-700 hover:bg-rose-100 dark:border-rose-700 dark:bg-rose-500/10 dark:text-rose-300">− Sangria</button>
                    <button type="button" @click="$dispatch('abrir-sangria', 'suprimento')" class="inline-flex h-10 items-center rounded-lg border border-emerald-300 bg-emerald-50 px-4 text-sm font-semibold text-emerald-700 hover:bg-emerald-100 dark:border-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">+ Suprimento</button>
                @endcan
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
            @include('caixas.partials.sangria-suprimento', ['caixa' => $caixa, 'recarregar' => true])
            <form method="POST" action="{{ route('caixas.movimentar', $caixa) }}" x-data="{ tipo: 'entrada' }" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                @csrf
                <select name="tipo" x-model="tipo" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="entrada">Entrada</option>
                    <option value="saida">Saída</option>
                </select>
                <select name="categoria" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <template x-if="tipo === 'entrada'">
                        <optgroup label="Entrada">
                            @foreach (\App\Support\Financeiro::CATEGORIAS_ENTRADA as $chave => $label)
                                <option value="{{ $chave }}">{{ $label }}</option>
                            @endforeach
                        </optgroup>
                    </template>
                    <template x-if="tipo === 'saida'">
                        <optgroup label="Saída">
                            @foreach (\App\Support\Financeiro::CATEGORIAS_SAIDA as $chave => $label)
                                <option value="{{ $chave }}">{{ $label }}</option>
                            @endforeach
                        </optgroup>
                    </template>
                </select>
                <input type="text" name="descricao" placeholder="Descrição" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="forma_pagamento" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Forma de pagamento</option>
                    @foreach (\App\Support\Financeiro::FORMAS_PAGAMENTO as $chave => $forma)
                        <option value="{{ $chave }}">{{ $forma['label'] }}</option>
                    @endforeach
                </select>
                <input type="number" step="0.01" name="valor" placeholder="Valor" required class="w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button class="rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Registrar</button>
            </form>
        @endcan
    @endif

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            @php $brl = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.'); @endphp
            <h3 class="mb-3 text-sm font-semibold text-slate-700 dark:text-white/90">Resumo</h3>

            {{-- Recebido por forma de pagamento (pizza em CSS: conic-gradient) --}}
            <div class="flex items-center gap-4">
                <div class="relative h-32 w-32 shrink-0 rounded-full" style="background: {{ \App\Services\Relatorios\CaixaFechamentoResumo::gradientePizza($resumo['recebimentos']) }}"
                     role="img" aria-label="Recebido por forma de pagamento">
                    <div style="inset: 22%" class="absolute flex flex-col items-center justify-center rounded-full bg-white text-center dark:bg-gray-900">
                        <span class="text-[10px] uppercase tracking-wide text-slate-400">Recebido</span>
                        <span class="text-sm font-bold text-slate-800 dark:text-white">{{ $brl($resumo['recebido_total']) }}</span>
                    </div>
                </div>
                <ul class="min-w-0 flex-1 space-y-1.5 text-sm">
                    @forelse ($resumo['recebimentos'] as $r)
                        <li class="flex items-center gap-2">
                            <span class="h-3 w-3 shrink-0" style="background: {{ $r['cor'] }}; border-radius: 3px"></span>
                            <span class="min-w-0 flex-1 truncate text-slate-600 dark:text-gray-300">{{ $r['forma'] }}</span>
                            <span class="whitespace-nowrap font-semibold text-slate-800 dark:text-white">{{ $brl($r['total']) }}</span>
                            <span class="text-right text-xs text-slate-400" style="width: 2.75rem">{{ number_format($r['percentual'], 0) }}%</span>
                        </li>
                    @empty
                        <li class="text-slate-400">Nenhum recebimento ainda.</li>
                    @endforelse
                </ul>
            </div>

            <dl class="mt-5 space-y-2 border-t border-slate-100 pt-4 text-sm dark:border-gray-800">
                <div class="flex justify-between"><dt class="text-slate-400">Abertura (troco)</dt><dd>{{ $brl($caixa->valor_abertura) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">(+) Entradas</dt><dd class="text-emerald-600">{{ $brl($resumo['entradas']) }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-400">(−) Saídas</dt><dd class="text-rose-600">{{ $brl($resumo['saidas']) }}</dd></div>
                <div class="flex justify-between font-semibold"><dt class="text-slate-600 dark:text-gray-300">{{ $caixa->estaAberto() ? 'Saldo até agora' : 'Saldo do sistema' }}</dt><dd>{{ $brl($resumo['saldo']) }}</dd></div>
                <div class="flex justify-between rounded-lg bg-emerald-50 px-2 py-1.5 font-semibold text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300">
                    <dt>💵 Dinheiro na gaveta</dt><dd>{{ $brl($resumo['dinheiro_gaveta']) }}</dd>
                </div>
                @if (! $caixa->estaAberto())
                    <div class="flex justify-between"><dt class="text-slate-400">Informado</dt><dd>R$ {{ number_format($caixa->valor_fechamento_informado, 2, ',', '.') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-400">Diferença</dt><dd class="{{ $caixa->diferenca < 0 ? 'text-rose-600' : 'text-emerald-600' }}">R$ {{ number_format($caixa->diferenca, 2, ',', '.') }}</dd></div>
                @endif
            </dl>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:col-span-2">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-slate-700">Movimentações</h3>
                @can('viewAny', App\Models\CaixaMovimentacao::class)
                    <a href="{{ route('movimentacoes.index', ['caixa_id' => $caixa->id]) }}" class="text-xs font-medium text-sky-600 hover:underline">Ver na tela de movimentações →</a>
                @endcan
            </div>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-slate-50">
                    @forelse ($caixa->movimentacoes as $mov)
                        <tr>
                            <td class="py-2">{{ \App\Support\Financeiro::labelCategoria($mov->tipo, $mov->categoria) }}</td>
                            <td class="py-2 text-slate-500">{{ $mov->descricao }}</td>
                            <td class="py-2 text-slate-500">{{ $mov->usuario->name }}</td>
                            <td class="py-2 text-right font-medium {{ $mov->tipo === 'saida' ? 'text-rose-600' : 'text-emerald-600' }}">
                                {{ $mov->tipo === 'saida' ? '-' : '+' }} R$ {{ number_format($mov->valor, 2, ',', '.') }}
                            </td>
                            <td class="py-2 pl-3 text-right">
                                @if (in_array($mov->categoria, ['sangria', 'suprimento'], true))
                                    <a href="{{ route('caixas.movimentacoes.comprovante', [$caixa, $mov]) }}" target="comprovante-caixa" class="mr-2 text-xs text-sky-600 hover:underline">🧾 Comprovante</a>
                                @endif
                                @if ($mov->estaEstornada())
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">Estornada</span>
                                @elseif ($mov->podeEstornar())
                                    @can('estornar', $mov)
                                        <form method="POST" action="{{ route('movimentacoes.estornar', $mov) }}" onsubmit="return confirm('Estornar este lançamento?')">
                                            @csrf
                                            <button class="text-xs text-rose-600 hover:underline">Estornar</button>
                                        </form>
                                    @endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-slate-400">Nenhuma movimentação ainda.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
