@extends('layouts.app')

@section('titulo', $produto->nome)

@section('conteudo')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="flex items-start gap-4">
            @if ($produto->imagem_url)
                <img src="{{ $produto->imagem_url }}" alt="" class="h-16 w-16 rounded-lg border border-slate-200 object-contain bg-white">
            @endif
            <div>
                <h2 class="text-xl font-bold text-slate-800">{{ $produto->nome }}</h2>
                <p class="text-sm text-slate-500">
                    {{ $produto->isServico() ? 'Serviço (NFS-e)' : 'Produto (NFC-e)' }}
                    · {{ $produto->categoria?->nome ?? 'Sem categoria' }}
                    · R$ {{ number_format($produto->preco_venda, 2, ',', '.') }}
                </p>
                @if ($produto->ean)
                    <p class="text-xs text-slate-400">EAN {{ $produto->ean }}</p>
                @endif
            </div>
        </div>
        @can('update', $produto)
            <a href="{{ route('produtos.edit', $produto) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Editar</a>
        @endcan
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-3 text-sm font-semibold text-slate-700">Dados fiscais</h3>
            @if ($produto->isServico())
                <dl class="grid grid-cols-2 gap-2 text-sm">
                    <dt class="text-slate-400">LC 116</dt><dd>{{ $produto->codigo_servico_lc116 ?: '—' }}</dd>
                    <dt class="text-slate-400">Trib. municipal</dt><dd>{{ $produto->codigo_tributacao_municipal ?: '—' }}</dd>
                    <dt class="text-slate-400">CNAE</dt><dd>{{ $produto->cnae_servico ?: '—' }}</dd>
                    <dt class="text-slate-400">NBS</dt><dd>{{ $produto->nbs ?: '—' }}</dd>
                    <dt class="text-slate-400">Alíq. ISS</dt><dd>{{ $produto->aliq_iss !== null ? $produto->aliq_iss.'%' : '—' }}</dd>
                    <dt class="text-slate-400">ISS retido</dt><dd>{{ $produto->iss_retido ? 'Sim' : 'Não' }}</dd>
                </dl>
            @else
                <dl class="grid grid-cols-2 gap-2 text-sm">
                    <dt class="text-slate-400">NCM</dt><dd>{{ $produto->ncm ?: '—' }}</dd>
                    <dt class="text-slate-400">CEST</dt><dd>{{ $produto->cest ?: '—' }}</dd>
                    <dt class="text-slate-400">CFOP</dt><dd>{{ $produto->cfop ?: '—' }}</dd>
                    <dt class="text-slate-400">Origem</dt><dd>{{ $produto->origem !== null ? $produto->origem : '—' }}</dd>
                    <dt class="text-slate-400">CST / CSOSN</dt><dd>{{ $produto->cst_icms ?: '—' }} / {{ $produto->csosn ?: '—' }}</dd>
                    <dt class="text-slate-400">Unidade</dt><dd>{{ $produto->unidade_comercial }}</dd>
                </dl>
            @endif
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-3 text-sm font-semibold text-slate-700">Estoque atual</h3>
            <p class="text-3xl font-bold {{ $produto->estoqueAbaixoDoMinimo() ? 'text-rose-600' : 'text-slate-800' }}">{{ $produto->estoque_atual }}</p>
            <p class="text-xs text-slate-400">mínimo: {{ $produto->estoque_minimo }}</p>

            @can('ajustarEstoque', $produto)
                <form method="POST" action="{{ route('produtos.ajustar-estoque', $produto) }}" class="mt-4 space-y-2">
                    @csrf
                    <input type="number" min="0" name="quantidade" placeholder="Nova quantidade" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input type="text" name="motivo" placeholder="Motivo do ajuste" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <button class="w-full rounded-lg bg-slate-700 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Ajustar estoque</button>
                </form>
            @endcan
        </div>
    </div>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <h3 class="mb-3 text-sm font-semibold text-slate-700">Últimas movimentações</h3>
        <table class="min-w-full text-sm">
            <tbody class="divide-y divide-slate-50">
                @forelse ($produto->movimentacoesEstoque as $mov)
                    <tr>
                        <td class="py-2">{{ ucfirst($mov->tipo) }}</td>
                        <td class="py-2 text-slate-500">{{ $mov->motivo }}</td>
                        <td class="py-2 text-slate-500">{{ $mov->quantidade_anterior }} → {{ $mov->quantidade_atual }}</td>
                        <td class="py-2 text-right text-slate-400">{{ $mov->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td class="py-6 text-center text-slate-400">Nenhuma movimentação registrada.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
