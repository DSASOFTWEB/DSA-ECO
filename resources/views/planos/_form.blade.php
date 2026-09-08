@php $p = $plano ?? null; @endphp

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2 [&_label]:text-gray-700 dark:[&_label]:text-gray-300 [&_input]:min-h-11 [&_input]:border-gray-300 [&_input]:bg-transparent [&_input]:text-gray-800 [&_input]:outline-none [&_input]:transition [&_input]:focus:border-brand-500 [&_input]:focus:ring-3 [&_input]:focus:ring-brand-500/10 dark:[&_input]:border-gray-700 dark:[&_input]:text-white/90 [&_select]:min-h-11 [&_select]:border-gray-300 [&_select]:bg-transparent [&_select]:text-gray-800 [&_select]:outline-none [&_select]:focus:border-brand-500 dark:[&_select]:border-gray-700 dark:[&_select]:bg-gray-900 dark:[&_select]:text-white/90 [&_textarea]:border-gray-300 [&_textarea]:bg-transparent [&_textarea]:text-gray-800 [&_textarea]:outline-none [&_textarea]:focus:border-brand-500 dark:[&_textarea]:border-gray-700 dark:[&_textarea]:text-white/90">
    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-slate-700">Nome do plano</label>
        <input type="text" name="nome" value="{{ old('nome', $p?->nome) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-slate-700">Descrição</label>
        <textarea name="descricao" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('descricao', $p?->descricao) }}</textarea>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">Valor (R$)</label>
        <input type="number" step="0.01" min="0" name="valor" value="{{ old('valor', $p?->valor) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">Periodicidade</label>
        <select name="periodicidade" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            @foreach (['mensal', 'trimestral', 'semestral', 'anual'] as $opcao)
                <option value="{{ $opcao }}" @selected(old('periodicidade', $p?->periodicidade ?? 'mensal') === $opcao)>{{ ucfirst($opcao) }}</option>
            @endforeach
        </select>
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">Máx. dependentes</label>
        <input type="number" min="0" name="max_dependentes" value="{{ old('max_dependentes', $p?->max_dependentes ?? 0) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">Dias de acesso/semana</label>
        <input type="number" min="1" max="7" name="dias_acesso_semana" value="{{ old('dias_acesso_semana', $p?->dias_acesso_semana ?? 7) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div class="flex items-center gap-2">
        <input type="checkbox" id="permite_congelamento" name="permite_congelamento" value="1" @checked(old('permite_congelamento', $p?->permite_congelamento)) class="rounded border-slate-300">
        <label for="permite_congelamento" class="text-sm text-slate-700">Permite congelamento</label>
    </div>

    <div class="flex items-center gap-2">
        <input type="checkbox" id="ativo" name="ativo" value="1" @checked(old('ativo', $p?->ativo ?? true)) class="rounded border-slate-300">
        <label for="ativo" class="text-sm text-slate-700">Plano ativo (disponível para novos contratos)</label>
    </div>
</div>
