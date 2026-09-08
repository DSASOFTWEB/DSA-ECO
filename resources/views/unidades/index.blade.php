@extends('layouts.app')

@section('titulo', 'Unidades')

@section('conteudo')
    <div class="mb-6 flex justify-end">
        @can('create', \App\Models\Unidade::class)
            <a href="{{ route('unidades.create') }}" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">+ Nova unidade</a>
        @endcan
    </div>

    @php
        $paletaCards = [
            ['card' => 'border-sky-300 bg-sky-100 hover:border-sky-500 dark:border-sky-700 dark:bg-sky-500/[0.15] dark:hover:border-sky-500/60', 'accent' => 'bg-sky-500'],
            ['card' => 'border-emerald-300 bg-emerald-100 hover:border-emerald-500 dark:border-emerald-700 dark:bg-emerald-500/[0.15] dark:hover:border-emerald-500/60', 'accent' => 'bg-emerald-500'],
            ['card' => 'border-amber-300 bg-amber-100 hover:border-amber-500 dark:border-amber-700 dark:bg-amber-500/[0.15] dark:hover:border-amber-500/60', 'accent' => 'bg-amber-500'],
            ['card' => 'border-violet-300 bg-violet-100 hover:border-violet-500 dark:border-violet-700 dark:bg-violet-500/[0.15] dark:hover:border-violet-500/60', 'accent' => 'bg-violet-500'],
            ['card' => 'border-rose-300 bg-rose-100 hover:border-rose-500 dark:border-rose-700 dark:bg-rose-500/[0.15] dark:hover:border-rose-500/60', 'accent' => 'bg-rose-500'],
        ];
    @endphp

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($unidades as $unidade)
            @php $cor = $paletaCards[$loop->index % count($paletaCards)]; @endphp
            <div class="overflow-hidden rounded-2xl border p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md {{ $cor['card'] }}">
                <div class="-mx-5 -mt-5 mb-4 h-1.5 {{ $cor['accent'] }}"></div>
                <div class="flex items-start justify-between">
                    <h3 class="font-semibold text-gray-800 dark:text-white/90">{{ $unidade->nome }}</h3>
                    <x-status-badge :status="$unidade->status" />
                </div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $unidade->cidade }}/{{ $unidade->uf }}</p>
                <dl class="mt-4 space-y-2 rounded-xl bg-white/60 p-3 text-xs text-gray-500 dark:bg-white/[0.06] dark:text-gray-400">
                    <div>{{ $unidade->clientes_count }} cliente(s)</div>
                    <div>{{ $unidade->contratos_count }} contrato(s)</div>
                </dl>
                <div class="mt-4 flex items-center justify-between gap-2 text-sm">
                    <button type="button" onclick="navigator.clipboard.writeText('{{ route('checkout.index', $unidade) }}'); this.innerText='Link copiado!'; setTimeout(() => this.innerText='Copiar link de venda online', 2000)" class="text-xs text-cyan-700 hover:underline dark:text-cyan-400">Copiar link de venda online</button>
                    @can('update', $unidade)
                        <a href="{{ route('unidades.edit', $unidade) }}" class="text-sky-700 hover:underline">Editar</a>
                    @endcan
                </div>
            </div>
        @empty
            <p class="text-slate-400">Nenhuma unidade cadastrada.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $unidades->links() }}</div>
@endsection
