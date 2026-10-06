@props(['label' => null, 'name', 'accept' => null, 'help' => null, 'placeholder' => 'Nenhum arquivo selecionado', 'icon' => 'upload'])
@php($id = $attributes->get('id', $name))
<div x-data="{ arquivo: '' }">
    @if ($label)
        <label for="{{ $id }}" class="mb-1 block text-sm font-medium text-slate-700 dark:text-gray-300">{{ $label }}</label>
    @endif
    <label for="{{ $id }}" @class([
        'group flex h-11 cursor-pointer items-center gap-3 rounded-lg border border-dashed bg-white pl-1.5 pr-3 text-sm transition hover:border-brand-400 hover:bg-brand-50/40 focus-within:border-brand-500 focus-within:ring-3 focus-within:ring-brand-500/10 dark:bg-transparent dark:hover:bg-white/[0.03]',
        'border-gray-300 dark:border-gray-700' => ! $errors->has($name),
        'border-rose-500' => $errors->has($name),
    ])>
        <span class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-md border border-gray-200 bg-gray-50 px-3 text-xs font-medium text-slate-700 shadow-theme-xs transition group-hover:border-brand-200 group-hover:bg-white group-hover:text-brand-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
            <x-dynamic-component :component="'lucide-'.$icon" class="h-3.5 w-3.5" />
            Escolher arquivo
        </span>
        <span class="min-w-0 flex-1 truncate" :class="arquivo ? 'font-medium text-slate-700 dark:text-white/90' : 'text-slate-400'" x-text="arquivo || @js($placeholder)">{{ $placeholder }}</span>
        <button type="button" x-show="arquivo" x-cloak @click.prevent="$refs.campo.value = ''; arquivo = ''" class="grid h-6 w-6 shrink-0 place-items-center rounded-md text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="Remover seleção">
            <x-lucide-x class="h-3.5 w-3.5" />
        </button>
        <input
            type="file"
            name="{{ $name }}"
            id="{{ $id }}"
            x-ref="campo"
            @change="arquivo = $event.target.files[0]?.name ?? ''"
            @if ($accept) accept="{{ $accept }}" @endif
            {{ $attributes->except('id')->merge(['class' => 'sr-only !min-h-0 !border-0']) }}
        >
    </label>
    @error($name)
        <p class="mt-1.5 text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
    @else
        @if ($help)<p class="mt-1.5 text-xs text-slate-400">{{ $help }}</p>@endif
    @enderror
</div>
