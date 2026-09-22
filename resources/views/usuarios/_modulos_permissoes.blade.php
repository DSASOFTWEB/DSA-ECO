@php
    /** @var array<string, array{label:string, permissoes:array<string, string>}> $modulos */
    $modulos = $modulos ?? \App\Support\ModulosPermissoes::modulos();
@endphp

<template x-for="perm in permissoesSelecionadas" :key="'perm-'+perm">
    <input type="hidden" name="permissions[]" :value="perm">
</template>

<div class="space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-slate-700">Módulos que o usuário pode acessar</h3>
            <p class="mt-1 text-xs text-slate-400">Marque as ações liberadas. Ao escolher um perfil, o pacote padrão é aplicado nos módulos (você pode ajustar).</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="marcarTodosModulos()" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Marcar todos</button>
            <button type="button" @click="limparModulos()" class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Limpar</button>
        </div>
    </div>

    <div class="grid gap-3 lg:grid-cols-2">
        @foreach ($modulos as $chave => $modulo)
            @php $permsModulo = array_keys($modulo['permissoes']); @endphp
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-white/[0.03]"
                 x-data="{ aberto: true }">
                <div class="flex items-center justify-between gap-2">
                    <label class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-white/90">
                        <input
                            type="checkbox"
                            class="rounded border-slate-300"
                            :checked="moduloCompleto(@js($permsModulo))"
                            @change="alternarModulo(@js($permsModulo), $event.target.checked)"
                        >
                        {{ $modulo['label'] }}
                    </label>
                    <button type="button" class="text-xs text-sky-700 hover:underline" @click="aberto = !aberto" x-text="aberto ? 'ocultar' : 'mostrar'"></button>
                </div>
                <div class="mt-3 grid gap-2 sm:grid-cols-2" x-show="aberto" x-cloak>
                    @foreach ($modulo['permissoes'] as $nome => $rotulo)
                        <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-gray-300">
                            <input
                                type="checkbox"
                                class="rounded border-slate-300"
                                :checked="permissoesSelecionadas.includes(@js($nome))"
                                @change="togglePermissao(@js($nome), $event.target.checked)"
                            >
                            {{ $rotulo }}
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
    @error('permissions')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
    @error('permissions.*')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
</div>
