@extends('layouts.app')

@section('titulo', 'Nova forma de pagamento')

@section('conteudo')
    <form method="POST" action="{{ route('formas-pagamento.store') }}" class="max-w-xl space-y-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        @csrf
        @include('formas-pagamento._form', ['forma' => null])
        <div class="flex justify-end gap-2">
            <a href="{{ route('formas-pagamento.index') }}" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancelar</a>
            <button class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">Salvar forma</button>
        </div>
    </form>
@endsection
