@extends('layouts.app')

@section('titulo', 'Check-out — Quarto '.$hospedagem->quarto->numero)

@section('conteudo')
    <div class="max-w-xl">
        @if (! $caixaAberto && $caixasDisponiveis->count() > 1)
            <div class="rounded-2xl border border-gray-200 bg-white p-6 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold text-slate-700">Você está no caixa/terminal de qual unidade?</h2>
                <p class="mt-1 text-sm text-slate-400">Há mais de um caixa aberto agora.</p>
                <form method="GET" action="{{ route('hospedagens.checkout', $hospedagem) }}" class="mt-4 flex flex-col gap-3">
                    <select name="caixa_id" required class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm dark:border-gray-700">
                        <option value="">Selecione...</option>
                        @foreach ($caixasDisponiveis as $caixa)
                            <option value="{{ $caixa->id }}">{{ $caixa->terminal->nome ?? $caixa->unidade->nome }} ({{ $caixa->unidade->nome }})</option>
                        @endforeach
                    </select>
                    <button class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600">Continuar</button>
                </form>
            </div>
        @elseif (! $caixaAberto)
            <div class="rounded-2xl border border-warning-200 bg-warning-50 p-5 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
                Nenhum caixa aberto no momento. <a href="{{ route('caixas.index') }}" class="font-medium underline">Abra o caixa</a> antes de fazer o check-out.
            </div>
        @else
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="mb-1 text-lg font-semibold text-gray-800 dark:text-white/90">Fechar conta — Quarto {{ $hospedagem->quarto->numero }}</h2>
                @include('hospedagens._checkout_form', [
                    'hospedagem' => $hospedagem,
                    'caixaAberto' => $caixaAberto,
                    'caixasDisponiveis' => $caixasDisponiveis,
                    'noites' => $noites,
                    'quantidadeItensNfce' => $quantidadeItensNfce,
                    'quantidadeItensNfse' => $quantidadeItensNfse,
                    'formasPagamento' => $formasPagamento,
                ])
            </div>
        @endif
    </div>
@endsection
