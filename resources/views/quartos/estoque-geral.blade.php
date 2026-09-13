@extends('layouts.app')

@section('titulo', 'Estoque dos Quartos')

@section('conteudo')
    <div class="mb-6 flex items-center justify-between gap-3">
        <p class="text-sm text-slate-500 dark:text-slate-400">Itens do estoque geral emprestados (comodato) em cada quarto — controle remoto, secador, jogo de toalha extra, etc. Clique num quarto pra emprestar/devolver.</p>
        <a href="{{ route('quartos.index') }}" class="shrink-0 rounded-lg border border-gray-300 px-4 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Voltar pra Quartos</a>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($quartos as $quarto)
            <a href="{{ route('quartos.estoque', $quarto) }}" class="block overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-gray-800 dark:bg-gray-900">
                <div class="mb-3 flex items-center justify-between">
                    <span class="text-sm font-bold text-slate-800 dark:text-white/90">Quarto {{ $quarto->numero }}</span>
                    <span class="text-xs text-slate-400">{{ $quarto->unidade->nome }}</span>
                </div>

                @if ($quarto->itensComodato->isEmpty())
                    <p class="text-sm text-slate-300 dark:text-slate-600">Nenhum item emprestado.</p>
                @else
                    <ul class="space-y-1 text-sm">
                        @foreach ($quarto->itensComodato as $item)
                            <li class="flex justify-between text-slate-600 dark:text-slate-300">
                                <span>{{ $item->produto->nome }}</span>
                                <span class="font-semibold">{{ $item->quantidade }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </a>
        @endforeach
    </div>
@endsection
