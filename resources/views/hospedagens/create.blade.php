@extends('layouts.app')

@section('titulo', 'Nova reserva')

@section('conteudo')
    <div class="max-w-xl rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
         x-data="{
            clienteSelecionado: @js($clienteAnterior ? ['id' => $clienteAnterior->id, 'nome' => $clienteAnterior->nome, 'cpf' => $clienteAnterior->cpf] : null),
            termo: '',
            resultados: [],
            buscando: false,
            buscou: false,
            salvandoCliente: false,
            clienteErros: {},
            novoCliente: { nome: '', cpf: '', data_nascimento: '', telefone: '', whatsapp: '', unidade_id: '' },
            buscarCliente() {
                if (this.termo.trim().length < 2) { this.resultados = []; this.buscou = false; return; }
                this.buscando = true;
                fetch('{{ route('clientes.buscar') }}?termo=' + encodeURIComponent(this.termo), { headers: { 'Accept': 'application/json' } })
                    .then(response => response.ok ? response.json() : Promise.reject())
                    .then(dados => { this.resultados = dados; })
                    .catch(() => { this.resultados = []; })
                    .finally(() => { this.buscando = false; this.buscou = true; });
            },
            selecionarCliente(cliente) {
                this.clienteSelecionado = cliente;
                this.resultados = [];
                this.termo = '';
                this.buscou = false;
            },
            {{-- Não achou: abre o cadastro rápido já com o que foi digitado (CPF se for só número, senão nome) --}}
            abrirNovoCliente() {
                const termo = this.termo.trim();
                if (termo) {
                    if (/^[\d.\-\s]+$/.test(termo)) this.novoCliente.cpf = termo;
                    else this.novoCliente.nome = termo;
                }
                this.clienteErros = {};
                this.$dispatch('open-modal', 'novo-cliente');
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
         }">
        <h2 class="mb-1 text-lg font-semibold text-gray-800 dark:text-white/90">Nova reserva</h2>
        <p class="mb-5 text-sm text-slate-500">O quarto fica bloqueado nesse período — check-in é feito depois, na hora que o hóspede chegar.</p>

        <form method="POST" action="{{ route('hospedagens.store') }}" class="space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-medium text-slate-700">Quarto</label>
                <select name="quarto_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    <option value="">Selecione...</option>
                    @foreach ($quartos as $quarto)
                        <option value="{{ $quarto->id }}" @selected(old('quarto_id') == $quarto->id)>{{ $quarto->numero }} — {{ $quarto->unidade->nome }} (até {{ $quarto->capacidade_maxima }} pessoas, R$ {{ number_format($quarto->valor_diaria, 2, ',', '.') }}/diária)</option>
                    @endforeach
                </select>
                @error('quarto_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <div class="flex items-center justify-between gap-3">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Hóspede titular</label>
                    @can('create', App\Models\Cliente::class)
                        <button type="button" @click="abrirNovoCliente()" class="inline-flex items-center gap-1 rounded-lg border border-sky-200 px-2.5 py-1 text-xs font-semibold text-sky-700 hover:bg-sky-50 dark:border-sky-800 dark:text-sky-400 dark:hover:bg-sky-500/10">
                            <span class="text-base leading-none">+</span> Novo cliente
                        </button>
                    @endcan
                </div>
                <template x-if="clienteSelecionado">
                    <div class="mt-1 flex items-center justify-between rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm dark:border-emerald-800 dark:bg-emerald-500/10">
                        <span><span class="font-semibold" x-text="clienteSelecionado.nome"></span> <span class="text-slate-500" x-text="clienteSelecionado.cpf"></span></span>
                        <button type="button" class="text-xs text-rose-600 hover:underline" @click="clienteSelecionado = null">trocar</button>
                    </div>
                </template>
                <div class="relative mt-1" x-show="!clienteSelecionado">
                    <input type="search" x-model="termo" @input.debounce.300ms="buscarCliente()" placeholder="Buscar por nome ou CPF..." autocomplete="off"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    <p x-show="buscando" class="mt-1 text-xs text-slate-400">Buscando...</p>
                    <ul x-show="resultados.length" class="absolute z-10 mt-1 max-h-52 w-full divide-y divide-slate-100 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg dark:divide-gray-800 dark:border-gray-700 dark:bg-gray-800">
                        <template x-for="cliente in resultados" :key="cliente.id">
                            <li><button type="button" @click="selecionarCliente(cliente)" class="w-full px-3 py-2 text-left text-sm hover:bg-gray-50 dark:hover:bg-white/[0.03]">
                                <span x-text="cliente.nome"></span> <span class="text-slate-400" x-text="cliente.cpf"></span>
                            </button></li>
                        </template>
                    </ul>
                    {{-- Não encontrou: oferece o cadastro rápido com o que foi digitado --}}
                    <div x-show="buscou && !buscando && termo.trim().length >= 2 && resultados.length === 0" x-cloak
                         class="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                        <span>Nenhum cliente encontrado para "<strong x-text="termo.trim()"></strong>".</span>
                        @can('create', App\Models\Cliente::class)
                            <button type="button" @click="abrirNovoCliente()" class="rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-700">Cadastrar novo cliente</button>
                        @endcan
                    </div>
                </div>
                <input type="hidden" name="cliente_id" :value="clienteSelecionado?.id ?? ''" required>
                @error('cliente_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Check-in previsto</label>
                    <input type="date" name="data_checkin_prevista" value="{{ old('data_checkin_prevista', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    @error('data_checkin_prevista')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Check-out previsto</label>
                    <input type="date" name="data_checkout_prevista" value="{{ old('data_checkout_prevista') }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    @error('data_checkout_prevista')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Adultos</label>
                    <input type="number" name="quantidade_adultos" min="1" max="50" value="{{ old('quantidade_adultos', 1) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    @error('quantidade_adultos')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Crianças</label>
                    <input type="number" name="quantidade_criancas" min="0" max="50" value="{{ old('quantidade_criancas', 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Isentos</label>
                    <input type="number" name="quantidade_isentos" min="0" max="50" value="{{ old('quantidade_isentos', 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Observações</label>
                <textarea name="observacoes" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">{{ old('observacoes') }}</textarea>
            </div>

            <div class="flex justify-end gap-2">
                <a href="{{ route('hospedagens.index') }}" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancelar</a>
                <button class="h-11 rounded-lg bg-brand-500 px-5 text-sm font-semibold text-white shadow-theme-xs hover:bg-brand-600">Reservar</button>
            </div>
        </form>

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
    </div>
@endsection
