@extends('layouts.app')

@section('titulo', 'NFC-e emitidas')

@php
    $moeda = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $origens = ['venda_pdv' => 'PDV', 'venda_mesa' => 'Mesa / comanda', 'hospedagem_consumos' => 'Hospedagem'];
    $situacoes = [
        'autorizado' => ['Autorizada', 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300'],
        'cancelado' => ['Cancelada', 'bg-slate-200 text-slate-700 dark:bg-white/10 dark:text-slate-300'],
        'rejeitado' => ['Rejeitada', 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300'],
        'erro' => ['Erro', 'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300'],
        'processando' => ['Processando', 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300'],
        'rascunho' => ['Rascunho', 'bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-400'],
    ];
    $atalhos = [
        'Hoje' => [now(), now()],
        'Este mês' => [now()->startOfMonth(), now()],
        'Mês passado' => [now()->subMonthNoOverflow()->startOfMonth(), now()->subMonthNoOverflow()->endOfMonth()],
    ];
    $campo = 'h-10 rounded-lg border border-gray-300 bg-transparent px-3 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-white/90';
@endphp

@section('conteudo')
    <a href="{{ route('relatorios.index') }}" class="text-xs font-medium text-brand-600 hover:underline">← Relatórios</a>

    {{-- Filtro --}}
    <form method="GET" class="mb-5 mt-2 flex flex-wrap items-end gap-3 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
        <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">De</label>
            <input type="date" name="inicio" value="{{ $inicio->toDateString() }}" class="mt-1 {{ $campo }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Até</label>
            <input type="date" name="fim" value="{{ $fim->toDateString() }}" class="mt-1 {{ $campo }}">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Situação</label>
            <select name="status" class="mt-1 {{ $campo }}">
                <option value="">Todas</option>
                @foreach (['autorizado', 'cancelado', 'rejeitado', 'erro'] as $s)
                    <option value="{{ $s }}" @selected(($filtros['status'] ?? null) === $s)>{{ $situacoes[$s][0] }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Série</label>
            <select name="serie" class="mt-1 {{ $campo }}">
                <option value="">Todas</option>
                @foreach ($series as $serie)
                    <option value="{{ $serie }}" @selected((int) ($filtros['serie'] ?? 0) === (int) $serie)>Série {{ $serie }}</option>
                @endforeach
            </select>
        </div>
        <button class="h-10 rounded-lg bg-brand-500 px-5 text-sm font-medium text-white shadow-theme-xs hover:bg-brand-600">Filtrar</button>
        <div class="flex flex-wrap gap-1.5 sm:ml-auto">
            @foreach ($atalhos as $rotulo => [$de, $ate])
                <a href="{{ route('relatorios.nfce', ['inicio' => $de->toDateString(), 'fim' => $ate->toDateString()]) }}" class="rounded-lg border border-gray-200 px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-white/5">{{ $rotulo }}</a>
            @endforeach
        </div>
    </form>

    {{-- Totais do período --}}
    <div class="mb-5 grid grid-cols-2 gap-3 xl:grid-cols-4">
        @foreach ([['NFC-e autorizadas', $resumo['quantidade']], ['Valor total', $moeda($resumo['valor_total'])], ['Ticket médio', $moeda($resumo['ticket_medio'])], ['Canceladas / não autorizadas', $resumo['canceladas'].' / '.$resumo['nao_autorizadas']]] as [$rotulo, $valor])
            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
                <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $rotulo }}</p>
                <p class="mt-1 text-xl font-bold text-gray-900 dark:text-white">{{ $valor }}</p>
            </div>
        @endforeach
    </div>

    {{-- Notas --}}
    <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
        <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
            <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/[0.03] dark:text-gray-400">
                <tr>
                    <th class="px-4 py-3">Nº / Série</th>
                    <th class="px-4 py-3">Data</th>
                    <th class="px-4 py-3">Origem</th>
                    <th class="px-4 py-3">Consumidor</th>
                    <th class="px-4 py-3 text-right">Valor</th>
                    <th class="px-4 py-3">Situação</th>
                    <th class="px-4 py-3 text-right">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($notas as $nota)
                    @php [$rotuloSit, $corSit] = $situacoes[$nota->status] ?? [ucfirst($nota->status), $situacoes['rascunho'][1]]; @endphp
                    <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.02]">
                        <td class="whitespace-nowrap px-4 py-2.5 font-semibold text-gray-800 dark:text-white/90">{{ $nota->numero ?? '—' }} <span class="font-normal text-gray-400">/ {{ $nota->serie ?? '—' }}</span></td>
                        <td class="whitespace-nowrap px-4 py-2.5 text-gray-600 dark:text-gray-300">{{ ($nota->autorizado_em ?? $nota->created_at)->format('d/m/Y H:i') }}</td>
                        <td class="whitespace-nowrap px-4 py-2.5 text-gray-600 dark:text-gray-300">
                            {{ $origens[$nota->origem] ?? ucfirst((string) $nota->origem) }}
                            @if ($nota->hospedagem?->quarto) <span class="text-xs text-gray-400">· Quarto {{ $nota->hospedagem->quarto->numero }}</span> @endif
                        </td>
                        <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300">{{ $nota->cliente?->nome ?? 'Consumidor final' }}</td>
                        <td class="whitespace-nowrap px-4 py-2.5 text-right font-medium tabular-nums text-gray-800 dark:text-white/90">{{ $moeda($nota->valor_total) }}</td>
                        <td class="px-4 py-2.5">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $corSit }}" @if ($nota->mensagem_erro) title="{{ $nota->mensagem_erro }}" @endif>{{ $rotuloSit }}</span>
                        </td>
                        <td class="whitespace-nowrap px-4 py-2.5 text-right text-xs font-semibold">
                            @if ($nota->status === 'autorizado')
                                <a href="{{ route('fiscal.danfce', ['documento' => $nota, 'ver' => 1]) }}" target="_blank" class="text-sky-700 hover:underline dark:text-sky-400">Ver DANFE</a>
                                <a href="{{ route('fiscal.danfce', $nota) }}" target="_blank" class="ml-3 text-emerald-700 hover:underline dark:text-emerald-400">Imprimir</a>
                            @endif
                            @if ($nota->temXmlArquivo())
                                <a href="{{ route('fiscal.xml', $nota) }}" class="ml-3 text-gray-600 hover:underline dark:text-gray-300">XML</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">Nenhuma NFC-e no período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $notas->links() }}</div>

    {{-- Fechamento do mês --}}
    <section class="mt-8 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-white/[0.03]">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold text-gray-800 dark:text-white/90">Fechar o mês</h2>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Gera um ZIP <strong>"{{ $empresa?->nome }} - NFC-e MM-AAAA"</strong> com os XMLs, os DANFEs em PDF e o resumo (PDF e planilha) para o contador.</p>
            </div>
            <form method="POST" action="{{ route('relatorios.nfce.fechar') }}" class="flex items-end gap-2">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400">Mês</label>
                    <input type="month" name="mes" required value="{{ now()->subMonthNoOverflow()->format('Y-m') }}" class="mt-1 {{ $campo }}">
                </div>
                <button class="h-10 rounded-lg bg-slate-800 px-5 text-sm font-semibold text-white hover:bg-slate-900 dark:bg-white/10 dark:hover:bg-white/20">📦 Fechar mês</button>
            </form>
        </div>

        @if ($fechamentos->isNotEmpty())
            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <tr><th class="py-2 pr-3">Mês</th><th class="py-2 pr-3">Situação</th><th class="py-2 pr-3 text-right">Notas</th><th class="py-2 pr-3 text-right">Valor</th><th class="py-2 pr-3">Gerado em</th><th class="py-2"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($fechamentos as $f)
                            <tr x-data="{ envio: null }">
                                <td class="py-2.5 pr-3 font-semibold text-gray-800 dark:text-white/90">{{ $f->rotuloCompetencia() }}</td>
                                <td class="py-2.5 pr-3">
                                    @if ($f->estaPronto())
                                        <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">Pronto</span>
                                    @elseif ($f->status === 'processando')
                                        <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">Gerando…</span>
                                    @else
                                        <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800 dark:bg-rose-500/15 dark:text-rose-300" title="{{ $f->erro }}">Erro</span>
                                    @endif
                                    @if ($f->estaPronto() && $f->erro)
                                        <span class="ml-1 text-xs text-amber-700" title="{{ $f->erro }}">⚠ com avisos</span>
                                    @endif
                                </td>
                                <td class="py-2.5 pr-3 text-right tabular-nums">{{ $f->quantidade }}</td>
                                <td class="py-2.5 pr-3 text-right tabular-nums">{{ $moeda($f->valor_total) }}</td>
                                <td class="py-2.5 pr-3 text-gray-500 dark:text-gray-400">{{ $f->gerado_em?->format('d/m/Y H:i') ?? '—' }}</td>
                                <td class="py-2.5 text-right text-xs font-semibold">
                                    @if ($f->estaPronto())
                                        <a href="{{ route('fiscal.fechamentos.download', $f) }}" class="text-sky-700 hover:underline dark:text-sky-400">⬇ Baixar ZIP</a>
                                        <button type="button" @click="envio = envio === 'email' ? null : 'email'" class="ml-3 text-gray-700 hover:underline dark:text-gray-300">✉ E-mail</button>
                                        <button type="button" @click="envio = envio === 'whatsapp' ? null : 'whatsapp'" class="ml-3 text-emerald-700 hover:underline dark:text-emerald-400">WhatsApp</button>
                                    @endif

                                    <form x-show="envio === 'email'" x-cloak method="POST" action="{{ route('fiscal.fechamentos.email', $f) }}" class="mt-2 flex flex-wrap justify-end gap-2">
                                        @csrf
                                        <input type="email" name="email" required placeholder="e-mail do contador" class="{{ $campo }} w-56">
                                        <input type="text" name="mensagem" maxlength="1000" placeholder="mensagem (opcional)" class="{{ $campo }} w-56">
                                        <button class="h-10 rounded-lg bg-brand-500 px-4 text-sm font-semibold text-white hover:bg-brand-600">Enviar</button>
                                    </form>
                                    <form x-show="envio === 'whatsapp'" x-cloak method="POST" action="{{ route('fiscal.fechamentos.whatsapp', $f) }}" @if (! $whatsappIntegrado) target="_blank" @endif class="mt-2 flex flex-wrap justify-end gap-2">
                                        @csrf
                                        <input type="tel" name="telefone" required placeholder="(83) 99999-9999" class="{{ $campo }} w-48">
                                        <button class="h-10 rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700">Enviar</button>
                                        <p class="w-full text-right text-[11px] font-normal text-gray-400">
                                            {{ $whatsappIntegrado ? 'O ZIP vai como arquivo pelo WhatsApp da empresa.' : 'Abre o WhatsApp com a mensagem e um link de download válido por 7 dias.' }}
                                        </p>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
