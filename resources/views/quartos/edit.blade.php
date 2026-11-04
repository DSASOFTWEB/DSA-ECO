@extends('layouts.app')

@section('titulo', 'Editar quarto')

@section('conteudo')
    <form method="POST" action="{{ route('quartos.update', $quarto) }}" class="max-w-3xl space-y-6 rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        @csrf
        @method('PUT')
        @include('quartos._form', ['quarto' => $quarto])
        <div class="flex justify-end gap-2">
            <a href="{{ route('quartos.index') }}" class="rounded-lg px-4 py-2 text-sm text-slate-600 hover:bg-slate-100">Cancelar</a>
            <button class="rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white shadow-theme-xs transition hover:bg-brand-600">Salvar alterações</button>
        </div>
    </form>

    {{-- Sincronização de calendário com Booking.com / Airbnb (iCal) --}}
    <section class="mt-6 max-w-3xl rounded-2xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900"
             x-data="{ copiado: false, copiar(t) { navigator.clipboard?.writeText(t).then(() => { this.copiado = true; setTimeout(() => this.copiado = false, 1500); }); } }">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-white/90">
                <x-lucide-calendar-sync class="h-4 w-4 text-brand-500" /> Booking.com / Airbnb (calendário iCal)
            </h2>
            <span class="flex items-center gap-2">
                @if ($quarto->ical_importacao_url)
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $quarto->ical_erro ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700' }}">
                        {{ $quarto->ical_erro ? 'Com erro' : 'Sincronizando' }}
                    </span>
                @endif
                <a href="{{ route('quartos.ical.passo-a-passo') }}" class="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">
                    <x-lucide-file-down class="h-3.5 w-3.5" /> Passo a passo (PDF)
                </a>
            </span>
        </div>
        <p class="mt-1 text-xs text-slate-500">
            Evita vender o mesmo quarto duas vezes: o Booking.com lê as datas ocupadas aqui, e o sistema lê as datas vendidas lá a cada 15 minutos.
            Faça uma vez por quarto, no extranet do Booking em <em>Tarifas e Disponibilidade → Sincronizar calendários</em>.
        </p>

        <div class="mt-4 space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">1. Link deste quarto <span class="font-normal text-slate-400">(cole no Booking em "Importar calendário")</span></label>
                <div class="mt-1 flex gap-2">
                    <input type="text" readonly value="{{ $quarto->linkIcal() }}" class="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-xs text-slate-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200" @focus="$event.target.select()">
                    <button type="button" @click="copiar(@js($quarto->linkIcal()))" class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold hover:bg-slate-50 dark:border-gray-700 dark:hover:bg-white/5" x-text="copiado ? 'Copiado!' : 'Copiar'"></button>
                </div>
                <form method="POST" action="{{ route('quartos.ical.novo-link', $quarto) }}" class="mt-1" onsubmit="return confirm('Gerar um novo link? O antigo para de funcionar e você precisa atualizar no Booking.com.')">
                    @csrf
                    <button class="text-[11px] text-slate-400 underline hover:text-slate-600">gerar novo link (se o atual vazou)</button>
                </form>
            </div>

            <form method="POST" action="{{ route('quartos.ical.salvar', $quarto) }}">
                @csrf
                @method('PUT')
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-200">2. Link do calendário do Booking.com <span class="font-normal text-slate-400">(copie em "Exportar calendário")</span></label>
                <div class="mt-1 flex gap-2">
                    <input type="url" name="ical_importacao_url" value="{{ old('ical_importacao_url', $quarto->ical_importacao_url) }}" placeholder="https://admin.booking.com/hotel/hoteladmin/ical.html?t=..." class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-gray-700 dark:bg-gray-800 dark:text-white">
                    <button class="shrink-0 rounded-lg bg-brand-500 px-3 py-2 text-xs font-semibold text-white hover:bg-brand-600">Salvar e sincronizar</button>
                </div>
                @error('ical_importacao_url')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                <p class="mt-1 text-xs text-slate-400">Deixe em branco e salve para desligar. Também funciona com o link iCal do Airbnb.</p>
            </form>

            @if ($quarto->ical_importacao_url)
                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg border px-3 py-2 text-xs {{ $quarto->ical_erro ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-slate-200 bg-slate-50 text-slate-600 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300' }}">
                    <span>
                        @if ($quarto->ical_erro)
                            <strong>Erro na última leitura:</strong> {{ $quarto->ical_erro }}
                        @else
                            Última leitura: {{ $quarto->ical_sincronizado_em?->format('d/m/Y H:i') ?? 'ainda não' }}
                        @endif
                    </span>
                    <form method="POST" action="{{ route('quartos.ical.sincronizar', $quarto) }}">
                        @csrf
                        <button class="font-semibold text-brand-600 hover:underline">Sincronizar agora</button>
                    </form>
                </div>
            @endif

            @if ($bloqueios->isNotEmpty())
                <div>
                    <p class="text-sm font-medium text-slate-700 dark:text-slate-200">Reservas vindas do canal</p>
                    <ul class="mt-2 divide-y divide-slate-100 rounded-lg border border-slate-200 text-sm dark:divide-gray-800 dark:border-gray-700">
                        @foreach ($bloqueios as $b)
                            <li class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 {{ $b->conflito && ! $b->hospedagem_id ? 'bg-rose-50 dark:bg-rose-500/10' : '' }}">
                                <span>
                                    <strong>{{ $b->inicio->format('d/m/Y') }} → {{ $b->fim->format('d/m/Y') }}</strong>
                                    <span class="text-slate-500">· {{ $b->origemLabel() }}{{ $b->resumo ? ' · '.$b->resumo : '' }}</span>
                                    @if ($b->conflito && ! $b->hospedagem_id)
                                        <span class="ml-1 rounded bg-rose-600 px-1.5 py-0.5 text-[10px] font-bold text-white">OVERBOOKING</span>
                                    @endif
                                </span>
                                @if ($b->hospedagem)
                                    <a href="{{ route('hospedagens.show', $b->hospedagem) }}" class="text-xs font-semibold text-emerald-700 hover:underline">Hóspede: {{ $b->hospedagem->cliente?->nome }}</a>
                                @else
                                    <a href="{{ route('hospedagens.create', ['bloqueio' => $b->id]) }}" class="rounded-lg border border-sky-300 px-2.5 py-1 text-xs font-semibold text-sky-700 hover:bg-sky-50 dark:text-sky-300">Registrar hóspede</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>
@endsection
