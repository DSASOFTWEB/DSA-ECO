@extends('layouts.app')

@section('titulo', 'Usuários')

@section('conteudo')
    <div class="mb-6 flex justify-end">
        @can('create', \App\Models\User::class)
            <a href="{{ route('usuarios.create') }}" class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">+ Novo usuário</a>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Nome</th>
                    <th class="px-4 py-3">E-mail</th>
                    <th class="px-4 py-3">Unidade</th>
                    <th class="px-4 py-3">Papéis</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($users as $user)
                    <tr class="transition hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                        <td class="px-4 py-3 font-medium text-slate-800">{{ $user->name }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $user->email }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $user->unidade?->nome ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-500">
                            {{ $user->roles->map(fn ($r) => \App\Support\ModulosPermissoes::labelPapel($r->name))->join(', ') ?: '—' }}
                        </td>
                        <td class="px-4 py-3"><x-status-badge :status="$user->status" /></td>
                        <td class="px-4 py-3 text-right">
                            @can('update', $user)
                                <a href="{{ route('usuarios.edit', $user) }}" class="text-sky-700 hover:underline">Editar</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Nenhum usuário cadastrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
@endsection
