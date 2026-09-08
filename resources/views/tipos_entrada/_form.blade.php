@php $t = $tipoEntrada ?? null; @endphp

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2 [&_label]:text-gray-700 dark:[&_label]:text-gray-300 [&_input]:min-h-11 [&_input]:border-gray-300 [&_input]:bg-transparent [&_input]:text-gray-800 [&_input]:outline-none [&_input]:transition [&_input]:focus:border-brand-500 [&_input]:focus:ring-3 [&_input]:focus:ring-brand-500/10 dark:[&_input]:border-gray-700 dark:[&_input]:text-white/90">
    <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-slate-700">Nome</label>
        <input type="text" name="nome" value="{{ old('nome', $t?->nome) }}" required placeholder="Ex.: Diária Adulto" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium text-slate-700">Valor (R$)</label>
        <input type="number" step="0.01" min="0" name="valor" value="{{ old('valor', $t?->valor) }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
    </div>

    <div class="flex items-center gap-2 sm:col-start-1">
        <input type="checkbox" id="ativo" name="ativo" value="1" @checked(old('ativo', $t?->ativo ?? true)) class="rounded border-slate-300">
        <label for="ativo" class="text-sm text-slate-700">Disponível para venda no PDV</label>
    </div>

    <div class="sm:col-span-2 rounded-lg bg-cyan-50 p-3 dark:bg-cyan-500/10">
        <div class="flex items-center gap-2">
            <input type="checkbox" id="eh_plano" name="eh_plano" value="1" @checked(old('eh_plano', $t?->eh_plano)) class="rounded border-slate-300">
            <label for="eh_plano" class="text-sm font-medium text-slate-700">É para cliente com plano (check-in, sem cobrança)</label>
        </div>
        <p class="mt-1 text-xs text-slate-500">
            No link público de venda online, esta opção não gera cobrança Pix — em vez disso pede CPF ou nome do cliente,
            verifica se o plano está ativo e já gera o voucher de entrada dele, igual ao check-in feito na recepção.
        </p>
    </div>
</div>
