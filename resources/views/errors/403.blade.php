@extends('layouts.app')

@section('titulo', 'Acesso não autorizado')

@section('conteudo')
    <div class="flex min-h-[60vh] items-center justify-center p-4">
        <div class="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
            <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-rose-100 dark:bg-rose-500/15">
                <svg class="h-8 w-8 text-rose-600 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Zm-3-9 2 2 4-4" />
                </svg>
            </div>

            <h1 class="mb-2 text-lg font-bold text-gray-800 dark:text-white/90">Acesso não autorizado</h1>
            <p class="mb-6 text-sm text-slate-500 dark:text-slate-400">
                Você não tem autorização para acessar esse módulo. Entre em contato com o administrador do sistema pra liberar o acesso.
            </p>

            <div class="flex flex-wrap justify-center gap-2">
                @if (\Illuminate\Support\Facades\Route::has('dashboard'))
                    <a href="{{ route('dashboard') }}" class="rounded-lg bg-brand-500 px-5 py-2.5 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">
                        Voltar ao painel
                    </a>
                @endif
            </div>
        </div>
    </div>
@endsection
