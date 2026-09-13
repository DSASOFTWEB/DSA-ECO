@extends('layouts.app')

@section('titulo', 'Lista do Café da Manhã')

@push('styles')
    <style>
        @media print {
            aside, header { display: none !important; }
            .lg\:pl-72 { padding-left: 0 !important; }
            main, .no-print-hide { padding: 0 !important; }
        }
    </style>
@endpush

@section('conteudo')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-3 no-print-hide">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Data</label>
                <input type="date" name="data" value="{{ $data->toDateString() }}" class="mt-1 h-11 rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 outline-none focus:border-brand-500 dark:border-gray-700 dark:text-white/90">
            </div>
            <button class="h-11 rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">Aplicar</button>
            <a href="{{ route('hospedagens.cafe') }}" class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-4 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Hoje</a>
        </form>
        <button type="button" onclick="window.print()" class="inline-flex h-11 items-center gap-2 rounded-lg bg-slate-700 px-5 text-sm font-semibold text-white hover:bg-slate-800">🖨 Imprimir</button>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-4">
        <div class="overflow-hidden rounded-2xl border border-sky-300 bg-sky-100 p-5 shadow-sm dark:border-sky-700 dark:bg-sky-500/[0.15]">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Adultos</p>
            <p class="mt-2 text-3xl font-bold text-sky-700 dark:text-sky-400">{{ $cafe['adultos'] }}</p>
        </div>
        <div class="overflow-hidden rounded-2xl border border-orange-300 bg-orange-100 p-5 shadow-sm dark:border-orange-700 dark:bg-orange-500/[0.15]">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Crianças</p>
            <p class="mt-2 text-3xl font-bold text-orange-700 dark:text-orange-400">{{ $cafe['criancas'] }}</p>
        </div>
        <div class="overflow-hidden rounded-2xl border border-rose-300 bg-rose-100 p-5 shadow-sm dark:border-rose-700 dark:bg-rose-500/[0.15]">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Isentos</p>
            <p class="mt-2 text-3xl font-bold text-rose-700 dark:text-rose-400">{{ $cafe['isentos'] }}</p>
        </div>
        <div class="overflow-hidden rounded-2xl border border-emerald-300 bg-emerald-100 p-5 shadow-sm dark:border-emerald-700 dark:bg-emerald-500/[0.15]">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Total</p>
            <p class="mt-2 text-3xl font-bold text-emerald-700 dark:text-emerald-400">{{ $cafe['total'] }}</p>
        </div>
    </div>

    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
        <h2 class="mb-4 text-center text-lg font-semibold text-slate-700 dark:text-slate-200">Lista de hóspedes para o café — {{ $data->format('d/m/Y') }}</h2>

        @forelse ($cafe['hospedagens'] as $hospedagem)
            <div class="border-t border-gray-100 py-3 first:border-t-0 dark:border-gray-800">
                <div class="mb-1 flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                    <span>📍 Quarto {{ $hospedagem->quarto->numero }}</span>
                    <span class="text-xs font-normal text-slate-400">{{ $hospedagem->quantidade_adultos }} Adulto(s) | {{ $hospedagem->quantidade_criancas }} Criança(s)@if($hospedagem->quantidade_isentos) | {{ $hospedagem->quantidade_isentos }} Isento(s) @endif</span>
                </div>
                <div class="flex flex-wrap items-center justify-between text-sm text-slate-600 dark:text-slate-300">
                    <span>👤 {{ $hospedagem->cliente->nome }}</span>
                    <span class="text-slate-400">{{ $hospedagem->data_checkin_prevista->format('d/m/Y') }} até {{ $hospedagem->data_checkout_prevista->format('d/m/Y') }}</span>
                </div>
            </div>
        @empty
            <p class="py-8 text-center text-slate-400">Nenhum hóspede hospedado nessa data.</p>
        @endforelse
    </div>
@endsection
