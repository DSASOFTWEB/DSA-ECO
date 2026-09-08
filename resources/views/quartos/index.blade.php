@extends('layouts.app')

@section('titulo', 'Quartos')

@section('conteudo')
    <div class="mb-6 flex items-center justify-between gap-3">
        <p class="text-sm text-slate-500 dark:text-slate-400">Cadastro dos quartos da pousada — número, capacidade e valor da diária. Reservas e check-in ficam em <a href="{{ route('hospedagens.index') }}" class="underline">Pousada</a>.</p>
        @can('create', \App\Models\Quarto::class)
            <a href="{{ route('quartos.create') }}" class="shrink-0 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">+ Novo quarto</a>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-slate-100 text-sm">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Quarto</th>
                    <th class="px-4 py-3">Unidade</th>
                    <th class="px-4 py-3">Capacidade</th>
                    <th class="px-4 py-3">Diária</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($quartos as $quarto)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                        <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-100">{{ $quarto->numero }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $quarto->unidade->nome }}</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $quarto->capacidade_maxima }} pessoa(s)</td>
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">R$ {{ number_format($quarto->valor_diaria, 2, ',', '.') }}</td>
                        <td class="px-4 py-3"><x-status-badge :status="$quarto->status" /></td>
                        <td class="px-4 py-3 text-right">
                            @can('update', $quarto)
                                <a href="{{ route('quartos.edit', $quarto) }}" class="text-sky-700 hover:underline dark:text-sky-400">Editar</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Nenhum quarto cadastrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $quartos->links() }}</div>
@endsection
