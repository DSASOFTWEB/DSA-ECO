@extends('layouts.app')

@section('titulo', 'Estoque de produtos')

@section('conteudo')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <form method="GET" class="flex flex-wrap gap-2">
            <input type="text" name="nome" value="{{ request('nome') }}" placeholder="Buscar produto" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <select name="tipo_item" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todos os tipos</option>
                <option value="produto" @selected(request('tipo_item') === 'produto')>Produto (NFC-e)</option>
                <option value="servico" @selected(request('tipo_item') === 'servico')>Serviço (NFS-e)</option>
            </select>
            <button class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-gray-900 dark:bg-white/10 dark:hover:bg-white/15">Filtrar</button>
        </form>

        @can('create', \App\Models\Produto::class)
            <div class="flex flex-wrap items-center gap-2">
                <form
                    method="POST"
                    action="{{ route('produtos.sincronizar-ncm') }}"
                    x-data="{ loading: false }"
                    @submit="loading = true"
                    class="inline"
                >
                    @csrf
                    <button
                        type="submit"
                        :disabled="loading"
                        class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-theme-xs transition hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        title="Baixa a tabela NCM oficial do Portal Único Siscomex"
                    >
                        <span x-show="!loading">Baixar NCM</span>
                        <span x-cloak x-show="loading">Baixando NCM…</span>
                    </button>
                </form>
                @if (($totalNcms ?? 0) > 0)
                    <span class="text-xs text-slate-400">{{ number_format($totalNcms, 0, ',', '.') }} códigos</span>
                @endif
                <button
                    type="button"
                    @click="$dispatch('open-modal', 'incluir-produto')"
                    class="rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600"
                >
                    + Incluir
                </button>
            </div>
        @endcan
    </div>

    @if ($errors->any())
        <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/40 dark:text-rose-300">
            <p class="font-semibold">Não foi possível salvar o produto:</p>
            <ul class="mt-1 list-disc pl-5">
                @foreach ($errors->all() as $erro)
                    <li>{{ $erro }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Item</th>
                    <th class="px-4 py-3">Tipo</th>
                    <th class="px-4 py-3">Categoria</th>
                    <th class="px-4 py-3">Preço venda</th>
                    <th class="px-4 py-3">Estoque</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($produtos as $produto)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                        <td class="px-4 py-3 font-medium text-slate-800">
                            <div class="flex items-center gap-2">
                                @if ($produto->imagem_url)
                                    <img src="{{ $produto->imagem_url }}" alt="" class="h-8 w-8 rounded object-contain bg-white">
                                @endif
                                <span>{{ $produto->nome }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ $produto->isServico() ? 'Serviço' : 'Produto' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $produto->categoria?->nome ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">R$ {{ number_format($produto->preco_venda, 2, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            <span class="{{ $produto->estoqueAbaixoDoMinimo() ? 'font-semibold text-rose-600' : 'text-slate-500' }}">
                                {{ $produto->controla_estoque ? $produto->estoque_atual : '—' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                @can('update', $produto)
                                    <a href="{{ route('produtos.index', ['editar' => $produto->id] + request()->except('editar', 'page')) }}" class="font-medium text-brand-600 hover:underline">Editar</a>
                                @endcan
                                <a href="{{ route('produtos.show', $produto) }}" class="text-sky-700 hover:underline">Ver</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Nenhum produto cadastrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $produtos->links() }}</div>

    @can('create', \App\Models\Produto::class)
        <x-modal name="incluir-produto" title="Incluir produto" maxWidth="5xl">
            <form method="POST" action="{{ route('produtos.store') }}" class="max-h-[75vh] space-y-2 overflow-y-auto pr-1">
                @csrf
                <input type="hidden" name="return_to" value="index">
                @include('produtos._form', ['produto' => null, 'categorias' => $categorias, 'empresa' => $empresa])
                <div class="sticky bottom-0 flex justify-end gap-2 border-t border-slate-100 bg-white pt-4 dark:border-gray-800 dark:bg-gray-900">
                    <button type="button" @click="$dispatch('close-modal', 'incluir-produto')" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:hover:bg-white/5">Cancelar</button>
                    <button class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">Salvar produto</button>
                </div>
            </form>
        </x-modal>
    @endcan

    @if ($produtoEditando)
        @can('update', $produtoEditando)
            <x-modal name="editar-produto" title="Editar produto" maxWidth="5xl">
                <form method="POST" action="{{ route('produtos.update', $produtoEditando) }}" class="max-h-[75vh] space-y-2 overflow-y-auto pr-1">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="return_to" value="index">
                    @include('produtos._form', ['produto' => $produtoEditando, 'categorias' => $categorias, 'empresa' => $empresa])
                    <div class="sticky bottom-0 flex justify-end gap-2 border-t border-slate-100 bg-white pt-4 dark:border-gray-800 dark:bg-gray-900">
                        <a href="{{ route('produtos.index', request()->except('editar')) }}" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100 dark:hover:bg-white/5">Cancelar</a>
                        <button class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">Salvar alterações</button>
                    </div>
                </form>
            </x-modal>
        @endcan
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            @if ($produtoEditando)
                window.dispatchEvent(new CustomEvent('open-modal', { detail: 'editar-produto' }));
            @elseif ($errors->any() && old('return_to') === 'index' && ! old('_method'))
                window.dispatchEvent(new CustomEvent('open-modal', { detail: 'incluir-produto' }));
            @elseif (session('abrir_incluir'))
                window.dispatchEvent(new CustomEvent('open-modal', { detail: 'incluir-produto' }));
            @endif
        });
    </script>
@endpush
