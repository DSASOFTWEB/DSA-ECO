@php $f = $fornecedor ?? null; @endphp

<div class="grid grid-cols-1 gap-5 text-gray-700 dark:text-gray-300 sm:grid-cols-2 [&_input]:outline-none [&_input]:transition [&_input]:focus:border-brand-500 [&_input]:focus:ring-3 [&_input]:focus:ring-brand-500/10 [&_input]:dark:border-gray-700 [&_input]:dark:bg-gray-800 [&_input]:dark:text-white">
    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-slate-700">Nome</label>
        <input type="text" name="nome" value="{{ old('nome', $f?->nome) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        @error('nome')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">CNPJ / CPF</label>
        <input type="text" name="documento" value="{{ old('documento', $f?->documento) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">Telefone</label>
        <input type="text" name="telefone" value="{{ old('telefone', $f?->telefone) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-slate-700">Pessoa de contato</label>
        <input type="text" name="contato" value="{{ old('contato', $f?->contato) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
</div>
