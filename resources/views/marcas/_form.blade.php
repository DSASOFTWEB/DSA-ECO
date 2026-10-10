@php $m = $marca ?? null; @endphp

<div class="text-gray-700 dark:text-gray-300 [&_input]:outline-none [&_input]:transition [&_input]:focus:border-brand-500 [&_input]:focus:ring-3 [&_input]:focus:ring-brand-500/10 [&_input]:dark:border-gray-700 [&_input]:dark:bg-gray-800 [&_input]:dark:text-white">
    <label class="block text-sm font-medium text-slate-700">Nome</label>
    <input type="text" name="nome" value="{{ old('nome', $m?->nome) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    @error('nome')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
</div>
