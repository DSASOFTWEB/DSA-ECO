@php $p = $produto ?? null; @endphp

<div class="grid grid-cols-1 gap-5 text-gray-700 dark:text-gray-300 sm:grid-cols-2 [&_input]:outline-none [&_input]:transition [&_input]:focus:border-brand-500 [&_input]:focus:ring-3 [&_input]:focus:ring-brand-500/10 [&_input]:dark:border-gray-700 [&_input]:dark:bg-gray-800 [&_input]:dark:text-white [&_select]:outline-none [&_select]:transition [&_select]:focus:border-brand-500 [&_select]:focus:ring-3 [&_select]:focus:ring-brand-500/10 [&_select]:dark:border-gray-700 [&_select]:dark:bg-gray-800 [&_select]:dark:text-white [&_textarea]:outline-none [&_textarea]:transition [&_textarea]:focus:border-brand-500 [&_textarea]:focus:ring-3 [&_textarea]:focus:ring-brand-500/10 [&_textarea]:dark:border-gray-700 [&_textarea]:dark:bg-gray-800 [&_textarea]:dark:text-white">
    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-slate-700">Nome do produto</label>
        <input type="text" name="nome" value="{{ old('nome', $p?->nome) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">Categoria</label>
        <select name="categoria_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">—</option>
            @foreach ($categorias as $categoria)
                <option value="{{ $categoria->id }}" @selected(old('categoria_id', $p?->categoria_id) == $categoria->id)>{{ $categoria->nome }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">SKU</label>
        <input type="text" name="sku" value="{{ old('sku', $p?->sku) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">Preço de custo (R$)</label>
        <input type="number" step="0.01" min="0" name="preco_custo" value="{{ old('preco_custo', $p?->preco_custo ?? 0) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">Preço de venda (R$)</label>
        <input type="number" step="0.01" min="0" name="preco_venda" value="{{ old('preco_venda', $p?->preco_venda) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    @unless ($p)
        <div>
            <label class="block text-sm font-medium text-slate-700">Estoque inicial</label>
            <input type="number" min="0" name="estoque_atual" value="{{ old('estoque_atual', 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
    @endunless

    <div>
        <label class="block text-sm font-medium text-slate-700">Estoque mínimo</label>
        <input type="number" min="0" name="estoque_minimo" value="{{ old('estoque_minimo', $p?->estoque_minimo ?? 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div class="flex items-center gap-2">
        <input type="checkbox" id="controla_estoque" name="controla_estoque" value="1" @checked(old('controla_estoque', $p?->controla_estoque ?? true)) class="rounded border-slate-300">
        <label for="controla_estoque" class="text-sm text-slate-700">Controla estoque</label>
    </div>

    <div class="flex items-center gap-2">
        <input type="checkbox" id="ativo" name="ativo" value="1" @checked(old('ativo', $p?->ativo ?? true)) class="rounded border-slate-300">
        <label for="ativo" class="text-sm text-slate-700">Produto ativo</label>
    </div>

    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-slate-700">Descrição</label>
        <textarea name="descricao" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('descricao', $p?->descricao) }}</textarea>
    </div>
</div>
