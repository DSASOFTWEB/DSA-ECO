@extends('layouts.publico')

@section('titulo', 'Seu pedido — '.$venda->unidade->nome)

@section('conteudo')
    @php $item = $venda->itens->first(); @endphp

    @if ($venda->status === 'pago')
        <div class="rounded-2xl border-2 border-emerald-300 bg-emerald-50 p-6 text-center shadow-sm">
            <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-emerald-500 text-white">
                <svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 13 4 4L19 7"/></svg>
            </span>
            <h1 class="mt-4 text-xl font-bold text-emerald-800">Pagamento confirmado!</h1>
            <p class="mt-2 text-sm text-emerald-700">
                {{ $item?->quantidade }}x <strong>{{ $item?->tipoEntrada?->nome }}</strong> em {{ $venda->unidade->nome }}.
                Baixe o voucher e apresente o QR na portaria para validar sua entrada.
            </p>
            <p class="mt-4 text-xs text-emerald-600">Pedido #{{ $venda->id }} · R$ {{ number_format($venda->valor_total, 2, ',', '.') }}</p>
            <a href="{{ $voucherUrl }}" target="_blank" class="mt-5 inline-flex items-center rounded-lg bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Baixar voucher (PDF)</a>
        </div>
    @elseif ($venda->status === 'cancelado')
        <div class="rounded-2xl border-2 border-gray-300 bg-gray-50 p-6 text-center shadow-sm">
            <h1 class="text-xl font-bold text-gray-700">Pedido cancelado</h1>
            <p class="mt-2 text-sm text-gray-500">Este pedido não está mais válido. <a href="{{ route('checkout.index', $venda->unidade) }}" class="text-sky-600 underline">Comprar novamente</a>.</p>
        </div>
    @else
        <div class="rounded-2xl border border-gray-200 bg-white p-6 text-center shadow-sm" x-data="{
            status: 'pendente',
            init() {
                setInterval(() => {
                    fetch('{{ $statusUrl }}')
                        .then(r => r.json())
                        .then(d => { if (d.status !== 'pendente') location.reload(); });
                }, 4000);
            }
        }">
            <h1 class="text-lg font-bold text-gray-800">Escaneie para pagar com Pix</h1>
            <p class="mt-1 text-sm text-gray-500">{{ $item?->quantidade }}x {{ $item?->tipoEntrada?->nome }} · R$ {{ number_format($venda->valor_total, 2, ',', '.') }}</p>

            @if ($pix && ! empty($pix['qr_code_base64']))
                <img src="data:image/png;base64,{{ $pix['qr_code_base64'] }}" alt="QR Code Pix" class="mx-auto mt-5 h-56 w-56 rounded-xl border border-gray-200 p-2">
            @endif

            @if ($pix && ! empty($pix['qr_code']))
                <div class="mt-5">
                    <label class="mb-1 block text-xs font-medium text-gray-500">Ou copie o código Pix (copia e cola)</label>
                    <div class="flex gap-2">
                        <textarea id="pix-copia-cola" readonly rows="2" class="flex-1 rounded-lg border border-gray-300 bg-gray-50 p-2 text-xs text-gray-600">{{ $pix['qr_code'] }}</textarea>
                        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('pix-copia-cola').value)" class="shrink-0 rounded-lg border border-gray-300 px-3 text-xs font-medium text-gray-600 hover:bg-gray-50">Copiar</button>
                    </div>
                </div>
            @endif

            @if (! $pix)
                <p class="mt-5 text-sm text-amber-600">Não foi possível carregar o QR Code agora. Atualize a página em instantes.</p>
            @endif

            <p class="mt-5 flex items-center justify-center gap-2 text-xs text-gray-400">
                <svg class="h-3.5 w-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8v4a4 4 0 0 0-4 4H4Z"/></svg>
                Aguardando confirmação do pagamento...
            </p>
        </div>
    @endif
@endsection
