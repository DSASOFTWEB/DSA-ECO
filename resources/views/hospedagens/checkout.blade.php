@extends('layouts.app')

@section('titulo', 'Check-out — Quarto '.$hospedagem->quarto->numero)

@section('conteudo')
    <div class="max-w-xl">
        @if (! $caixaAberto && $caixasDisponiveis->count() > 1)
            <div class="rounded-2xl border border-gray-200 bg-white p-6 text-center shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold text-slate-700">Você está no caixa/terminal de qual unidade?</h2>
                <p class="mt-1 text-sm text-slate-400">Há mais de um caixa aberto agora.</p>
                <form method="GET" action="{{ route('hospedagens.checkout', $hospedagem) }}" class="mt-4 flex flex-col gap-3">
                    <select name="caixa_id" required class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm dark:border-gray-700">
                        <option value="">Selecione...</option>
                        @foreach ($caixasDisponiveis as $caixa)
                            <option value="{{ $caixa->id }}">{{ $caixa->terminal->nome ?? $caixa->unidade->nome }} ({{ $caixa->unidade->nome }})</option>
                        @endforeach
                    </select>
                    <button class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600">Continuar</button>
                </form>
            </div>
        @elseif (! $caixaAberto)
            <div class="rounded-2xl border border-warning-200 bg-warning-50 p-5 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-300">
                Nenhum caixa aberto no momento. <a href="{{ route('caixas.index') }}" class="font-medium underline">Abra o caixa</a> antes de fazer o check-out.
            </div>
        @else
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                 x-data="{
                    valorDiaria: {{ (float) $hospedagem->valor_diaria }},
                    noites: {{ $noites }},
                    totalConsumos: {{ (float) $hospedagem->consumos->sum('subtotal') }},
                    desconto: 0,
                    get total() { return Math.max(0, (this.valorDiaria * this.noites) + this.totalConsumos - (Number(this.desconto) || 0)) },
                    formatar(valor) { return 'R$ ' + Number(valor || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) }
                 }">
                <h2 class="mb-1 text-lg font-semibold text-gray-800 dark:text-white/90">Fechar conta — Quarto {{ $hospedagem->quarto->numero }}</h2>
                <p class="mb-5 text-sm text-slate-500">{{ $hospedagem->cliente->nome }} · <span x-text="noites"></span> diária(s)</p>

                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Diárias (<span x-text="noites"></span> × R$ {{ number_format($hospedagem->valor_diaria, 2, ',', '.') }})</dt><dd x-text="formatar(valorDiaria * noites)"></dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Consumos</dt><dd x-text="formatar(totalConsumos)"></dd></div>
                </dl>

                <form method="POST" action="{{ route('hospedagens.checkout.store', $hospedagem) }}" class="mt-5 space-y-4">
                    @csrf
                    <input type="hidden" name="caixa_id" value="{{ $caixaAberto->id }}">

                    <div>
                        <label class="block text-sm font-medium text-slate-700">Desconto (R$)</label>
                        <input type="number" step="0.01" min="0" name="desconto" x-model.number="desconto" value="0" class="mt-1 w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700">Forma de pagamento</label>
                        <select name="forma_pagamento" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                            <option value="dinheiro">Dinheiro</option>
                            <option value="cartao_credito">Cartão de crédito</option>
                            <option value="cartao_debito">Cartão de débito</option>
                            <option value="pix">Pix</option>
                        </select>
                    </div>

                    <fieldset class="rounded-xl border border-slate-200 p-4 dark:border-gray-700">
                        <legend class="px-1 text-sm font-semibold text-slate-700 dark:text-slate-200">Documentos fiscais</legend>
                        <p class="mb-3 text-xs text-slate-500">Selecione as notas que devem ser emitidas logo após o fechamento da conta.</p>

                        <div class="space-y-3">
                            <label class="flex items-start gap-3 {{ $quantidadeItensNfce === 0 ? 'cursor-not-allowed opacity-50' : 'cursor-pointer' }}">
                                <input type="checkbox" name="emitir_nfce" value="1" @checked(old('emitir_nfce')) @disabled($quantidadeItensNfce === 0) class="mt-0.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                                <span>
                                    <span class="block text-sm font-medium text-slate-700 dark:text-slate-200">Emitir NFC-e (produtos)</span>
                                    <span class="block text-xs text-slate-500">{{ $quantidadeItensNfce }} item(ns) de produto</span>
                                </span>
                            </label>

                            <label class="flex items-start gap-3 {{ $quantidadeItensNfse === 0 ? 'cursor-not-allowed opacity-50' : 'cursor-pointer' }}">
                                <input type="checkbox" name="emitir_nfse" value="1" @checked(old('emitir_nfse')) @disabled($quantidadeItensNfse === 0) class="mt-0.5 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                                <span>
                                    <span class="block text-sm font-medium text-slate-700 dark:text-slate-200">Emitir NFS-e (hospedagem)</span>
                                    <span class="block text-xs text-slate-500">{{ $quantidadeItensNfse }} item(ns) de diária/serviço</span>
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    <div class="flex items-center justify-between rounded-2xl bg-gray-900 p-5 text-white">
                        <span class="text-sm text-gray-300">Total a cobrar</span>
                        <span class="text-2xl font-bold" x-text="formatar(total)"></span>
                    </div>

                    <button type="submit" class="w-full rounded-xl bg-emerald-600 py-3.5 text-base font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                        Confirmar check-out
                    </button>
                </form>
            </div>
        @endif
    </div>
@endsection
