@extends('layouts.app')

@section('titulo', 'Novo contrato')

@section('conteudo')
    <form method="POST" action="{{ route('contratos.store') }}" x-data="{ agendamento: @js(old('agendamento_primeiro_vencimento', '30_dias')) }" class="max-w-4xl space-y-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-7">
        @csrf

        <div x-data="{
                clienteSelecionado: @js($clientePreSelecionado ? ['id' => $clientePreSelecionado->id, 'nome' => $clientePreSelecionado->nome, 'cpf' => $clientePreSelecionado->cpf] : null),
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
            <label class="block text-sm font-medium text-slate-700">Cliente</label>

            <template x-if="clienteSelecionado">
                <div class="mt-1 flex items-center justify-between rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700">
                    <span><span x-text="clienteSelecionado.nome"></span> <span class="text-slate-400" x-text="'(' + clienteSelecionado.cpf + ')'"></span></span>
                    <button type="button" class="text-xs text-rose-600 hover:underline" @click="clienteSelecionado = null">trocar</button>
                </div>
            </template>

            <div class="relative mt-1" x-show="!clienteSelecionado">
                <input type="text" x-model="termo" @input.debounce.300ms="buscarCliente()" placeholder="Buscar por nome ou CPF..."
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <ul x-show="resultados.length" class="absolute z-10 mt-1 w-full divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white shadow-lg dark:divide-gray-800 dark:border-gray-700 dark:bg-gray-800">
                    <template x-for="cliente in resultados" :key="cliente.id">
                        <li @click="selecionarCliente(cliente)" class="cursor-pointer px-3 py-2 text-sm hover:bg-gray-50 dark:hover:bg-white/[0.03]">
                            <span x-text="cliente.nome"></span> <span class="text-slate-400" x-text="cliente.cpf"></span>
                        </li>
                    </template>
                </ul>
                <p class="mt-1 text-xs text-slate-400">Digite ao menos 2 letras do nome, ou o CPF.</p>
            </div>

            <input type="hidden" name="cliente_id" :value="clienteSelecionado?.id ?? ''" required>
            @error('cliente_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 [&_label]:text-gray-700 dark:[&_label]:text-gray-300 [&_input]:min-h-11 [&_input]:border-gray-300 [&_input]:bg-transparent [&_input]:text-gray-800 [&_input]:outline-none [&_input]:focus:border-brand-500 dark:[&_input]:border-gray-700 dark:[&_input]:text-white/90 [&_select]:min-h-11 [&_select]:border-gray-300 [&_select]:bg-transparent [&_select]:text-gray-800 [&_select]:outline-none [&_select]:focus:border-brand-500 dark:[&_select]:border-gray-700 dark:[&_select]:bg-gray-900 dark:[&_select]:text-white/90">
            <div>
                <label class="block text-sm font-medium text-slate-700">Unidade</label>
                <select name="unidade_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($unidades as $unidade)
                        <option value="{{ $unidade->id }}" @selected(old('unidade_id') == $unidade->id)>{{ $unidade->nome }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Plano</label>
                <select name="plano_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($planos as $plano)
                        <option value="{{ $plano->id }}" @selected(old('plano_id') == $plano->id)>{{ $plano->nome }} — R$ {{ number_format($plano->valor, 2, ',', '.') }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Data de início</label>
                <input type="date" name="data_inicio" value="{{ old('data_inicio', now()->toDateString()) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Dia de vencimento</label>
                <input type="number" min="1" max="28" name="dia_vencimento" value="{{ old('dia_vencimento', 10) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Valor mensal (opcional — padrão do plano)</label>
                <input type="number" step="0.01" min="0" name="valor_mensal" value="{{ old('valor_mensal') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Caução / entrada</label>
                <input type="number" step="0.01" min="0.01" name="valor_caucao" value="{{ old('valor_caucao') }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('valor_caucao')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-slate-400">Será gerada como cobrança de entrada, com vencimento na data de início.</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Desconto (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="desconto_percentual" value="{{ old('desconto_percentual', 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>

        <fieldset class="rounded-xl border border-slate-200 p-4 dark:border-gray-700">
            <legend class="px-2 text-sm font-semibold text-slate-700 dark:text-gray-300">Primeira mensalidade</legend>
            <div class="mt-2 flex flex-wrap gap-5 text-sm">
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="agendamento_primeiro_vencimento" value="30_dias" x-model="agendamento">
                    30 dias após o início
                </label>
                <label class="inline-flex items-center gap-2">
                    <input type="radio" name="agendamento_primeiro_vencimento" value="data_escolhida" x-model="agendamento">
                    Escolher uma data
                </label>
            </div>
            <div x-show="agendamento === 'data_escolhida'" x-cloak class="mt-4 max-w-xs">
                <label class="block text-sm font-medium text-slate-700">Data da primeira mensalidade</label>
                <input type="date" name="primeiro_vencimento" value="{{ old('primeiro_vencimento') }}" :required="agendamento === 'data_escolhida'" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('primeiro_vencimento')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
        </fieldset>

        <div class="flex justify-end gap-2">
            <a href="{{ url()->previous() }}" class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Cancelar</a>
            <button class="h-11 rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">Contratar</button>
        </div>
    </form>
@endsection
