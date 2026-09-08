@extends('layouts.app')

@section('titulo', 'Cortesias')

@section('conteudo')
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="overflow-hidden rounded-2xl border border-violet-300 bg-violet-100 p-5 shadow-sm dark:border-violet-700 dark:bg-violet-500/[0.15]">
            <div class="-mx-5 -mt-5 mb-4 h-1.5 bg-violet-500"></div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Cortesias este mês</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-violet-700 dark:text-violet-400">{{ $contadorMes['emitidas'] }}</p>
        </div>
        <div class="overflow-hidden rounded-2xl border border-emerald-300 bg-emerald-100 p-5 shadow-sm dark:border-emerald-700 dark:bg-emerald-500/[0.15]">
            <div class="-mx-5 -mt-5 mb-4 h-1.5 bg-emerald-500"></div>
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Já validadas (mês)</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-emerald-700 dark:text-emerald-400">{{ $contadorMes['validadas'] }}</p>
        </div>
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Total histórico emitido</p>
            <p class="mt-2 text-3xl font-bold tracking-tight text-gray-800 dark:text-white/90">{{ $contadorTotal['emitidas'] }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $contadorTotal['pendentes'] }} ainda não usada(s)</p>
        </div>
    </div>

    <div class="max-w-xl rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
         x-data="{
            clienteSelecionado: null,
            termo: '',
            resultados: [],
            buscarCliente() {
                if (this.termo.trim().length < 2) { this.resultados = []; return; }
                fetch('{{ route('clientes.buscar') }}?termo=' + encodeURIComponent(this.termo))
                    .then(r => r.json())
                    .then(dados => { this.resultados = dados; });
            },
            selecionarCliente(cliente) {
                this.clienteSelecionado = cliente;
                this.resultados = [];
                this.termo = '';
            }
         }">
        <h2 class="mb-1 text-lg font-semibold text-gray-800 dark:text-white/90">Emitir entrada de cortesia</h2>
        <p class="mb-5 text-sm text-slate-500">Não gera venda nem entra no caixa. Cada entrada sai com QR próprio, validado na portaria como qualquer voucher.</p>

        <form method="POST" action="{{ route('cortesias.store') }}" class="space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-slate-700">Tipo de entrada (opcional)</label>
                <select name="tipo_entrada_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    <option value="">— não especificado —</option>
                    @foreach ($tiposEntrada as $tipo)
                        <option value="{{ $tipo->id }}" @selected(old('tipo_entrada_id') == $tipo->id)>{{ $tipo->nome }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex flex-wrap gap-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Quantidade</label>
                    <input type="number" name="quantidade" min="1" max="50" value="{{ old('quantidade', 1) }}" required class="mt-1 w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Validade (dias)</label>
                    <input type="number" name="validade_dias" min="1" max="365" value="{{ old('validade_dias') }}" placeholder="Sem prazo" class="mt-1 w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    <p class="mt-1 text-xs text-slate-400">Deixe em branco para não expirar.</p>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Cliente (opcional)</label>
                <template x-if="clienteSelecionado">
                    <div class="mt-1 flex items-center justify-between rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700">
                        <span x-text="clienteSelecionado.nome"></span>
                        <button type="button" class="text-xs text-rose-600 hover:underline" @click="clienteSelecionado = null">trocar</button>
                    </div>
                </template>
                <div class="relative mt-1" x-show="!clienteSelecionado">
                    <input type="text" x-model="termo" @input.debounce.300ms="buscarCliente()" placeholder="Buscar por nome ou CPF (opcional)..."
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    <ul x-show="resultados.length" class="absolute z-10 mt-1 w-full divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white shadow-lg dark:divide-gray-800 dark:border-gray-700 dark:bg-gray-800">
                        <template x-for="cliente in resultados" :key="cliente.id">
                            <li @click="selecionarCliente(cliente)" class="cursor-pointer px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-white/[0.03]">
                                <span x-text="cliente.nome"></span> <span class="text-slate-400" x-text="cliente.cpf"></span>
                            </li>
                        </template>
                    </ul>
                </div>
                <input type="hidden" name="cliente_id" :value="clienteSelecionado?.id ?? ''">
                <p class="mt-1 text-xs text-slate-400">Deixe em branco se for para alguém sem cadastro (ex.: convidado).</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Observação</label>
                <input type="text" name="observacao" maxlength="255" value="{{ old('observacao') }}" placeholder="Ex.: Aniversário do João, parceria com hotel X..."
                       class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                <p class="mt-1 text-xs text-slate-400">Aparece no voucher quando não há cliente selecionado.</p>
            </div>

            <div class="flex justify-end">
                <button class="h-11 rounded-lg bg-violet-600 px-5 text-sm font-semibold text-white shadow-theme-xs hover:bg-violet-700">Gerar cortesia(s)</button>
            </div>
        </form>
    </div>

    <div class="mt-8">
        <h2 class="mb-3 text-lg font-semibold text-gray-800 dark:text-white/90">Cortesias emitidas</h2>

        <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <select name="status" class="rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-700 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                <option value="">Todos os status</option>
                <option value="pendente" @selected(request('status') === 'pendente')>Pendentes (ainda válidas)</option>
                <option value="validada" @selected(request('status') === 'validada')>Já validadas (usadas)</option>
            </select>
            <button class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">Filtrar</button>
        </form>

        <div
            x-data="{
                selecionadas: [],
                todasNaPagina: [{{ $cortesias->filter(fn ($c) => ! $c->jaValidado() && ! $c->estaExpirado())->pluck('id')->implode(',') }}],
                get todasMarcadas() { return this.todasNaPagina.length > 0 && this.todasNaPagina.every(id => this.selecionadas.includes(id)) },
                alternarTodas() { this.selecionadas = this.todasMarcadas ? [] : [...this.todasNaPagina]; },
                reimprimirSelecionadas() {
                    if (! this.selecionadas.length) return;
                    window.open('{{ route('cortesias.voucher') }}?acessos=' + this.selecionadas.join(','), '_blank');
                }
            }"
        >
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <span class="text-sm text-slate-500 dark:text-slate-400"><span x-text="selecionadas.length"></span> selecionada(s)</span>
                <button type="button" @click="reimprimirSelecionadas()" :disabled="!selecionadas.length"
                    class="inline-flex items-center gap-2 rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white shadow-theme-xs transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-40">
                    Reimprimir selecionadas (PDF)
                </button>
            </div>

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="max-w-full overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                        <tr>
                            <th class="w-10 px-4 py-3">
                                <input type="checkbox" :checked="todasMarcadas" @change="alternarTodas()" class="rounded border-gray-300">
                            </th>
                            <th class="px-4 py-3">Para</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3">Unidade</th>
                            <th class="px-4 py-3">Emitida em</th>
                            <th class="px-4 py-3">Válido até</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($cortesias as $cortesia)
                            <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                                <td class="px-4 py-3">
                                    @unless ($cortesia->jaValidado() || $cortesia->estaExpirado())
                                        <input type="checkbox" value="{{ $cortesia->id }}" x-model.number="selecionadas" class="rounded border-gray-300">
                                    @endunless
                                </td>
                                <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">
                                    {{ $cortesia->nomeTitular() ?? 'Convidado' }}
                                    @if ($cortesia->observacao)
                                        <p class="text-xs font-normal text-slate-400">{{ $cortesia->observacao }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $cortesia->tipoEntrada?->nome ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $cortesia->unidade?->nome ?? '—' }}</td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $cortesia->registrado_em?->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $cortesia->validade_ate?->format('d/m/Y') ?? 'Sem prazo' }}</td>
                                <td class="px-4 py-3">
                                    @if ($cortesia->jaValidado())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500 dark:bg-white/5 dark:text-gray-400">Usada em {{ $cortesia->validado_em->format('d/m/Y H:i') }}</span>
                                    @elseif ($cortesia->estaExpirado())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">Expirada</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-400">● Válida</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @unless ($cortesia->jaValidado() || $cortesia->estaExpirado())
                                        <a href="{{ route('cortesias.voucher', ['acessos' => $cortesia->id]) }}" target="_blank" class="text-violet-700 hover:underline dark:text-violet-400">Reimprimir</a>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">Nenhuma cortesia emitida ainda.</td></tr>
                        @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>

        <div class="mt-4">{{ $cortesias->links() }}</div>
    </div>
@endsection
