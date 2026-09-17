@extends('layouts.app')

@section('titulo', 'Editar produto')

@section('conteudo')
    <form method="POST" action="{{ route('produtos.update', $produto) }}" class="max-w-5xl space-y-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        @csrf
        @method('PUT')
        @include('produtos._form', ['produto' => $produto])
        <div class="flex justify-end gap-2">
            <a href="{{ route('produtos.show', $produto) }}" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancelar</a>
            <button class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">Salvar alterações</button>
        </div>
    </form>
@endsection
