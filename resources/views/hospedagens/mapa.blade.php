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

                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($item['status'] === 'ocupado')
                            <a href="{{ route('hospedagens.show', $item['hospedagem']) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-gray-700 dark:text-slate-300">Ver</a>
                            @can('checkout', $item['hospedagem'])
                                <a href="{{ route('hospedagens.checkout', $item['hospedagem']) }}" class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700">Check-out</a>
                            @endcan
                        @elseif ($item['status'] === 'reservado')
                            <a href="{{ route('hospedagens.show', $item['hospedagem']) }}" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 dark:border-gray-700 dark:text-slate-300">Ver</a>
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
                                <a href="{{ route('hospedagens.create') }}" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">+ Nova reserva</a>
                            @endcan
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center text-slate-400">Nenhum quarto encontrado.</div>
        @endforelse
    </div>
@endsection
