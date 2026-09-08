@extends('layouts.app')

@section('titulo', 'Planos')

@section('conteudo')
    <div class="mb-4 flex justify-end">
        @can('create', \App\Models\Plano::class)
            <a href="{{ route('planos.create') }}" class="inline-flex h-11 items-center rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">+ Novo plano</a>
        @endcan
    </div>

    @php
        $paletaCards = [
            ['card' => 'border-sky-300 bg-sky-100 hover:border-sky-500 dark:border-sky-700 dark:bg-sky-500/[0.15] dark:hover:border-sky-500/60', 'accent' => 'bg-sky-500', 'preco' => 'text-sky-700 dark:text-sky-400'],
            ['card' => 'border-emerald-300 bg-emerald-100 hover:border-emerald-500 dark:border-emerald-700 dark:bg-emerald-500/[0.15] dark:hover:border-emerald-500/60', 'accent' => 'bg-emerald-500', 'preco' => 'text-emerald-700 dark:text-emerald-400'],
            ['card' => 'border-amber-300 bg-amber-100 hover:border-amber-500 dark:border-amber-700 dark:bg-amber-500/[0.15] dark:hover:border-amber-500/60', 'accent' => 'bg-amber-500', 'preco' => 'text-amber-700 dark:text-amber-400'],
            ['card' => 'border-violet-300 bg-violet-100 hover:border-violet-500 dark:border-violet-700 dark:bg-violet-500/[0.15] dark:hover:border-violet-500/60', 'accent' => 'bg-violet-500', 'preco' => 'text-violet-700 dark:text-violet-400'],
            ['card' => 'border-rose-300 bg-rose-100 hover:border-rose-500 dark:border-rose-700 dark:bg-rose-500/[0.15] dark:hover:border-rose-500/60', 'accent' => 'bg-rose-500', 'preco' => 'text-rose-700 dark:text-rose-400'],
        ];
    @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($planos as $plano)
            @php $cor = $paletaCards[$loop->index % count($paletaCards)]; @endphp
            <article class="group overflow-hidden rounded-2xl border p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md {{ $cor['card'] }}">
                <div class="-mx-6 -mt-6 mb-5 h-1.5 {{ $cor['accent'] }}"></div>
                <div class="flex items-start justify-between">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $plano->nome }}</h3>
                    <x-status-badge :status="$plano->ativo ? 'ativo' : 'inativo'" />
                </div>
                <p class="mt-4 text-3xl font-bold tracking-tight {{ $cor['preco'] }}">R$ {{ number_format($plano->valor, 2, ',', '.') }}<span class="text-sm font-normal text-gray-400">/{{ $plano->periodicidade }}</span></p>
                <p class="mt-1 text-sm text-slate-500">{{ $plano->descricao }}</p>
                <dl class="mt-3 space-y-1 text-xs text-slate-500">
                    <div>Até {{ $plano->max_dependentes }} dependente(s)</div>
                    <div>{{ $plano->contratos_count }} contrato(s) vinculado(s)</div>
                </dl>
                <div class="mt-4 flex justify-end gap-3 text-sm">
                    @can('update', $plano)
                        <a href="{{ route('planos.edit', $plano) }}" class="text-sky-700 hover:underline">Editar</a>
                    @endcan
                </div>
            </article>
        @empty
            <p class="text-slate-400">Nenhum plano cadastrado ainda.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $planos->links() }}</div>
@endsection
