@php $u = $unidade ?? null; @endphp

<div class="grid grid-cols-1 gap-5 text-gray-700 dark:text-gray-300 sm:grid-cols-2 [&_input]:outline-none [&_input]:transition [&_input]:focus:border-brand-500 [&_input]:focus:ring-3 [&_input]:focus:ring-brand-500/10 [&_input]:dark:border-gray-700 [&_input]:dark:bg-gray-800 [&_input]:dark:text-white">
    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-slate-700">Nome da unidade</label>
        <input type="text" name="nome" value="{{ old('nome', $u?->nome) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>
    <div><label class="block text-sm font-medium text-slate-700">CNPJ</label><input type="text" name="cnpj" value="{{ old('cnpj', $u?->cnpj) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="block text-sm font-medium text-slate-700">Telefone</label><input type="text" name="telefone" value="{{ old('telefone', $u?->telefone) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div class="sm:col-span-2"><label class="block text-sm font-medium text-slate-700">Endereço</label><input type="text" name="endereco" value="{{ old('endereco', $u?->endereco) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="block text-sm font-medium text-slate-700">Número</label><input type="text" name="numero" value="{{ old('numero', $u?->numero) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="block text-sm font-medium text-slate-700">Bairro</label><input type="text" name="bairro" value="{{ old('bairro', $u?->bairro) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="block text-sm font-medium text-slate-700">Cidade</label><input type="text" name="cidade" value="{{ old('cidade', $u?->cidade) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
    <div><label class="block text-sm font-medium text-slate-700">UF</label><input type="text" maxlength="2" name="uf" value="{{ old('uf', $u?->uf) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm uppercase"></div>
    <div><label class="block text-sm font-medium text-slate-700">Capacidade máxima</label><input type="number" min="1" name="capacidade_maxima" value="{{ old('capacidade_maxima', $u?->capacidade_maxima) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
</div>
