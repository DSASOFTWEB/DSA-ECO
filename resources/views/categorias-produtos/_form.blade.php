@php $c = $categoria ?? null; @endphp

<div class="grid grid-cols-1 gap-5 text-gray-700 dark:text-gray-300 sm:grid-cols-2 [&_input]:outline-none [&_input]:transition [&_input]:focus:border-brand-500 [&_input]:focus:ring-3 [&_input]:focus:ring-brand-500/10 [&_input]:dark:border-gray-700 [&_input]:dark:bg-gray-800 [&_input]:dark:text-white [&_select]:outline-none [&_select]:transition [&_select]:focus:border-brand-500 [&_select]:focus:ring-3 [&_select]:focus:ring-brand-500/10 [&_select]:dark:border-gray-700 [&_select]:dark:bg-gray-800 [&_select]:dark:text-white">
    <div>
        <label class="block text-sm font-medium text-slate-700">Nome</label>
        <input type="text" name="nome" value="{{ old('nome', $c?->nome) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        @error('nome')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">Categoria mãe (opcional)</label>
        <select name="categoria_pai_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">— Categoria raiz —</option>
            @foreach ($categoriasRaizes as $raiz)
                <option value="{{ $raiz->id }}" @selected(old('categoria_pai_id', $c?->categoria_pai_id) == $raiz->id)>{{ $raiz->nome }}</option>
            @endforeach
        </select>
        <p class="mt-1 text-xs text-slate-400">Se escolher uma, essa categoria vira subcategoria dela.</p>
        @error('categoria_pai_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
</div>
