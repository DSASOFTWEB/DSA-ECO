@extends('layouts.app')

@section('titulo', 'Link de Vendas Externa')

@section('conteudo')
    <p class="mb-6 max-w-2xl text-sm text-slate-500 dark:text-slate-400">
        Link de autoatendimento — o cliente escolhe a entrada avulsa ou o plano, paga via Pix e recebe o voucher, sem precisar ir até o balcão.
        Compartilhe por WhatsApp, redes sociais, ou imprima o QR na recepção/entrada.
    </p>

    <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($unidades as $unidade)
            @php $link = $links[$unidade->id]; @endphp
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900"
                 x-data="{ copiado: false }">
                <h3 class="font-semibold text-gray-800 dark:text-white/90">{{ $unidade->nome }}</h3>

                <div class="mt-4 flex justify-center">
                    <img src="{{ $link['qr'] }}" alt="QR do link de venda de {{ $unidade->nome }}" class="h-40 w-40 rounded-lg border border-gray-100 dark:border-gray-800">
                </div>

                <div class="mt-4 flex items-center gap-1.5 rounded-lg border border-slate-300 px-2.5 py-2 dark:border-gray-700">
                    <input type="text" readonly value="{{ $link['url'] }}" onclick="this.select()" class="w-full truncate border-0 bg-transparent p-0 text-xs text-slate-600 outline-none dark:text-gray-300">
                </div>

                <div class="mt-3 flex gap-2">
                    <button type="button"
                            @click="navigator.clipboard.writeText('{{ $link['url'] }}'); copiado = true; setTimeout(() => copiado = false, 2000)"
                            class="flex-1 rounded-lg bg-brand-500 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-600">
                        <span x-show="!copiado">Copiar link</span>
                        <span x-show="copiado" x-cloak>Copiado!</span>
                    </button>
                    <a href="{{ $link['url'] }}" target="_blank" class="flex-1 rounded-lg border border-gray-300 px-3 py-2 text-center text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">
                        Abrir
                    </a>
                </div>
            </div>
        @empty
            <p class="text-slate-400">Nenhuma unidade ativa cadastrada.</p>
        @endforelse
    </div>
@endsection
