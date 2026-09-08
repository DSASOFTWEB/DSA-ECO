@extends('layouts.app')

@section('titulo', 'Auditoria')

@section('conteudo')
    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Data/hora</th>
                    <th class="px-4 py-3">Usuário</th>
                    <th class="px-4 py-3">Evento</th>
                    <th class="px-4 py-3">Descrição</th>
                    <th class="px-4 py-3">Registro</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($atividades as $atividade)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                        <td class="px-4 py-3 text-slate-500">{{ $atividade->created_at->format('d/m/Y H:i:s') }}</td>
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $atividade->causer?->name ?? 'Sistema' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $atividade->event }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $atividade->description }}</td>
                        <td class="px-4 py-3 text-slate-400">{{ class_basename($atividade->subject_type) }} #{{ $atividade->subject_id }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Nenhum evento registrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $atividades->links() }}</div>
@endsection
