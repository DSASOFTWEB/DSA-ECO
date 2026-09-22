@extends('layouts.app')

@section('titulo', 'Mensalidades')

@section('conteudo')
    @php
        $podeCobrarLote = auth()->user()?->can('financeiro.enviar_cobranca');
        $podeAlterarVencimentoLote = auth()->user()?->can('contratos.editar');
        $usaSelecao = $podeCobrarLote || $podeAlterarVencimentoLote;
        $idsSelecionaveis = $mensalidades->filter(fn ($m) => in_array($m->status, ['pendente', 'atrasado'], true))->pluck('id');
    @endphp

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <a href="{{ route('mensalidades.index', ['status' => 'pendente']) }}" class="overflow-hidden rounded-2xl border border-amber-300 bg-amber-100 p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-amber-500 hover:shadow-md dark:border-amber-700 dark:bg-amber-500/[0.15] dark:hover:border-amber-500/60">
            <div class="-mx-5 -mt-5 mb-4 h-1.5 bg-amber-500"></div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-300">Pendentes</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-amber-700 dark:text-amber-400">{{ $resumo['pendentes'] }}</p>
        </a>
        <a href="{{ route('mensalidades.index', ['status' => 'pago']) }}" class="overflow-hidden rounded-2xl border border-emerald-300 bg-emerald-100 p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-500 hover:shadow-md dark:border-emerald-700 dark:bg-emerald-500/[0.15] dark:hover:border-emerald-500/60">
            <div class="-mx-5 -mt-5 mb-4 h-1.5 bg-emerald-500"></div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-300">Em dia</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-emerald-700 dark:text-emerald-400">{{ $resumo['em_dia'] }}</p>
        </a>
        <a href="{{ route('mensalidades.index', ['status' => 'atrasado']) }}" class="overflow-hidden rounded-2xl border border-rose-300 bg-rose-100 p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-rose-500 hover:shadow-md dark:border-rose-700 dark:bg-rose-500/[0.15] dark:hover:border-rose-500/60">
            <div class="-mx-5 -mt-5 mb-4 h-1.5 bg-rose-500"></div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-300">Atrasadas</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-rose-700 dark:text-rose-400">{{ $resumo['atrasadas'] }}</p>
        </a>
    </div>

    <form method="GET" class="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <select name="status" class="rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 outline-none transition focus:border-brand-500 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
            <option value="">Todos os status</option>
            @foreach (['pendente', 'pago', 'atrasado', 'cancelado', 'isento'] as $opcao)
                <option value="{{ $opcao }}" @selected(request('status') === $opcao)>{{ ucfirst($opcao) }}</option>
            @endforeach
        </select>
        <input type="month" name="competencia" value="{{ request('competencia') }}" class="rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 outline-none transition focus:border-brand-500 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
        <button class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs transition hover:bg-brand-600">Filtrar</button>
    </form>

    <div
        @if ($usaSelecao)
            x-data="{
                selecionadas: [],
                novaData: '',
                todasNaPagina: @js($idsSelecionaveis->values()),
                get todasMarcadas() { return this.todasNaPagina.length > 0 && this.todasNaPagina.every(id => this.selecionadas.includes(id)) },
                alternarTodas() { this.selecionadas = this.todasMarcadas ? [] : [...this.todasNaPagina]; },
                enviarLote() {
                    if (! this.selecionadas.length) return;
                    if (! confirm('Enviar cobrança por WhatsApp para ' + this.selecionadas.length + ' cliente(s)?')) return;
                    document.getElementById('campo-ids-lote').value = this.selecionadas.join(',');
                    document.getElementById('form-cobrar-lote').submit();
                },
                alterarVencimentoLote() {
                    if (! this.selecionadas.length) return;
                    if (! this.novaData) { alert('Informe a nova data de vencimento.'); return; }
                    if (! confirm('Alterar o vencimento de ' + this.selecionadas.length + ' cobrança(s) para ' + this.novaData.split('-').reverse().join('/') + '?')) return;
                    document.getElementById('campo-ids-venc-lote').value = this.selecionadas.join(',');
                    document.getElementById('campo-data-venc-lote').value = this.novaData;
                    document.getElementById('form-vencimento-lote').submit();
                }
            }"
        @endif
    >
        @if ($podeCobrarLote)
            <form id="form-cobrar-lote" method="POST" action="{{ route('mensalidades.cobrar-whatsapp-lote') }}" class="hidden">
                @csrf
                <input type="hidden" name="ids" id="campo-ids-lote">
            </form>
        @endif

        @if ($podeAlterarVencimentoLote)
            <form id="form-vencimento-lote" method="POST" action="{{ route('mensalidades.alterar-vencimento-lote') }}" class="hidden">
                @csrf
                <input type="hidden" name="ids" id="campo-ids-venc-lote">
                <input type="hidden" name="data_vencimento" id="campo-data-venc-lote">
            </form>
        @endif

        @if ($usaSelecao)
            <div class="mb-3 flex flex-wrap items-end justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <span class="text-sm text-slate-500 dark:text-slate-400">
                    <span x-text="selecionadas.length"></span> selecionada(s)
                </span>
                <div class="flex flex-wrap items-end gap-2">
                    @if ($podeAlterarVencimentoLote)
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Novo vencimento</label>
                            <input type="date" x-model="novaData" class="h-10 rounded-lg border border-gray-300 bg-white px-3 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>
                        <button type="button" @click="alterarVencimentoLote()" :disabled="!selecionadas.length || !novaData"
                            class="inline-flex h-10 items-center rounded-lg border border-amber-300 bg-amber-50 px-4 text-sm font-medium text-amber-900 transition hover:bg-amber-100 disabled:cursor-not-allowed disabled:opacity-40 dark:border-amber-700 dark:bg-amber-500/10 dark:text-amber-200">
                            Alterar vencimento
                        </button>
                    @endif
                    @if ($podeCobrarLote)
                        <button type="button" @click="enviarLote()" :disabled="!selecionadas.length"
                            class="inline-flex h-10 items-center gap-2 rounded-lg bg-emerald-600 px-4 text-sm font-medium text-white shadow-theme-xs transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-40">
                            Cobrar WhatsApp
                        </button>
                    @endif
                </div>
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="max-w-full overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                    <tr>
                        @if ($usaSelecao)
                            <th class="w-10 px-4 py-3">
                                <input type="checkbox" :checked="todasMarcadas" @change="alternarTodas()" class="rounded border-gray-300">
                            </th>
                        @endif
                        <th class="px-4 py-3">Cliente</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Competência</th>
                        <th class="px-4 py-3">Vencimento</th>
                        <th class="px-4 py-3">Valor</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($mensalidades as $mensalidade)
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                            @if ($usaSelecao)
                                <td class="px-4 py-3">
                                    @if (in_array($mensalidade->status, ['pendente', 'atrasado'], true))
                                        <input type="checkbox" value="{{ $mensalidade->id }}" x-model.number="selecionadas" class="rounded border-gray-300">
                                    @endif
                                </td>
                            @endif
                            <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ $mensalidade->contrato->cliente->nome }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $mensalidade->ehCaucao() ? 'Caução' : 'Mensalidade' }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $mensalidade->competencia->format('m/Y') }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $mensalidade->data_vencimento->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-slate-500 dark:text-slate-400">R$ {{ number_format($mensalidade->valor_total, 2, ',', '.') }}</td>
                            <td class="px-4 py-3"><x-status-badge :status="$mensalidade->status" /></td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                @can('cobrar', $mensalidade)
                                    @if ($mensalidade->status !== 'pago')
                                        <form method="POST" action="{{ route('mensalidades.cobrar-whatsapp', $mensalidade) }}" class="inline" onsubmit="return confirm('Enviar cobrança por WhatsApp para {{ addslashes($mensalidade->contrato->cliente->nome) }}?');">
                                            @csrf
                                            <button type="submit" class="mr-3 text-emerald-700 hover:underline dark:text-emerald-400">Cobrar</button>
                                        </form>
                                    @endif
                                @endcan
                                <a href="{{ route('mensalidades.show', $mensalidade) }}" class="text-sky-700 hover:underline dark:text-sky-400">Ver</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $usaSelecao ? 8 : 7 }}" class="px-4 py-8 text-center text-slate-400">Nenhuma cobrança encontrada.</td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </div>
    </div>

    <div class="mt-4">{{ $mensalidades->links() }}</div>
@endsection
