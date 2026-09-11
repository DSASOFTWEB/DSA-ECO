@extends('layouts.app')

@section('titulo', 'Mesas e Comandas')

@section('conteudo')
<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <label class="text-sm font-medium">Unidade
                <select name="unidade_id" class="mt-1 block rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900" onchange="this.form.submit()">
                    @foreach($unidades as $unidade)<option value="{{ $unidade->id }}" @selected($unidade->id === $unidadeId)>{{ $unidade->nome }}</option>@endforeach
                </select>
            </label>
            <input type="hidden" name="tipo" value="{{ $tipo }}">
        </form>
        <div class="inline-flex rounded-xl bg-gray-100 p-1 dark:bg-gray-800">
            <a href="{{ route('food.index', ['unidade_id' => $unidadeId, 'tipo' => 'mesa']) }}" class="rounded-lg px-5 py-2 text-sm font-semibold {{ $tipo === 'mesa' ? 'bg-white text-sky-700 shadow dark:bg-gray-700 dark:text-sky-300' : 'text-gray-500' }}">Mapa de mesas</a>
            <a href="{{ route('food.index', ['unidade_id' => $unidadeId, 'tipo' => 'comanda']) }}" class="rounded-lg px-5 py-2 text-sm font-semibold {{ $tipo === 'comanda' ? 'bg-white text-sky-700 shadow dark:bg-gray-700 dark:text-sky-300' : 'text-gray-500' }}">Mapa de comandas</a>
        </div>
    </div>

    <div class="flex flex-wrap gap-4 text-xs font-medium text-gray-600 dark:text-gray-300">
        <span><i class="mr-1 inline-block h-3 w-3 rounded-full bg-emerald-500"></i>Livre</span>
        <span><i class="mr-1 inline-block h-3 w-3 rounded-full bg-rose-500"></i>Ocupada</span>
        <span><i class="mr-1 inline-block h-3 w-3 rounded-full bg-amber-500"></i>Pré-fechada/reservada</span>
        <span><i class="mr-1 inline-block h-3 w-3 rounded-full bg-gray-500"></i>Bloqueada</span>
    </div>

    <div class="grid gap-3 sm:grid-cols-4">
        <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-800 dark:bg-gray-900"><p class="text-xs text-gray-500">Total</p><p class="text-2xl font-bold">{{ $pontos->count() }}</p></div>
        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-900 dark:bg-emerald-500/10"><p class="text-xs text-emerald-700 dark:text-emerald-300">Livres</p><p class="text-2xl font-bold">{{ $pontos->where('status', 'livre')->count() }}</p></div>
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-3 dark:border-rose-900 dark:bg-rose-500/10"><p class="text-xs text-rose-700 dark:text-rose-300">Ocupadas</p><p class="text-2xl font-bold">{{ $pontos->where('status', 'ocupada')->count() }}</p></div>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-500/10"><p class="text-xs text-amber-700 dark:text-amber-300">Reservadas/bloqueadas</p><p class="text-2xl font-bold">{{ $pontos->whereIn('status', ['reservada', 'bloqueada'])->count() }}</p></div>
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-7">
        @forelse($pontos as $ponto)
            @php
                $atendimento = $ponto->atendimentoAtual;
                $cor = match(true) {
                    $atendimento?->status === 'pre_fechado', $ponto->status === 'reservada' => 'border-amber-400 bg-amber-50 dark:bg-amber-500/10',
                    $ponto->status === 'ocupada' => 'border-rose-400 bg-rose-50 dark:bg-rose-500/10',
                    $ponto->status === 'bloqueada' => 'border-gray-400 bg-gray-100 dark:bg-gray-800',
                    default => 'border-emerald-400 bg-emerald-50 dark:bg-emerald-500/10',
                };
            @endphp
            <article class="flex min-h-40 flex-col justify-between rounded-2xl border-2 p-4 shadow-sm {{ $cor }}">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-500">{{ $ponto->tipo }}</p>
                    <h2 class="text-xl font-bold">{{ $ponto->identificacao }}</h2>
                    @if($atendimento)
                        <p class="mt-2 text-sm">{{ $atendimento->quantidade_pessoas }} pessoa(s)</p>
                        <p class="text-lg font-bold">R$ {{ number_format((float)$atendimento->valor_total, 2, ',', '.') }}</p>
                        <p class="text-xs text-gray-500">há {{ $atendimento->aberto_em->diffForHumans(short: true) }}</p>
                    @else
                        <p class="mt-2 text-sm capitalize text-gray-500">{{ $ponto->status }}</p>
                    @endif
                </div>
                @if($atendimento)
                    <x-button :href="route('food.atendimentos.show', $atendimento)" size="sm" class="mt-3 w-full">Abrir conta</x-button>
                @elseif(in_array($ponto->status, ['livre','reservada']))
                    <form method="POST" action="{{ route('food.pontos.abrir', $ponto) }}" class="mt-3">@csrf
                        <input type="number" name="quantidade_pessoas" min="1" value="1" class="mb-2 w-full rounded-lg border-gray-300 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900" aria-label="Quantidade de pessoas">
                        <x-button type="submit" size="sm" class="w-full">Abrir {{ $ponto->tipo }}</x-button>
                    </form>
                @endif
                @can('update', $ponto)
                    @if(!$atendimento)
                    <details class="mt-3 border-t border-black/10 pt-3 dark:border-white/10">
                        <summary class="cursor-pointer text-center text-xs font-semibold text-gray-600 dark:text-gray-300">Configurar</summary>
                        <form method="POST" action="{{ route('food.pontos.update', $ponto) }}" class="mt-3 space-y-2">@csrf @method('PUT')
                            <div class="grid grid-cols-2 gap-2">
                                <label class="text-xs">Número<input type="number" name="numero" min="1" required value="{{ $ponto->numero }}" class="mt-1 w-full rounded-lg border-gray-300 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900"></label>
                                <label class="text-xs">Ordem<input type="number" name="ordem" min="0" value="{{ $ponto->ordem }}" class="mt-1 w-full rounded-lg border-gray-300 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900"></label>
                            </div>
                            <label class="block text-xs">Nome<input name="nome" maxlength="100" value="{{ $ponto->nome }}" class="mt-1 w-full rounded-lg border-gray-300 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900"></label>
                            <div class="grid grid-cols-2 gap-2">
                                <label class="text-xs">Capacidade<input type="number" name="capacidade" min="1" value="{{ $ponto->capacidade }}" class="mt-1 w-full rounded-lg border-gray-300 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900"></label>
                                <label class="text-xs">Situação<select name="status" class="mt-1 w-full rounded-lg border-gray-300 py-1.5 text-sm dark:border-gray-700 dark:bg-gray-900"><option value="livre" @selected($ponto->status === 'livre')>Livre</option><option value="reservada" @selected($ponto->status === 'reservada')>Reservada</option><option value="bloqueada" @selected($ponto->status === 'bloqueada')>Bloqueada</option></select></label>
                            </div>
                            <x-button type="submit" size="sm" variant="secondary" class="w-full">Salvar configuração</x-button>
                        </form>
                    </details>
                    @endif
                @endcan
            </article>
        @empty
            <div class="col-span-full rounded-2xl border border-dashed border-gray-300 p-10 text-center text-gray-500">
                Nenhuma {{ $tipo }} cadastrada nesta unidade.
                @can('create', App\Models\PontoAtendimento::class)
                    <a href="{{ route('empresa.edit', ['tipo' => $tipo, 'unidade_id' => $unidadeId]) }}" class="mt-3 block text-sm font-semibold text-sky-600 hover:underline">Cadastrar em faixa em Minha Empresa</a>
                @endcan
            </div>
        @endforelse
    </div>
</div>
@endsection
