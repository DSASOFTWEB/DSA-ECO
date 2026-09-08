@extends('layouts.app')

@section('titulo', 'Editar cliente')

@section('conteudo')
    <form method="POST" action="{{ route('clientes.update', $cliente) }}" enctype="multipart/form-data" class="max-w-4xl space-y-6 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03] sm:p-7">
        @csrf
        @method('PUT')
        @include('clientes._form', ['cliente' => $cliente])

        <div class="flex justify-end gap-2">
            <a href="{{ route('clientes.show', $cliente) }}" class="inline-flex h-11 items-center rounded-lg border border-gray-300 px-5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300">Cancelar</a>
            <button class="h-11 rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">Salvar alterações</button>
        </div>
    </form>
@endsection
