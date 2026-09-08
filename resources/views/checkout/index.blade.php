@extends('layouts.publico')

@section('titulo', 'Comprar entrada — '.$unidade->nome)

@section('conteudo')
    @if ($tiposEntrada->isEmpty())
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
            Nenhuma entrada disponível para venda online no momento.
        </div>
    @else
        @php
            $paleta = [
                ['card' => 'border-sky-300 bg-sky-100', 'accent' => 'bg-sky-500', 'ring' => 'ring-sky-500'],
                ['card' => 'border-emerald-300 bg-emerald-100', 'accent' => 'bg-emerald-500', 'ring' => 'ring-emerald-500'],
                ['card' => 'border-amber-300 bg-amber-100', 'accent' => 'bg-amber-500', 'ring' => 'ring-amber-500'],
                ['card' => 'border-violet-300 bg-violet-100', 'accent' => 'bg-violet-500', 'ring' => 'ring-violet-500'],
                ['card' => 'border-rose-300 bg-rose-100', 'accent' => 'bg-rose-500', 'ring' => 'ring-rose-500'],
            ];
        @endphp

        <div x-data="{
                tipoId: {{ old('tipo_entrada_id', $tiposEntrada->first()->id) }},
                valores: { @foreach ($tiposEntrada as $t) {{ $t->id }}: {{ $t->valor }}, @endforeach },
                planos: { @foreach ($tiposEntrada as $t) {{ $t->id }}: {{ $t->eh_plano ? 'true' : 'false' }}, @endforeach },
                quantidade: {{ (int) old('quantidade', 1) }},
                get ehPlano() { return this.planos[this.tipoId] ?? false },
                get total() { return (this.valores[this.tipoId] ?? 0) * this.quantidade },
                inc() { if (this.quantidade < 20) this.quantidade++ },
                dec() { if (this.quantidade > 1) this.quantidade-- },
              }">
            <h1 class="mb-0.5 text-lg font-bold tracking-tight text-gray-900">Vamos nessa? 🌊</h1>
            <p class="mb-2.5 text-xs leading-snug text-gray-500">Tem plano? Selecione-o (grátis). Senão, escolha o ingresso e pague no Pix.</p>

            <div class="grid grid-cols-3 gap-2">
                @foreach ($tiposEntrada as $tipo)
                    @php $cor = $paleta[$loop->index % count($paleta)]; @endphp
                    <label class="flex h-20 cursor-pointer flex-col items-center justify-center gap-0.5 rounded-xl border-2 p-1.5 text-center shadow-sm transition {{ $cor['card'] }}" :class="tipoId === {{ $tipo->id }} ? '{{ $cor['ring'] }} ring-2 ring-offset-1' : 'opacity-70'">
                        <input type="radio" name="tipo_entrada_id" value="{{ $tipo->id }}" x-model.number="tipoId" class="sr-only">
                        <span class="h-2 w-2 shrink-0 rounded-full {{ $cor['accent'] }}"></span>
                        <span class="text-[11px] font-semibold leading-tight text-gray-800">{{ $tipo->nome }}</span>
                        <span class="text-[10px] leading-tight text-gray-500">
                            @if ($tipo->eh_plano)
                                Grátis
                            @else
                                R$ {{ number_format($tipo->valor, 2, ',', '.') }}
                            @endif
                        </span>
                    </label>
                @endforeach
            </div>

            {{-- Ingresso avulso pago via Pix --}}
            <form method="POST" action="{{ route('checkout.store', $unidade) }}" x-show="!ehPlano" x-cloak>
                @csrf
                <input type="hidden" name="tipo_entrada_id" :value="tipoId">

                <div class="mt-2.5 flex items-center justify-between rounded-xl border border-gray-200 bg-white p-2.5 shadow-sm">
                    <span class="text-xs font-medium text-gray-700">Quantidade</span>
                    <div class="flex items-center gap-2.5">
                        <button type="button" @click="dec()" class="grid h-7 w-7 place-items-center rounded-full border border-gray-300 text-base font-semibold text-gray-600 hover:bg-gray-50">−</button>
                        <input type="number" name="quantidade" x-model.number="quantidade" min="1" max="20" class="w-6 border-0 text-center text-base font-semibold text-gray-800 focus:outline-none focus:ring-0" readonly>
                        <button type="button" @click="inc()" class="grid h-7 w-7 place-items-center rounded-full border border-gray-300 text-base font-semibold text-gray-600 hover:bg-gray-50">+</button>
                    </div>
                </div>

                <div class="mt-2 flex items-center justify-between rounded-xl bg-gray-900 p-3 text-white">
                    <span class="text-xs text-gray-300">Total a pagar</span>
                    <span class="text-lg font-bold" x-text="'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2})"></span>
                </div>

                <button type="submit" class="mt-2 w-full rounded-xl bg-sky-600 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-sky-700">
                    Gerar QR Code Pix
                </button>
            </form>

            {{-- Cliente com plano: identifica e libera a entrada, sem cobrança --}}
            <form method="POST" action="{{ route('checkout.verificar-plano', $unidade) }}" x-show="ehPlano" x-cloak>
                @csrf
                <div class="mt-2.5 rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
                    <label class="mb-1 block text-xs font-medium text-gray-700">CPF, primeiro nome ou nome completo</label>
                    <input type="text" name="termo" required minlength="3" value="{{ old('termo') }}"
                           placeholder="Ex.: 000.000.000-00, João ou João da Silva"
                           class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm outline-none focus:border-brand-500 focus:ring-3 focus:ring-brand-500/10">
                    <p class="mt-1 text-[11px] leading-snug text-gray-400">Usado só pra localizar seu cadastro. Se houver homônimo, digite o nome completo.</p>
                </div>

                <button type="submit" class="mt-2 w-full rounded-xl bg-emerald-600 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700">
                    Verificar entrada
                </button>
            </form>
        </div>
    @endif
@endsection
