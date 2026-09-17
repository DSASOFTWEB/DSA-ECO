@extends('layouts.app')

@section('titulo', 'Mapa de Quartos')

@php
    $cores = [
        'disponivel' => ['borda' => 'border-emerald-300 dark:border-emerald-700', 'topo' => 'bg-emerald-500', 'texto' => 'text-emerald-700 dark:text-emerald-400', 'label' => 'Disponível'],
        'ocupado' => ['borda' => 'border-rose-300 dark:border-rose-700', 'topo' => 'bg-rose-500', 'texto' => 'text-rose-700 dark:text-rose-400', 'label' => 'Ocupado'],
        'reservado' => ['borda' => 'border-sky-300 dark:border-sky-700', 'topo' => 'bg-sky-500', 'texto' => 'text-sky-700 dark:text-sky-400', 'label' => 'Reservado'],
        'em_limpeza' => ['borda' => 'border-amber-300 dark:border-amber-700', 'topo' => 'bg-amber-500', 'texto' => 'text-amber-700 dark:text-amber-400', 'label' => 'Em limpeza'],
        'bloqueado' => ['borda' => 'border-gray-400 dark:border-gray-600', 'topo' => 'bg-gray-500', 'texto' => 'text-gray-600 dark:text-gray-400', 'label' => 'Bloqueado'],
    ];
    $contagens = $mapa->countBy('status');
@endphp

@section('conteudo')
    <div x-data="{
            quarto: @js($quartoAnterior),
            clienteSelecionado: @js($clienteAnterior ? ['id' => $clienteAnterior->id, 'nome' => $clienteAnterior->nome, 'cpf' => $clienteAnterior->cpf] : null),
            termo: '',
            resultados: [],
            buscando: false,
            salvandoCliente: false,
            clienteErros: {},
            novoCliente: { nome: '', cpf: '', data_nascimento: '', telefone: '', whatsapp: '', unidade_id: '' },
            abrirReserva(quarto) {
                this.quarto = quarto;
                this.$dispatch('open-modal', 'reserva-hospedagem');
                this.$nextTick(() => this.$refs.buscaCliente?.focus());
            },
            buscarCliente() {
                if (this.termo.trim().length < 2) { this.resultados = []; return; }
                this.buscando = true;
                fetch('{{ route('clientes.buscar') }}?termo=' + encodeURIComponent(this.termo), { headers: { 'Accept': 'application/json' } })
                    .then(response => response.ok ? response.json() : Promise.reject())
                    .then(dados => { this.resultados = dados; })
                    .catch(() => { this.resultados = []; })
                    .finally(() => { this.buscando = false; });
            },
            selecionarCliente(cliente) {
                this.clienteSelecionado = cliente;
                this.resultados = [];
                this.termo = '';
            },
            cadastrarCliente(event) {
                this.salvandoCliente = true;
                this.clienteErros = {};

                fetch('{{ route('clientes.store') }}', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: new FormData(event.target),
                })
                    .then(async response => {
                        const dados = await response.json();
                        if (!response.ok) {
                            if (response.status === 422) this.clienteErros = dados.errors ?? {};
                            throw new Error(dados.message ?? 'Não foi possível cadastrar o cliente.');
                        }
                        return dados;
                    })
                    .then(cliente => {
                        this.selecionarCliente(cliente);
                        this.novoCliente = { nome: '', cpf: '', data_nascimento: '', telefone: '', whatsapp: '', unidade_id: '' };
                        event.target.reset();
                        this.$dispatch('close-modal', 'novo-cliente');
                    })
                    .catch(erro => {
                        if (Object.keys(this.clienteErros).length === 0) this.clienteErros = { geral: [erro.message] };
                    })
                    .finally(() => { this.salvandoCliente = false; });
            }
        }"
        x-init="@if ($errors->any() && old('quarto_id')) $nextTick(() => $dispatch('open-modal', 'reserva-hospedagem')); @endif">
    <div class="mb-6 flex flex-wrap items-center gap-2">
        <a href="{{ route('hospedagens.mapa') }}" class="rounded-full border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 {{ ! request('status') ? 'bg-gray-100 dark:bg-white/10' : '' }}">Todos: {{ $mapa->count() }}</a>
        @foreach ($cores as $chave => $cor)
            <a href="{{ route('hospedagens.mapa', ['status' => $chave]) }}" class="rounded-full border px-3 py-1.5 text-xs font-medium {{ $cor['borda'] }} {{ $cor['texto'] }} {{ request('status') === $chave ? 'bg-gray-100 dark:bg-white/10' : '' }}">
                {{ $cor['label'] }}: {{ $contagens[$chave] ?? 0 }}
            </a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        @forelse ($mapa->when(request('status'), fn ($m) => $m->where('status', request('status'))) as $item)
            @php $cor = $cores[$item['status']]; @endphp
            <div class="overflow-hidden rounded-2xl border {{ $cor['borda'] }} bg-white shadow-sm dark:bg-gray-900">
                <div class="h-1.5 {{ $cor['topo'] }}"></div>
                <div class="p-4">
                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-sm font-bold text-slate-800 dark:text-white/90">Quarto {{ $item['quarto']->numero }}</span>
                        <span class="text-xs font-medium {{ $cor['texto'] }}">{{ $cor['label'] }}</span>
                    </div>
                    <p class="text-xs text-slate-400">{{ $item['quarto']->unidade->nome }}</p>

                    @if ($item['hospedagem'])
                        <p class="mt-3 truncate text-sm font-medium text-slate-700 dark:text-slate-200">{{ $item['hospedagem']->cliente->nome }}</p>
                        <p class="text-xs text-slate-400">{{ $item['hospedagem']->data_checkin_prevista->format('d/m/Y') }} — {{ $item['hospedagem']->data_checkout_prevista->format('d/m/Y') }}</p>
                    @else
                        <p class="mt-3 text-sm text-slate-300 dark:text-slate-600">—</p>
                    @endif

                    <div class="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-3 dark:border-gray-800">
                        @if ($item['status'] === 'ocupado')
                            <a href="{{ route('hospedagens.show', $item['hospedagem']) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-gray-700 dark:text-slate-300">Ver</a>
                            <a href="{{ route('hospedagens.ficha', $item['hospedagem']) }}" target="_blank" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-gray-700 dark:text-slate-300">🖨 Imprimir ficha</a>
                            @can('checkout', $item['hospedagem'])
                                <a href="{{ route('hospedagens.checkout', $item['hospedagem']) }}" class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Fechar conta</a>
                            @endcan
                        @elseif ($item['status'] === 'reservado')
                            <a href="{{ route('hospedagens.show', $item['hospedagem']) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-gray-700 dark:text-slate-300">Ver</a>
                            <a href="{{ route('hospedagens.ficha', $item['hospedagem']) }}" target="_blank" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-gray-700 dark:text-slate-300">🖨 Imprimir ficha</a>
                            @can('checkin', $item['hospedagem'])
                                <form method="POST" action="{{ route('hospedagens.checkin', $item['hospedagem']) }}">
                                    @csrf
                                    <button class="rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700">Check-in</button>
                                </form>
                            @endcan
                        @elseif ($item['status'] === 'em_limpeza')
                            @can('limpar', $item['quarto'])
                                <form method="POST" action="{{ route('quartos.limpar', $item['quarto']) }}" onsubmit="return confirm('Marcar este quarto como limpo?')">
                                    @csrf
                                    <button class="rounded-lg bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700">Concluir limpeza</button>
                                </form>
                            @endcan
                        @elseif ($item['status'] === 'disponivel')
                            @can('create', App\Models\Hospedagem::class)
                                <button type="button"
                                        @click="abrirReserva(@js([
                                            'id' => $item['quarto']->id,
                                            'numero' => $item['quarto']->numero,
                                            'unidade' => $item['quarto']->unidade->nome,
                                            'capacidade' => $item['quarto']->capacidade_maxima,
                                            'valor_diaria' => number_format($item['quarto']->valor_diaria, 2, ',', '.'),
                                        ]))"
                                        class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Reservar</button>
                            @endcan
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center text-slate-400">Nenhum quarto encontrado.</div>
        @endforelse
    </div>

    @can('create', App\Models\Hospedagem::class)
        <x-modal name="reserva-hospedagem" title="Nova reserva" max-width="2xl">
                <form method="POST" action="{{ route('hospedagens.store') }}" class="space-y-5">
                    @csrf
                    <input type="hidden" name="quarto_id" :value="quarto?.id ?? ''">

                    <div class="rounded-xl bg-slate-50 p-3 dark:bg-white/5">
                        <p class="font-semibold text-slate-800 dark:text-white">Quarto <span x-text="quarto?.numero"></span></p>
                        <p class="text-sm text-slate-500"><span x-text="quarto?.unidade"></span> · até <span x-text="quarto?.capacidade"></span> pessoas · R$ <span x-text="quarto?.valor_diaria"></span>/diária</p>
                    </div>

                    @if ($errors->any())
                        <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
                            Revise os campos destacados antes de concluir a reserva.
                        </div>
                    @endif

                    <div>
                        <div class="flex items-center justify-between gap-3">
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Hóspede titular</label>
                            @can('create', App\Models\Cliente::class)
                                <button type="button" @click="$dispatch('open-modal', 'novo-cliente')" class="inline-flex items-center gap-1 rounded-lg border border-sky-200 px-2.5 py-1 text-xs font-semibold text-sky-700 hover:bg-sky-50 dark:border-sky-800 dark:text-sky-400 dark:hover:bg-sky-500/10">
                                    <span class="text-base leading-none">+</span> Novo cliente
                                </button>
                            @endcan
                        </div>
                        <template x-if="clienteSelecionado">
                            <div class="mt-1 flex items-center justify-between rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700">
                                <span x-text="clienteSelecionado.nome"></span>
                                <button type="button" class="text-xs text-rose-600 hover:underline" @click="clienteSelecionado = null">Trocar</button>
                            </div>
                        </template>
                        <div class="relative mt-1" x-show="!clienteSelecionado">
                            <input x-ref="buscaCliente" type="search" x-model="termo" @input.debounce.300ms="buscarCliente()" placeholder="Buscar por nome ou CPF..." autocomplete="off" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            <p x-show="buscando" class="mt-1 text-xs text-slate-400">Buscando...</p>
                            <ul x-show="resultados.length" class="absolute z-20 mt-1 max-h-52 w-full overflow-y-auto divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white shadow-lg dark:divide-gray-700 dark:border-gray-700 dark:bg-gray-800">
                                <template x-for="cliente in resultados" :key="cliente.id">
                                    <li><button type="button" @click="selecionarCliente(cliente)" class="w-full px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-white/5"><span x-text="cliente.nome"></span> <span class="text-slate-400" x-text="cliente.cpf"></span></button></li>
                                </template>
                            </ul>
                        </div>
                        <input type="hidden" name="cliente_id" :value="clienteSelecionado?.id ?? '{{ old('cliente_id') }}'" required>
                        @error('cliente_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Check-in previsto</label>
                            <input type="date" name="data_checkin_prevista" value="{{ old('data_checkin_prevista', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            @error('data_checkin_prevista')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Check-out previsto</label>
                            <input type="date" name="data_checkout_prevista" value="{{ old('data_checkout_prevista') }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            @error('data_checkout_prevista')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Adultos</label>
                            <input type="number" name="quantidade_adultos" min="1" max="50" value="{{ old('quantidade_adultos', 1) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Crianças</label>
                            <input type="number" name="quantidade_criancas" min="0" max="50" value="{{ old('quantidade_criancas', 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Isentos</label>
                            <input type="number" name="quantidade_isentos" min="0" max="50" value="{{ old('quantidade_isentos', 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Observações</label>
                        <textarea name="observacoes" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">{{ old('observacoes') }}</textarea>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-gray-800">
                        <button type="button" @click="$dispatch('close-modal', 'reserva-hospedagem')" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5">Cancelar</button>
                        <button type="submit" class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Confirmar reserva</button>
                    </div>
                </form>
        </x-modal>

        @can('create', App\Models\Cliente::class)
            <x-modal name="novo-cliente" title="Cadastrar novo cliente" max-width="lg">
                <form method="POST" action="{{ route('clientes.store') }}" @submit.prevent="cadastrarCliente($event)" class="space-y-5">
                    @csrf
                    <div x-show="clienteErros.geral" x-text="clienteErros.geral?.[0]" class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700"></div>

                    <x-cliente-form-rapido :unidades="$unidades" />

                    <div class="flex justify-end gap-2 border-t border-slate-100 pt-4 dark:border-gray-800">
                        <button type="button" @click="$dispatch('close-modal', 'novo-cliente')" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5">Cancelar</button>
                        <button type="submit" :disabled="salvandoCliente" class="rounded-lg bg-sky-600 px-5 py-2 text-sm font-semibold text-white hover:bg-sky-700 disabled:cursor-wait disabled:opacity-60">
                            <span x-show="!salvandoCliente">Salvar e selecionar</span>
                            <span x-show="salvandoCliente">Salvando...</span>
                        </button>
                    </div>
                </form>
            </x-modal>
        @endcan
    @endcan
    </div>
@endsection
