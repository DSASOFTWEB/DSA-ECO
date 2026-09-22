@extends('layouts.app')

@section('titulo', 'Novo usuário')

@section('conteudo')
    @php
        $modulos = \App\Support\ModulosPermissoes::modulos();
        $permissoesPorPapel = \App\Support\ModulosPermissoes::permissoesPorPapel();
        $labelsPapeis = \App\Support\ModulosPermissoes::labelsPapeis();
        $permissoesIniciais = old('permissions', []);
        $papeisIniciais = old('roles', []);
    @endphp

    <form
        method="POST"
        action="{{ route('usuarios.store') }}"
        x-data="usuarioPermissoesForm(@js($permissoesIniciais), @js($papeisIniciais), @js($permissoesPorPapel))"
        class="max-w-5xl space-y-5 rounded-2xl border border-gray-200 bg-white p-6 text-gray-700 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 [&_input]:outline-none [&_input]:transition [&_input]:focus:border-brand-500 [&_input]:focus:ring-3 [&_input]:focus:ring-brand-500/10 [&_input]:dark:border-gray-700 [&_input]:dark:bg-gray-800 [&_input]:dark:text-white [&_select]:outline-none [&_select]:transition [&_select]:focus:border-brand-500 [&_select]:focus:ring-3 [&_select]:focus:ring-brand-500/10 [&_select]:dark:border-gray-700 [&_select]:dark:bg-gray-800 [&_select]:dark:text-white"
    >
        @csrf
        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-slate-700">Nome</label>
                <input type="text" name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">E-mail</label>
                <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Senha</label>
                <input type="password" name="password" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Unidade</label>
                <select name="unidade_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todas</option>
                    @foreach ($unidades as $unidade)
                        <option value="{{ $unidade->id }}">{{ $unidade->nome }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="flex flex-wrap gap-5">
            <div>
                <label class="block text-sm font-medium text-slate-700">Percentual de comissão</label>
                <div class="mt-1 flex items-center gap-2">
                    <input type="number" step="0.01" min="0" max="100" name="percentual_comissao" value="{{ old('percentual_comissao') }}" placeholder="Sem comissão" class="w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <span class="text-sm text-slate-400">%</span>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Comissão de reativação</label>
                <div class="mt-1 flex items-center gap-2">
                    <input type="number" step="0.01" min="0" max="100" name="percentual_comissao_reativacao" value="{{ old('percentual_comissao_reativacao') }}" placeholder="Sem comissão" class="w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <span class="text-sm text-slate-400">%</span>
                </div>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700">Perfis</label>
            <p class="mt-1 text-xs text-slate-400">O perfil aplica um pacote padrão nos módulos abaixo (você pode ajustar).</p>
            <template x-for="papel in papeisSelecionados" :key="'role-'+papel">
                <input type="hidden" name="roles[]" :value="papel">
            </template>
            <div class="mt-2 grid gap-2 rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-white/[0.03] sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($roles as $role)
                    <label class="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            class="rounded border-slate-300"
                            :checked="papeisSelecionados.includes(@js($role->name))"
                            @change="togglePapel(@js($role->name), $event.target.checked)"
                        >
                        {{ $labelsPapeis[$role->name] ?? $role->name }}
                    </label>
                @endforeach
            </div>
            @error('roles')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>

        @include('usuarios._modulos_permissoes', ['modulos' => $modulos])

        <div class="flex justify-end gap-2">
            <a href="{{ route('usuarios.index') }}" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancelar</a>
            <button class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">Salvar usuário</button>
        </div>
    </form>

    @include('usuarios._permissoes_script')
@endsection
