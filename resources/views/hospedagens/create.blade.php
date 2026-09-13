@extends('layouts.app')

@section('titulo', 'Nova reserva')

@section('conteudo')
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
                <label class="block text-sm font-medium text-slate-700">Hóspede titular</label>
                <template x-if="clienteSelecionado">
                    <div class="mt-1 flex items-center justify-between rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700">
                        <span x-text="clienteSelecionado.nome"></span>
                        <button type="button" class="text-xs text-rose-600 hover:underline" @click="clienteSelecionado = null">trocar</button>
                    </div>
                </template>
                <div class="relative mt-1" x-show="!clienteSelecionado">
                    <input type="text" x-model="termo" @input.debounce.300ms="buscarCliente()" placeholder="Buscar por nome ou CPF..."
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    <ul x-show="resultados.length" class="absolute z-10 mt-1 w-full divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white shadow-lg dark:divide-gray-800 dark:border-gray-700 dark:bg-gray-800">
                        <template x-for="cliente in resultados" :key="cliente.id">
                            <li @click="selecionarCliente(cliente)" class="cursor-pointer px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-white/[0.03]">
                                <span x-text="cliente.nome"></span> <span class="text-slate-400" x-text="cliente.cpf"></span>
                            </li>
                        </template>
                    </ul>
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
    </div>
@endsection
