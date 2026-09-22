@extends('layouts.app')

@section('titulo', $mensalidade->ehCaucao() ? 'Caução de entrada' : 'Mensalidade '.$mensalidade->competencia->format('m/Y'))

@section('conteudo')
    @if (session('pix'))
        <div class="mb-6 rounded-2xl border border-brand-200 bg-brand-50 p-5 text-sm text-brand-800 dark:border-brand-500/30 dark:bg-brand-500/10 dark:text-brand-300">
            <p class="font-medium">Pix gerado! Copia e cola:</p>
            <textarea readonly class="mt-2 w-full rounded-lg border border-sky-200 bg-white p-2 text-xs" rows="3">{{ session('pix')['qr_code'] ?? '' }}</textarea>
        </div>
    @endif

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:col-span-2">
            <h3 class="mb-3 text-sm font-semibold text-slate-700">{{ $mensalidade->contrato->cliente->nome }} — {{ $mensalidade->ehCaucao() ? 'Caução / entrada' : $mensalidade->competencia->format('m/Y') }}</h3>
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-slate-400">Valor original</dt><dd>R$ {{ number_format($mensalidade->valor_original, 2, ',', '.') }}</dd></div>
                <div><dt class="text-slate-400">Desconto</dt><dd>R$ {{ number_format($mensalidade->desconto, 2, ',', '.') }}</dd></div>
                <div><dt class="text-slate-400">Valor total</dt><dd class="font-semibold">R$ {{ number_format($mensalidade->valor_total, 2, ',', '.') }}</dd></div>
                <div><dt class="text-slate-400">Vencimento</dt><dd>{{ $mensalidade->data_vencimento->format('d/m/Y') }}</dd></div>
                <div><dt class="text-slate-400">Status</dt><dd><x-status-badge :status="$mensalidade->status" /></dd></div>
                <div><dt class="text-slate-400">Pago em</dt><dd>{{ $mensalidade->data_pagamento?->format('d/m/Y') ?? '—' }}</dd></div>
            </dl>

            @if ($mensalidade->status !== 'pago')
                <div class="mt-6 border-t border-gray-100 pt-5 dark:border-gray-800">
                    <div class="flex flex-wrap items-end gap-3">
                        <form method="POST" action="{{ route('mensalidades.gerar-pix', $mensalidade) }}">
                            @csrf
                            <button class="h-11 rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">Gerar cobrança Pix</button>
                        </form>

                        @can('baixarManual', $mensalidade)
                            @if ($caixaAberto)
                                <form method="POST" action="{{ route('mensalidades.baixar', $mensalidade) }}" class="flex flex-wrap items-end gap-3">
                                    @csrf
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-500">Baixa manual — forma de pagamento</label>
                                        <select name="metodo_pagamento" required class="h-11 rounded-lg border border-slate-300 px-3 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                                            <option value="dinheiro">Dinheiro</option>
                                            <option value="cartao_credito">Cartão de crédito</option>
                                            <option value="cartao_debito">Cartão de débito</option>
                                            <option value="pix_manual">Pix (confirmado manualmente)</option>
                                        </select>
                                    </div>
                                    <button class="h-11 rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700">Registrar pagamento</button>
                                </form>
                            @else
                                <p class="text-sm text-amber-600 dark:text-amber-400">
                                    Abra o <a href="{{ route('caixas.index') }}" class="underline">caixa da sua unidade</a> para poder registrar a baixa manual desta mensalidade.
                                </p>
                            @endif
                        @endcan
                    </div>
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h3 class="mb-3 text-sm font-semibold text-slate-700">Histórico de cobranças</h3>
            <ul class="space-y-2 text-sm">
                @forelse ($mensalidade->cobrancas as $cobranca)
                    <li class="flex items-center justify-between border-b border-slate-50 pb-2">
                        <span>{{ $cobranca->canal }} · {{ $cobranca->created_at->format('d/m H:i') }}</span>
                        <x-status-badge :status="$cobranca->status" />
                    </li>
                @empty
                    <li class="text-slate-400">Nenhuma cobrança disparada ainda.</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
