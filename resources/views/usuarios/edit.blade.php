@extends('layouts.app')

@section('titulo', 'Editar usuário')

@section('conteudo')
    <form method="POST" action="{{ route('usuarios.update', $user) }}" class="max-w-3xl space-y-5 rounded-2xl border border-gray-200 bg-white p-6 text-gray-700 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 [&_input]:outline-none [&_input]:transition [&_input]:focus:border-brand-500 [&_input]:focus:ring-3 [&_input]:focus:ring-brand-500/10 [&_input]:dark:border-gray-700 [&_input]:dark:bg-gray-800 [&_input]:dark:text-white [&_select]:outline-none [&_select]:transition [&_select]:focus:border-brand-500 [&_select]:focus:ring-3 [&_select]:focus:ring-brand-500/10 [&_select]:dark:border-gray-700 [&_select]:dark:bg-gray-800 [&_select]:dark:text-white">
        @csrf
        @method('PUT')
        <div>
            <label class="block text-sm font-medium text-slate-700">Nome</label>
            <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">E-mail</label>
            <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Unidade</label>
            <select name="unidade_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todas</option>
                @foreach ($unidades as $unidade)
                    <option value="{{ $unidade->id }}" @selected(old('unidade_id', $user->unidade_id) == $unidade->id)>{{ $unidade->nome }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700">Status</label>
            <select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="ativo" @selected(old('status', $user->status) === 'ativo')>Ativo</option>
                <option value="inativo" @selected(old('status', $user->status) === 'inativo')>Inativo</option>
            </select>
        </div>
        <div class="flex flex-wrap gap-5">
            <div>
                <label class="block text-sm font-medium text-slate-700">Percentual de comissão</label>
                <div class="mt-1 flex items-center gap-2">
                    <input type="number" step="0.01" min="0" max="100" name="percentual_comissao" value="{{ old('percentual_comissao', $user->percentual_comissao) }}" placeholder="Sem comissão" class="w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <span class="text-sm text-slate-400">%</span>
                </div>
                <p class="mt-1 max-w-52 text-xs text-slate-400">Venda no PDV e contrato novo. Em branco não comissiona.</p>
                @error('percentual_comissao')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Comissão de reativação</label>
                <div class="mt-1 flex items-center gap-2">
                    <input type="number" step="0.01" min="0" max="100" name="percentual_comissao_reativacao" value="{{ old('percentual_comissao_reativacao', $user->percentual_comissao_reativacao) }}" placeholder="Sem comissão" class="w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <span class="text-sm text-slate-400">%</span>
                </div>
                <p class="mt-1 max-w-52 text-xs text-slate-400">Contrato cancelado que voltou a ficar ativo. Em branco não comissiona.</p>
                @error('percentual_comissao_reativacao')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-white/[0.03]">
            <p class="mb-3 text-sm font-medium text-slate-700">Alterar senha <span class="font-normal text-slate-400">(opcional — deixe em branco para manter a senha atual)</span></p>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-xs font-medium text-slate-500">Nova senha</label>
                    <input type="password" name="password" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm @error('password') border-rose-500 @enderror">
                    @error('password')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500">Confirmar nova senha</label>
                    <input type="password" name="password_confirmation" autocomplete="new-password" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
        </div>
        @can('gerenciarPapeis', $user)
            <div>
                <label class="block text-sm font-medium text-slate-700">Papéis</label>
                <div class="mt-2 grid gap-2 rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-white/[0.03] sm:grid-cols-2">
                    @foreach ($roles as $role)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="roles[]" value="{{ $role->name }}" @checked($user->hasRole($role->name)) class="rounded border-slate-300">
                            {{ $role->name }}
                        </label>
                    @endforeach
                </div>
            </div>
        @endcan
        <div class="flex justify-end gap-2">
            <a href="{{ route('usuarios.index') }}" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancelar</a>
            <button class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">Salvar alterações</button>
        </div>
    </form>
@endsection
