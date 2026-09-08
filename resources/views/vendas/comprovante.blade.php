@extends('layouts.app')

@section('titulo', 'Comprovante — Venda #'.$venda->id)

@push('styles')
    <style>
        @media print {
            body * { visibility: hidden; }
            #recibo, #recibo * { visibility: visible; }
            #recibo { position: absolute; top: 0; left: 0; width: 76mm; padding: 4mm; }
            @page { size: 80mm auto; margin: 0; }
        }
    </style>
@endpush

@section('conteudo')
    <div class="mx-auto max-w-sm">
        <a href="{{ route('vendas.create') }}" class="mb-4 inline-block text-sm text-brand-600 hover:underline">&larr; Nova venda</a>

        <div class="mb-4 flex gap-2">
            <button type="button" onclick="window.print()" class="flex-1 rounded-lg bg-gray-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-900 dark:bg-white dark:text-gray-900">Imprimir (bobina)</button>
            @if ($venda->status === 'pago' && $venda->ehEntradaAvulsa())
                <a href="{{ route('vendas.voucher', $venda) }}" target="_blank" class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Baixar PDF</a>
            @endif
        </div>

        <div id="recibo" class="space-y-4 rounded-2xl border border-gray-200 bg-white p-5 font-mono text-sm text-gray-800 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white/90">
            <div class="text-center">
                <p class="text-sm font-bold uppercase tracking-wide">{{ $venda->unidade->nome }}</p>
                <p class="text-xs text-gray-500">Venda #{{ $venda->id }} · {{ $venda->created_at->format('d/m/Y H:i') }}</p>
                @if ($venda->cliente)
                    <p class="text-xs text-gray-500">Cliente: {{ $venda->cliente->nome }}</p>
                @endif
            </div>

            <div class="border-t border-dashed border-gray-300 pt-3 dark:border-gray-700">
                @foreach ($venda->itens as $item)
                    <div class="flex justify-between gap-2 py-1">
                        <span>{{ $item->quantidade }}x {{ $item->nomeItem() }}</span>
                        <span>R$ {{ number_format($item->subtotal, 2, ',', '.') }}</span>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-dashed border-gray-300 pt-3 dark:border-gray-700">
                @if ($venda->desconto > 0)
                    <div class="flex justify-between text-xs text-gray-500"><span>Desconto</span><span>-R$ {{ number_format($venda->desconto, 2, ',', '.') }}</span></div>
                @endif
                <div class="flex justify-between text-base font-bold"><span>Total</span><span>R$ {{ number_format($venda->valor_total, 2, ',', '.') }}</span></div>
                <div class="mt-1 flex justify-between text-xs text-gray-500"><span>Pagamento</span><span>{{ ucfirst(str_replace('_', ' ', $venda->forma_pagamento)) }}</span></div>
            </div>

            @if ($acessos->isNotEmpty())
                <div class="space-y-2 border-t border-dashed border-gray-300 pt-3 text-center dark:border-gray-700">
                    <p class="text-[11px] uppercase tracking-wide text-amber-600">Escaneie na portaria para validar a entrada</p>
                    @foreach ($acessos as $acesso)
                        <div class="border-t border-dashed border-gray-200 pt-2 first:border-0 first:pt-0 dark:border-gray-800">
                            <p class="text-[10px] text-gray-500">{{ $acesso->vendaItem?->tipoEntrada?->nome }}</p>
                            @if ($qrCodes[$acesso->id] ?? null)
                                <img src="{{ $qrCodes[$acesso->id] }}" alt="QR de validação" class="mx-auto h-28 w-28">
                            @endif
                            @if ($barcodes[$acesso->id] ?? null)
                                <img src="{{ $barcodes[$acesso->id] }}" alt="Código de barras" class="mx-auto mt-1 h-8">
                            @endif
                            <p class="mt-0.5 text-[9px] tracking-widest text-gray-400">{{ $acesso->codigo_validacao }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

            <p class="text-center text-[10px] uppercase tracking-widest text-gray-400">Obrigado pela preferência</p>
        </div>

        <p class="mt-3 text-center text-xs text-gray-400"><a href="{{ route('vendas.show', $venda) }}" class="underline">Ver detalhes completos da venda</a></p>
    </div>
@endsection
