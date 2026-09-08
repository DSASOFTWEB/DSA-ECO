@extends('layouts.app')

@section('titulo', 'Editar plano')

@section('conteudo')
    <form method="POST" action="{{ route('planos.update', $plano) }}" class="max-w-3xl space-y-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-7">
        @csrf
        @method('PUT')
        @include('planos._form', ['plano' => $plano])
        <div class="flex justify-end gap-2">
            <a href="{{ route('planos.index') }}" class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Cancelar</a>
            <button class="h-11 rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">Salvar alterações</button>
        </div>
    </form>
@endsection
