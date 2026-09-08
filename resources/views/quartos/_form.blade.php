@php $q = $quarto ?? null; @endphp

<div class="grid grid-cols-1 gap-5 text-gray-700 dark:text-gray-300 sm:grid-cols-2 [&_input]:outline-none [&_input]:transition [&_input]:focus:border-brand-500 [&_input]:focus:ring-3 [&_input]:focus:ring-brand-500/10 [&_input]:dark:border-gray-700 [&_input]:dark:bg-gray-800 [&_input]:dark:text-white [&_select]:outline-none [&_select]:transition [&_select]:focus:border-brand-500 [&_select]:focus:ring-3 [&_select]:focus:ring-brand-500/10 [&_select]:dark:border-gray-700 [&_select]:dark:bg-gray-800 [&_select]:dark:text-white">
    <div>
        <label class="block text-sm font-medium text-slate-700">Número/Nome do quarto</label>
        <input type="text" name="numero" value="{{ old('numero', $q?->numero) }}" required placeholder="ex: 101, Chalé 3" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        @error('numero')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">Unidade</label>
        <select name="unidade_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Selecione...</option>
            @foreach ($unidades as $unidade)
                <option value="{{ $unidade->id }}" @selected(old('unidade_id', $q?->unidade_id) == $unidade->id)>{{ $unidade->nome }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">Capacidade máxima (hóspedes)</label>
        <input type="number" min="1" max="50" name="capacidade_maxima" value="{{ old('capacidade_maxima', $q?->capacidade_maxima ?? 2) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">Valor da diária (R$)</label>
        <input type="number" step="0.01" min="0" name="valor_diaria" value="{{ old('valor_diaria', $q?->valor_diaria) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium text-slate-700">Status</label>
        <select name="status" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="ativo" @selected(old('status', $q?->status ?? 'ativo') === 'ativo')>Ativo</option>
            <option value="manutencao" @selected(old('status', $q?->status) === 'manutencao')>Em manutenção</option>
            <option value="inativo" @selected(old('status', $q?->status) === 'inativo')>Inativo</option>
        </select>
    </div>
    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-slate-700">Observações</label>
        <textarea name="observacoes" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('observacoes', $q?->observacoes) }}</textarea>
    </div>
</div>
