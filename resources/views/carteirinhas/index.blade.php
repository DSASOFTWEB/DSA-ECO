@extends('layouts.app')

@section('titulo', 'Carteirinhas')

@section('conteudo')
    <form method="GET" class="mb-6 flex flex-wrap gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
        <input type="text" name="busca" value="{{ request('busca') }}" placeholder="Buscar por nome ou CPF" class="h-11 min-w-56 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90">
        <input type="text" name="codigo" value="{{ request('codigo') }}" placeholder="Buscar por código" class="h-11 min-w-56 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 outline-none transition placeholder:text-gray-400 focus:border-brand-500 focus:ring-3 focus:ring-brand-500/10 dark:border-gray-700 dark:text-white/90">
        <select name="status" class="h-11 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
            <option value="">Todos os status</option>
            @foreach (['ativa', 'bloqueada', 'expirada', 'cancelada'] as $opcao)
                <option value="{{ $opcao }}" @selected(request('status') === $opcao)>{{ ucfirst($opcao) }}</option>
            @endforeach
        </select>
        <select name="tipo" class="h-11 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
            <option value="">Titulares e dependentes</option>
            <option value="titular" @selected(request('tipo') === 'titular')>Só titulares</option>
            <option value="dependente" @selected(request('tipo') === 'dependente')>Só dependentes</option>
        </select>
        <button class="h-11 rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-theme-xs transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">Filtrar</button>
    </form>

    <div x-data="{
            selecionadas: [],
            todasNaPagina: [{{ $carteirinhas->pluck('id')->implode(',') }}],
            get todasMarcadas() { return this.todasNaPagina.length > 0 && this.todasNaPagina.every(id => this.selecionadas.includes(id)) },
            alternarTodas() {
                this.selecionadas = this.todasMarcadas ? [] : [...this.todasNaPagina];
            },
            imprimirSelecionadas() {
                if (!this.selecionadas.length) return;
                window.open('{{ url('carteirinhas/imprimir-lote') }}?ids=' + this.selecionadas.join(','), '_blank');
            }
         }">
        <div class="mb-3 flex items-center justify-between">
            <span class="text-sm text-slate-500"><span x-text="selecionadas.length"></span> selecionada(s)</span>
            <button type="button" @click="imprimirSelecionadas()" :disabled="!selecionadas.length"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-900 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-white dark:text-gray-900">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.7" d="M6 9V4h12v5M6 18h12v4H6v-4Zm-2-9h16a1 1 0 0 1 1 1v6a1 1 0 0 1-1 1h-3v-3H6v3H3a1 1 0 0 1-1-1v-6a1 1 0 0 1 1-1Z"/></svg>
                Imprimir selecionadas (PDF)
            </button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <table class="min-w-full divide-y divide-slate-100 text-sm">
                <thead class="border-b border-gray-100 bg-gray-50 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:border-gray-800 dark:bg-white/[0.02] dark:text-gray-400">
                    <tr>
                        <th class="w-10 px-4 py-3"><input type="checkbox" :checked="todasMarcadas" @change="alternarTodas()" class="rounded border-gray-300"></th>
                        <th class="px-4 py-3">Código</th>
                        <th class="px-4 py-3">Nome</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-50">
                    @forelse ($carteirinhas as $carteirinha)
                        <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                            <td class="px-4 py-3"><input type="checkbox" value="{{ $carteirinha->id }}" x-model.number="selecionadas" class="rounded border-gray-300"></td>
                            <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $carteirinha->codigo }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-800 dark:text-white/90">{{ $carteirinha->cliente?->nome ?? $carteirinha->dependente?->nome }}</div>
                                @if ($carteirinha->dependente)
                                    <div class="text-xs text-slate-400">dependente de {{ $carteirinha->dependente->cliente?->nome }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if ($carteirinha->cliente_id)
                                    <span class="inline-flex rounded-full bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-700 dark:bg-sky-500/15 dark:text-sky-400">Titular</span>
                                @else
                                    <span class="inline-flex rounded-full bg-violet-50 px-2.5 py-1 text-xs font-medium text-violet-700 dark:bg-violet-500/15 dark:text-violet-400">Dependente</span>
                                @endif
                            </td>
                            <td class="px-4 py-3"><x-status-badge :status="$carteirinha->status" /></td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('carteirinhas.imprimir', $carteirinha) }}" target="_blank" class="mr-3 text-slate-500 hover:underline">Imprimir</a>
                                <a href="{{ route('carteirinhas.show', $carteirinha) }}" class="text-sky-700 hover:underline">Ver</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Nenhuma carteirinha emitida.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $carteirinhas->links() }}</div>
@endsection
