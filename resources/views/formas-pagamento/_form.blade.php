@php $f = $forma ?? null; @endphp

<div class="space-y-5 text-gray-700 dark:text-gray-300 [&_input]:outline-none [&_select]:outline-none [&_input]:dark:border-gray-700 [&_input]:dark:bg-gray-800 [&_input]:dark:text-white [&_select]:dark:border-gray-700 [&_select]:dark:bg-gray-800 [&_select]:dark:text-white"
     x-data="{ tipo: @js(old('tipo', $f?->tipo ?? 'dinheiro')) }">
    <div>
        <label class="block text-sm font-medium">Nome exibido no caixa</label>
        <input type="text" name="nome" value="{{ old('nome', $f?->nome) }}" required maxlength="60" placeholder="Ex.: Pix QR Code" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        @error('nome')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="block text-sm font-medium">Tipo</label>
            <select name="tipo" x-model="tipo" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @foreach(\App\Models\FormaPagamento::TIPOS as $chave => $rotulo)
                    <option value="{{ $chave }}" @selected(old('tipo', $f?->tipo) === $chave)>{{ $rotulo }}</option>
                @endforeach
            </select>
            @error('tipo')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label class="block text-sm font-medium">Ordem no caixa</label>
            <input type="number" name="ordem" min="0" max="999" value="{{ old('ordem', $f?->ordem ?? 0) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            @error('ordem')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </div>
    </div>

    <div x-show="tipo === 'pix'" x-cloak class="rounded-xl border border-teal-200 bg-teal-50/60 p-4 dark:border-teal-900 dark:bg-teal-500/10">
        <label class="flex items-start gap-3">
            <input type="checkbox" name="integrado" value="1" @checked(old('integrado', $f?->integrado)) @disabled(! $gatewayPixAtivo && ! $f?->integrado) class="mt-0.5 h-4 w-4 rounded border-slate-300">
            <span>
                <span class="block text-sm font-semibold">Pix integrado (QR Code)</span>
                <span class="block text-xs text-slate-500 dark:text-slate-400">
                    Gera o QR Code no gateway da empresa e só conclui a venda/fechamento quando o banco confirmar.
                    Desmarcado, o Pix é registrado manualmente (chave ou maquininha), conferido pelo operador.
                </span>
                @unless($gatewayPixAtivo)
                    <span class="mt-1 block text-xs font-semibold text-amber-700 dark:text-amber-300">Configure o gateway Pix em <a href="{{ route('empresa.edit') }}" class="underline">Minha Empresa</a> para habilitar.</span>
                @endunless
            </span>
        </label>
        @error('integrado')<p class="mt-2 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    <label class="flex items-center gap-3">
        <input type="checkbox" name="ativo" value="1" @checked(old('ativo', $f?->ativo ?? true)) class="h-4 w-4 rounded border-slate-300">
        <span class="text-sm font-medium">Ativa (aparece no PDV e no fechamento de mesas)</span>
    </label>
</div>
