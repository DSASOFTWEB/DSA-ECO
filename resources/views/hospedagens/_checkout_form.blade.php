@php
    $caixasDisponiveis = $caixasDisponiveis ?? collect();
    $temVariosCaixas = ! $caixaAberto && $caixasDisponiveis->count() > 1;
@endphp
<div
    x-data="{
        valorDiaria: {{ (float) $hospedagem->valor_diaria }},
        noites: {{ (int) $noites }},
        totalConsumos: {{ (float) $hospedagem->consumos->sum('subtotal') }},
        desconto: 0,
        get total() { return Math.max(0, (this.valorDiaria * this.noites) + this.totalConsumos - (Number(this.desconto) || 0)) },
        formatar(valor) { return 'R$ ' + Number(valor || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) }
    }"
>
    <p class="mb-4 text-sm text-slate-500">{{ $hospedagem->cliente->nome }} · <span x-text="noites"></span> diária(s)</p>

    <dl class="mb-4 space-y-2 text-sm">
        <div class="flex justify-between"><dt class="text-slate-500">Diárias (<span x-text="noites"></span> × R$ {{ number_format($hospedagem->valor_diaria, 2, ',', '.') }})</dt><dd x-text="formatar(valorDiaria * noites)"></dd></div>
        <div class="flex justify-between"><dt class="text-slate-500">Consumos</dt><dd x-text="formatar(totalConsumos)"></dd></div>
    </dl>

    <form method="POST" action="{{ route('hospedagens.checkout.store', $hospedagem) }}" class="space-y-4">
        @csrf

        @if ($temVariosCaixas)
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Caixa / terminal</label>
                <select name="caixa_id" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    <option value="">Selecione...</option>
                    @foreach ($caixasDisponiveis as $caixa)
                        <option value="{{ $caixa->id }}">{{ $caixa->terminal->nome ?? $caixa->unidade->nome }} ({{ $caixa->unidade->nome }})</option>
                    @endforeach
                </select>
            </div>
        @else
            <input type="hidden" name="caixa_id" value="{{ $caixaAberto->id }}">
        @endif

        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Desconto (R$)</label>
            <input type="number" step="0.01" min="0" name="desconto" x-model.number="desconto" value="0" class="mt-1 w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">Forma de pagamento</label>
            <select name="forma_pagamento" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                <option value="dinheiro">Dinheiro</option>
                <option value="cartao_credito">Cartão de crédito</option>
                <option value="cartao_debito">Cartão de débito</option>
                <option value="pix">Pix</option>
            </select>
        </div>

        <fieldset class="rounded-xl border border-slate-200 p-4 dark:border-gray-700">
            <legend class="px-1 text-sm font-semibold text-slate-700 dark:text-slate-200">Documentos fiscais</legend>
            <p class="mb-3 text-xs text-slate-500">Emitir após o fechamento da conta.</p>
            <div class="space-y-3">
                <label class="flex items-start gap-3 {{ $quantidadeItensNfce === 0 ? 'cursor-not-allowed opacity-50' : 'cursor-pointer' }}">
                    <input type="checkbox" name="emitir_nfce" value="1" @checked(old('emitir_nfce')) @disabled($quantidadeItensNfce === 0) class="mt-0.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                    <span>
                        <span class="block text-sm font-medium text-slate-700 dark:text-slate-200">Emitir NFC-e (produtos)</span>
                        <span class="block text-xs text-slate-500">{{ $quantidadeItensNfce }} item(ns)</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 {{ $quantidadeItensNfse === 0 ? 'cursor-not-allowed opacity-50' : 'cursor-pointer' }}">
                    <input type="checkbox" name="emitir_nfse" value="1" @checked(old('emitir_nfse')) @disabled($quantidadeItensNfse === 0) class="mt-0.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                    <span>
                        <span class="block text-sm font-medium text-slate-700 dark:text-slate-200">Emitir NFS-e (hospedagem)</span>
                        <span class="block text-xs text-slate-500">{{ $quantidadeItensNfse }} item(ns)</span>
                    </span>
                </label>
            </div>
        </fieldset>

        <div class="flex items-center justify-between rounded-2xl bg-gray-900 p-5 text-white">
            <span class="text-sm text-gray-300">Total a cobrar</span>
            <span class="text-2xl font-bold" x-text="formatar(total)"></span>
        </div>

        <div class="flex gap-2">
            <button type="button" @click="$dispatch('close-modal', 'fechar-conta')" class="rounded-xl px-4 py-3 text-sm font-medium text-slate-600 hover:bg-slate-100 dark:hover:bg-white/5">Cancelar</button>
            <button type="submit" class="flex-1 rounded-xl bg-emerald-600 py-3.5 text-base font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                Confirmar check-out
            </button>
        </div>
    </form>
</div>
