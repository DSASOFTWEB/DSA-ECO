@extends('layouts.app')

@section('titulo', 'Cidades (IBGE)')

@section('conteudo')
    <div class="mb-6 space-y-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-800 dark:text-white">Cadastro de cidades</h2>
                <p class="mt-1 text-sm text-slate-500">Tabela oficial de municípios via API do IBGE (código de 7 dígitos usado na NFC-e/NFS-e).</p>
                <p class="mt-1 text-xs text-slate-400">
                    {{ number_format($total, 0, ',', '.') }} cidade(s) cadastrada(s)
                </p>
            </div>

            <form
                method="POST"
                action="{{ route('cidades.sincronizar') }}"
                x-data="{ loading: false }"
                @submit="loading = true"
                class="flex flex-wrap items-end gap-2"
            >
                @csrf
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">UF (opcional)</label>
                    <select name="uf" class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800">
                        <option value="">Todo o Brasil</option>
                        @foreach ($estados as $uf)
                            <option value="{{ $uf }}" @selected($filtroUf === $uf)>{{ $uf }}</option>
                        @endforeach
                    </select>
                </div>
                <button
                    type="submit"
                    :disabled="loading"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-theme-xs transition hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    title="Baixa municípios oficiais da API do IBGE"
                >
                    <span x-show="!loading">Baixar do IBGE</span>
                    <span x-cloak x-show="loading">Baixando…</span>
                </button>
            </form>
        </div>

        <form method="GET" class="flex flex-wrap gap-2 border-t border-slate-100 pt-4 dark:border-gray-800">
            <input
                type="text"
                name="nome"
                value="{{ $filtroNome }}"
                placeholder="Buscar por nome"
                class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800"
            >
            <select name="uf" class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800">
                <option value="">Todas as UFs</option>
                @foreach ($estados as $uf)
                    <option value="{{ $uf }}" @selected($filtroUf === $uf)>{{ $uf }}</option>
                @endforeach
            </select>
            <button class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-900 dark:bg-white/10 dark:hover:bg-white/15">Filtrar</button>
            @if ($filtroNome !== '' || $filtroUf !== '')
                <a href="{{ route('cidades.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Limpar</a>
            @endif
        </form>
    </div>

    @if (session('sucesso'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/40 dark:text-emerald-300">
            {{ session('sucesso') }}
        </div>
    @endif
    @if (session('erro'))
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-300">
            {{ session('erro') }}
        </div>
    @endif

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Código IBGE</th>
                    <th class="px-4 py-3">Nome</th>
                    <th class="px-4 py-3">UF</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($cidades as $cidade)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                        <td class="px-4 py-3 font-mono text-slate-700 dark:text-gray-200">{{ $cidade->codigo }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-white">{{ $cidade->nome }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-gray-300">{{ $cidade->uf }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-10 text-center text-slate-400">
                            Nenhuma cidade cadastrada. Use <strong>Baixar do IBGE</strong> para importar os municípios.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $cidades->links() }}
    </div>
@endsection
