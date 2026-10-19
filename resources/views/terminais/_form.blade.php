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

    {{-- Série própria de NFC-e: cada caixa com numeração independente --}}
    <fieldset class="rounded-xl border border-slate-200 p-4 sm:col-span-2 dark:border-gray-700">
        <legend class="px-1 text-sm font-semibold text-slate-700 dark:text-slate-200">NFC-e deste terminal</legend>
        <p class="mb-3 text-xs text-slate-500 dark:text-slate-400">
            Com vários caixas emitindo ao mesmo tempo, dê a cada terminal uma série própria: cada série tem numeração independente.
            Deixe a série em branco para usar a série padrão da empresa (<strong>série {{ $seriePadraoEmpresa }}</strong>, em Minha Empresa).
        </p>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-slate-700">Série</label>
                <input type="number" name="serie_nfce" min="1" max="999" value="{{ old('serie_nfce', $t?->serie_nfce) }}" placeholder="padrão ({{ $seriePadraoEmpresa }})" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('serie_nfce')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Último nº emitido — produção</label>
                <input type="number" name="numero_ultima_nfce_producao" min="0" value="{{ old('numero_ultima_nfce_producao', $t?->numero_ultima_nfce_producao ?? 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('numero_ultima_nfce_producao')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700">Último nº emitido — homologação</label>
                <input type="number" name="numero_ultima_nfce_homologacao" min="0" value="{{ old('numero_ultima_nfce_homologacao', $t?->numero_ultima_nfce_homologacao ?? 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('numero_ultima_nfce_homologacao')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
            </div>
        </div>
        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">A próxima nota sai com o número seguinte. Série nova: deixe 0. Só vale quando a série está preenchida.</p>
    </fieldset>
</div>
