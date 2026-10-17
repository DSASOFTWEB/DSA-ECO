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
                ['barra' => 'bg-sky-500', 'ring' => 'ring-sky-500 border-sky-500', 'icone' => 'bg-sky-100 text-sky-700'],
                ['barra' => 'bg-amber-500', 'ring' => 'ring-amber-500 border-amber-500', 'icone' => 'bg-amber-100 text-amber-700'],
                ['barra' => 'bg-violet-500', 'ring' => 'ring-violet-500 border-violet-500', 'icone' => 'bg-violet-100 text-violet-700'],
                ['barra' => 'bg-rose-500', 'ring' => 'ring-rose-500 border-rose-500', 'icone' => 'bg-rose-100 text-rose-700'],
                ['barra' => 'bg-teal-500', 'ring' => 'ring-teal-500 border-teal-500', 'icone' => 'bg-teal-100 text-teal-700'],
            ];
            $tiposPlano = $tiposEntrada->where('eh_plano', true)->values();
            $tiposAvulsos = $tiposEntrada->where('eh_plano', false)->values();
            $tipoInicial = old('tipo_entrada_id', ($tiposAvulsos->first() ?? $tiposEntrada->first())->id);
        @endphp

        <div x-data="{
                tipoId: {{ (int) $tipoInicial }},
                valores: { @foreach ($tiposEntrada as $t) {{ $t->id }}: {{ (float) $t->valor }}, @endforeach },
                planos: { @foreach ($tiposEntrada as $t) {{ $t->id }}: {{ $t->eh_plano ? 'true' : 'false' }}, @endforeach },
                quantidade: {{ (int) old('quantidade', 1) }},
                get ehPlano() { return this.planos[this.tipoId] ?? false },
                get total() { return (this.valores[this.tipoId] ?? 0) * this.quantidade },
                inc() { if (this.quantidade < 20) this.quantidade++ },
                dec() { if (this.quantidade > 1) this.quantidade-- },
              }">
            <h1 class="text-xl font-extrabold sm:text-2xl tracking-tight text-slate-900">Vamos nessa? 🌊</h1>
            <p class="mt-1 hidden text-[15px] sm:block leading-snug text-slate-600">Escolha sua entrada abaixo.</p>

            {{-- Como funciona: plano x avulso --}}
            <div class="mt-3 grid gap-2 sm:mt-4 sm:grid-cols-2">
                @if ($tiposPlano->isNotEmpty())
                    <div class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-2.5 sm:p-3">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-emerald-600 text-white sm:h-9 sm:w-9">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        </span>
                        <span>
                            <span class="block text-sm font-bold text-emerald-900">Tem plano?</span>
                            <span class="block text-sm leading-snug text-emerald-800">Entrada <strong>grátis</strong>: escolha seu plano e informe CPF ou nome.</span>
                        </span>
                    </div>
                @endif
                @if ($tiposAvulsos->isNotEmpty())
                    <div class="flex items-start gap-3 rounded-2xl border border-sky-200 bg-sky-50 p-2.5 sm:p-3 {{ $tiposPlano->isEmpty() ? 'sm:col-span-2' : '' }}">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-sky-600 text-white sm:h-9 sm:w-9">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5h4.5v4.5h-4.5zM15.75 4.5h4.5v4.5h-4.5zM3.75 15h4.5v4.5h-4.5zM15 15h1.5v1.5H15zM18.75 18.75h1.5v1.5h-1.5zM12 4.5v3M12 12h3m4.5 0h.75M12 15.75V19.5"/></svg>
                        </span>
                        <span>
                            <span class="block text-sm font-bold text-sky-900">Não tem plano?</span>
                            <span class="block text-sm leading-snug text-sky-800">Escolha o ingresso e pague com <strong>Pix</strong>.</span>
                        </span>
                    </div>
                @endif
            </div>

            @foreach ([['titulo' => 'Tenho plano', 'tipos' => $tiposPlano, 'plano' => true], ['titulo' => 'Ingresso avulso', 'tipos' => $tiposAvulsos, 'plano' => false]] as $grupo)
                @continue($grupo['tipos']->isEmpty())
                <h2 class="mb-2 mt-4 flex items-center gap-2 sm:mt-5 text-xs font-bold uppercase tracking-wider text-slate-500">
                    {{ $grupo['titulo'] }}
                    <span class="h-px flex-1 bg-slate-200"></span>
                </h2>
                <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3">
                    @foreach ($grupo['tipos'] as $tipo)
                        @php $cor = $grupo['plano'] ? ['barra' => 'bg-emerald-500', 'ring' => 'ring-emerald-500 border-emerald-500', 'icone' => 'bg-emerald-100 text-emerald-700'] : $paleta[$loop->index % count($paleta)]; @endphp
                        <label class="relative flex min-h-[6rem] cursor-pointer flex-col overflow-hidden rounded-2xl border-2 bg-white text-left shadow-sm transition hover:shadow-md focus-within:ring-2"
                               :class="tipoId === {{ $tipo->id }} ? '{{ $cor['ring'] }} ring-2 ring-offset-1' : 'border-slate-200'">
                            <input type="radio" name="tipo_entrada_id" value="{{ $tipo->id }}" x-model.number="tipoId" class="sr-only">
                            <span class="h-1.5 w-full shrink-0 {{ $cor['barra'] }}"></span>
                            <span class="flex flex-1 flex-col gap-2 p-3">
                                <span class="pr-7 text-sm font-bold leading-snug text-slate-900">{{ $tipo->nome }}</span>
                                <span class="mt-auto">
                                    @if ($tipo->eh_plano)
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">Grátis · plano</span>
                                    @else
                                        <span class="text-lg font-extrabold text-slate-900">R$ {{ number_format($tipo->valor, 2, ',', '.') }}</span>
                                    @endif
                                </span>
                            </span>
                            <span x-show="tipoId === {{ $tipo->id }}" x-cloak class="absolute right-2 top-3 grid h-6 w-6 place-items-center rounded-full {{ $cor['barra'] }} text-white shadow">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7"/></svg>
                            </span>
                        </label>
                    @endforeach
                </div>
            @endforeach

            @unless ($pixDisponivel)
                <div x-show="!ehPlano" x-cloak class="mt-4 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-snug text-amber-900">
                    A compra de ingressos com Pix está indisponível no momento. Procure a recepção do parque.
                </div>
            @endunless

            {{-- Ingresso avulso pago via Pix (campos e botão ficam no rodapé fixo, ligados por form="form-pix") --}}
            @if ($pixDisponivel)
            <form method="POST" action="{{ route('checkout.store', $unidade) }}" id="form-pix">
                @csrf
                <input type="hidden" name="tipo_entrada_id" :value="tipoId">
            </form>
            @endif

            {{-- Cliente com plano: identifica e libera a entrada, sem cobrança --}}
            <form method="POST" action="{{ route('checkout.verificar-plano', $unidade) }}" id="form-plano" x-show="ehPlano" x-cloak class="mt-5">
                @csrf
                <input type="hidden" name="tipo_entrada_id" :value="tipoId">
                <div class="rounded-2xl border border-emerald-200 bg-white p-4 shadow-sm">
                    <label for="termo-plano" class="block text-[15px] font-bold text-slate-900">Identifique-se para liberar sua entrada</label>
                    <p class="mt-0.5 text-sm text-slate-600">Informe seu CPF, primeiro nome ou nome completo.</p>
                    <input id="termo-plano" type="text" name="termo" required minlength="3" value="{{ old('termo') }}" autocomplete="off"
                           placeholder="Ex.: 000.000.000-00 ou João da Silva"
                           class="mt-3 w-full rounded-xl border border-slate-300 px-4 py-3 text-base text-slate-900 outline-none placeholder:text-slate-400 focus:border-emerald-500 focus:ring-3 focus:ring-emerald-500/15">
                    <p class="mt-2 text-xs leading-snug text-slate-500">Usado só pra localizar seu cadastro. Se houver nomes iguais, digite o nome completo.</p>
                </div>
            </form>

            {{--
                Rodapé fixo do card: fica fora dos formulários de propósito — "sticky"
                só gruda dentro do elemento pai, e o pai precisa ser o conteúdo todo
                para o botão aparecer mesmo quando o formulário está fora da tela (celular).
            --}}
            <div x-show="ehPlano || {{ $pixDisponivel ? 'true' : 'false' }}" class="sticky -bottom-4 z-10 -mx-4 -mb-4 mt-5 sm:-bottom-6 space-y-2 border-t border-slate-200 bg-white px-4 pt-3 shadow-[0_-8px_16px_-8px_rgba(15,23,42,0.12)] sm:-mx-6 sm:-mb-6 sm:px-6"
                 style="padding-bottom: max(1rem, env(safe-area-inset-bottom))">
                @if ($pixDisponivel)
                    <div x-show="!ehPlano" class="space-y-2">
                        <div class="flex items-stretch gap-2">
                            <div class="flex shrink-0 flex-col justify-center rounded-2xl border border-slate-300 bg-white px-2.5 py-1.5 sm:px-3">
                                <span class="text-[11px] font-bold uppercase tracking-wide text-slate-500">Quantidade</span>
                                <div class="flex items-center gap-1.5 sm:gap-2">
                                    <button type="button" @click="dec()" :disabled="quantidade <= 1" aria-label="Diminuir" class="grid h-9 w-9 place-items-center rounded-full border border-slate-300 text-xl font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-40">−</button>
                                    <input type="number" form="form-pix" name="quantidade" x-model.number="quantidade" min="1" max="20" readonly aria-label="Quantidade" class="w-8 border-0 bg-transparent p-0 text-center text-xl font-extrabold text-slate-900 focus:outline-none focus:ring-0">
                                    <button type="button" @click="inc()" :disabled="quantidade >= 20" aria-label="Aumentar" class="grid h-9 w-9 place-items-center rounded-full border border-slate-300 text-xl font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-40">+</button>
                                </div>
                            </div>
                            <div class="flex min-w-0 flex-1 flex-col items-end justify-center rounded-2xl bg-slate-900 px-3 py-2 text-white sm:px-4">
                                <span class="text-[13px] font-medium text-slate-300">Total a pagar</span>
                                <span class="truncate text-xl font-extrabold leading-tight sm:text-2xl" x-text="'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                            </div>
                        </div>

                        <button type="submit" form="form-pix" class="flex w-full items-center justify-center gap-2 rounded-2xl bg-sky-600 py-4 text-base font-bold text-white shadow-md transition hover:bg-sky-700">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.5h4.5v4.5h-4.5zM15.75 4.5h4.5v4.5h-4.5zM3.75 15h4.5v4.5h-4.5zM15 15h1.5v1.5H15zM18.75 18.75h1.5v1.5h-1.5zM12 4.5v3M12 12h3m4.5 0h.75M12 15.75V19.5"/></svg>
                            Gerar QR Code Pix
                        </button>
                        <p class="hidden text-center text-xs text-slate-500 sm:block">Depois do pagamento você recebe o voucher de entrada na tela.</p>
                    </div>
                @endif

                <div x-show="ehPlano" x-cloak>
                    <button type="submit" form="form-plano" class="flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-600 py-4 text-base font-bold text-white shadow-md transition hover:bg-emerald-700">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                        Verificar minha entrada
                    </button>
                </div>
            </div>
        </div>
    @endif
@endsection
