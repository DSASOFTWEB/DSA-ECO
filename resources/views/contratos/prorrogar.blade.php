@extends('layouts.app')

@section('titulo', 'Prorrogar cobrança')

@section('conteudo')
    <form method="POST" action="{{ route('contratos.prorrogar', $contrato) }}" class="max-w-xl space-y-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-7">
        @csrf
        @method('PATCH')

        <div>
            <p class="text-sm text-slate-500">Contrato {{ $contrato->numero_contrato }}</p>
            <p class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $contrato->cliente->nome }}</p>
        </div>

        @if ($proximaMensalidade)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-500/10 dark:text-amber-300">
                Vencimento atual: <strong>{{ $proximaMensalidade->data_vencimento->format('d/m/Y') }}</strong>
                · R$ {{ number_format($proximaMensalidade->valor_total, 2, ',', '.') }}
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-gray-300">Novo vencimento</label>
                <input type="date" name="nova_data_vencimento" value="{{ old('nova_data_vencimento', $proximaMensalidade->data_vencimento->copy()->addDays(30)->toDateString()) }}" min="{{ $proximaMensalidade->data_vencimento->copy()->addDay()->toDateString() }}" required class="mt-1 h-11 w-full rounded-lg border border-gray-300 px-3 text-sm dark:border-gray-700 dark:bg-gray-900 dark:text-white/90">
                @error('nova_data_vencimento')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>

            <p class="text-xs text-slate-500">A prorrogação altera somente a próxima mensalidade aberta. A caução e os pagamentos já realizados permanecem inalterados.</p>
        @else
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">Não há mensalidade aberta para prorrogar.</div>
        @endif

        <div class="flex justify-end gap-2">
            <a href="{{ route('contratos.show', $contrato) }}" class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-5 text-sm font-medium text-gray-700">Voltar</a>
            @if ($proximaMensalidade)
                <button class="h-11 rounded-lg bg-amber-600 px-5 text-sm font-medium text-white hover:bg-amber-700">Confirmar prorrogação</button>
            @endif
        </div>
    </form>
@endsection
