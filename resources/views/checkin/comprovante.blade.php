@extends('layouts.app')

@section('titulo', 'Comprovante de entrada')

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
        <a href="{{ route('checkin.index') }}" class="mb-4 inline-block text-sm text-brand-600 hover:underline">&larr; Nova busca</a>

        <div class="mb-4 flex gap-2">
            <button type="button" onclick="window.print()" class="flex-1 rounded-lg bg-gray-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-gray-900 dark:bg-white dark:text-gray-900">Imprimir (bobina)</button>
            <a href="{{ route('checkin.voucher-pdf', ['cliente' => $cliente, 'acessos' => $acessos->pluck('id')->implode(',')]) }}" target="_blank" class="flex-1 rounded-lg border border-gray-300 px-4 py-2.5 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">Baixar PDF</a>
        </div>

        <div id="recibo" class="space-y-4 rounded-2xl border border-gray-200 bg-white p-5 font-mono text-sm text-gray-800 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-white/90">
            @foreach ($acessos as $acesso)
                <div class="border-b border-dashed border-gray-300 pb-4 text-center last:border-0 dark:border-gray-700">
                    <p class="text-xs uppercase tracking-widest text-emerald-600">Entrada liberada</p>
                    <p class="mt-1 text-base font-bold">{{ $acesso->nomeTitular() }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $acesso->dependente_id ? 'Dependente' : 'Titular' }} · {{ $acesso->unidade->nome }}</p>
                    <p class="text-xs text-gray-400">{{ $acesso->registrado_em->format('d/m/Y H:i') }}</p>

                    @if ($qrCodes[$acesso->id] ?? null)
                        <img src="{{ $qrCodes[$acesso->id] }}" alt="QR de validação" class="mx-auto mt-2 h-28 w-28">
                    @endif
                    @if ($barcodes[$acesso->id] ?? null)
                        <img src="{{ $barcodes[$acesso->id] }}" alt="Código de barras" class="mx-auto mt-1 h-8">
                    @endif
                    @if ($acesso->codigo_validacao)
                        <p class="mt-0.5 text-[9px] tracking-widest text-gray-400">{{ $acesso->codigo_validacao }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endsection
