@php $t = $terminal ?? null; @endphp

<div class="grid grid-cols-1 gap-5 text-gray-700 dark:text-gray-300 sm:grid-cols-2 [&_input]:outline-none [&_input]:transition [&_input]:focus:border-brand-500 [&_input]:focus:ring-3 [&_input]:focus:ring-brand-500/10 [&_input]:dark:border-gray-700 [&_input]:dark:bg-gray-800 [&_input]:dark:text-white [&_select]:outline-none [&_select]:transition [&_select]:focus:border-brand-500 [&_select]:focus:ring-3 [&_select]:focus:ring-brand-500/10 [&_select]:dark:border-gray-700 [&_select]:dark:bg-gray-800 [&_select]:dark:text-white">
    <div>
        <label class="block text-sm font-medium text-slate-700">Nome do terminal</label>
        <input type="text" name="nome" value="{{ old('nome', $t?->nome) }}" required placeholder="ex: Caixa 1, Recepção, PDV Loja" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">Unidade</label>
        <select name="unidade_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Selecione...</option>
            @foreach ($unidades as $unidade)
                <option value="{{ $unidade->id }}" @selected(old('unidade_id', $t?->unidade_id) == $unidade->id)>{{ $unidade->nome }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">Status</label>
        <select name="status" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="ativo" @selected(old('status', $t?->status ?? 'ativo') === 'ativo')>Ativo</option>
            <option value="inativo" @selected(old('status', $t?->status) === 'inativo')>Inativo</option>
        </select>
    </div>
</div>
