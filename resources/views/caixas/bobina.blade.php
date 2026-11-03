{{--
    Fechamento (ou parcial) de caixa na bobina 80mm. Imprime sozinho ao
    abrir (DOM ou ESC/POS, conforme Minha Empresa → impressão); ?ver=1 só
    mostra; ?resumido=1 tira a lista de movimentações.
--}}
@php
    $caixa = $r['caixa'];
    $m = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
    $dif = (float) ($r['diferenca'] ?? 0);
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $r['aberto'] ? 'Parcial' : 'Fechamento' }} do caixa #{{ $caixa->id }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #e2e8f0; color: #000; font-family: system-ui, -apple-system, Segoe UI, sans-serif; }
        .toolbar { position: sticky; top: 0; display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; padding: 10px; background: #0f172a; }
        .toolbar button, .toolbar a { border: 0; border-radius: 8px; padding: 9px 14px; font-size: 13px; font-weight: 700; cursor: pointer; background: #fff; color: #0f172a; text-decoration: none; }
        .toolbar .principal { background: #16a34a; color: #fff; }
        .toolbar .escpos { background: #0f766e; color: #fff; }
        #status { text-align: center; font-size: 12px; color: #334155; margin: 6px 0 0; min-height: 16px; }

        /* Texto preto e forte: impressora térmica apaga cinza e traço fino. */
        .bobina { width: 302px; margin: 16px auto 32px; padding: 10px 8px 14px; background: #fff; font-family: 'Courier New', Courier, monospace; font-size: 11.5px; line-height: 1.35; font-weight: 600; }
        .c { text-align: center; }
        .b { font-weight: 800; }
        .titulo { font-size: 14px; font-weight: 800; letter-spacing: .04em; }
        .sep { border-top: 1px dashed #000; margin: 6px 0; }
        .sep2 { border-top: 2px solid #000; margin: 6px 0; }
        .sec { text-align: center; font-weight: 800; margin: 8px 0 4px; }
        .linha { display: flex; justify-content: space-between; gap: 8px; }
        .linha span:last-child { white-space: nowrap; }
        .sub { padding-left: 10px; }
        .destaque { font-size: 13px; font-weight: 800; }
        .caixa-situacao { border: 2px solid #000; padding: 3px; text-align: center; font-weight: 800; margin-top: 4px; }
        .mov { display: flex; justify-content: space-between; gap: 6px; font-size: 10.5px; }
        .mov span:first-child { flex: 1; min-width: 0; }
        .mov span:last-child { white-space: nowrap; }
        .assinatura { margin-top: 28px; border-top: 1px solid #000; text-align: center; padding-top: 2px; }

        @media print {
            @page { size: 80mm auto; margin: 0; }
            body { background: #fff; }
            .no-print { display: none !important; }
            .bobina { margin: 0 auto; width: 72mm; padding: 1mm 0 4mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        @if (in_array($impressaoModo, ['dom', 'ambos'], true))
            <button type="button" class="principal" onclick="window.print()">Imprimir 80mm</button>
        @endif
        @if (in_array($impressaoModo, ['escpos', 'ambos'], true))
            <button type="button" class="escpos" id="btn-escpos">Imprimir ESC/POS</button>
        @endif
        @if ($comMovimentacoes)
            <a href="{{ route('caixas.bobina', [$caixa, 'resumido' => 1, 'ver' => 1]) }}">Só o resumo</a>
        @else
            <a href="{{ route('caixas.bobina', [$caixa, 'ver' => 1]) }}">Com movimentações</a>
        @endif
        <a href="{{ route('caixas.pdf', $caixa) }}" target="_blank">PDF A4</a>
        <a href="{{ route('caixas.show', $caixa) }}">Voltar ao caixa (Esc)</a>
    </div>
    <p id="status" class="no-print"></p>

    <div class="bobina">
        <div class="c b">{{ $caixa->empresa?->nome ?? $caixa->unidade?->nome }}</div>
        @if ($caixa->unidade?->cnpj ?: $caixa->empresa?->cnpj)
            <div class="c">CNPJ: {{ $caixa->unidade?->cnpj ?: $caixa->empresa?->cnpj }}</div>
        @endif
        <div class="sep2"></div>
        <div class="c titulo">{{ $r['aberto'] ? 'PARCIAL DE CAIXA' : 'FECHAMENTO DE CAIXA' }}</div>
        <div class="c b">Caixa Nº {{ str_pad((string) $caixa->id, 6, '0', STR_PAD_LEFT) }}</div>
        <div class="sep"></div>

        <div class="linha"><span>Unidade</span><span>{{ $caixa->unidade?->nome ?? '—' }}</span></div>
        <div class="linha"><span>Terminal</span><span>{{ $caixa->terminal?->nome ?? '—' }}</span></div>
        <div class="linha"><span>Abertura</span><span>{{ $caixa->data_abertura->format('d/m/Y H:i') }}</span></div>
        <div class="linha"><span>Aberto por</span><span>{{ $caixa->usuarioAbertura?->name ?? '—' }}</span></div>
        @unless ($r['aberto'])
            <div class="linha"><span>Fechamento</span><span>{{ $caixa->data_fechamento?->format('d/m/Y H:i') }}</span></div>
            <div class="linha"><span>Fechado por</span><span>{{ $caixa->usuarioFechamento?->name ?? '—' }}</span></div>
        @endunless

        <div class="sec">— RESUMO —</div>
        <div class="linha"><span>Valor de abertura</span><span>{{ $m($r['abertura']) }}</span></div>
        <div class="linha"><span>(+) Entradas</span><span>{{ $m($r['entradas']) }}</span></div>
        <div class="linha"><span>(−) Saídas</span><span>{{ $m($r['saidas']) }}</span></div>
        <div class="sep"></div>
        <div class="linha destaque"><span>{{ $r['aberto'] ? 'SALDO ATÉ AGORA' : 'SALDO DO SISTEMA' }}</span><span>{{ $m($r['saldo']) }}</span></div>
        @unless ($r['aberto'])
            <div class="linha"><span>Valor contado</span><span>{{ $m($r['informado']) }}</span></div>
            <div class="linha destaque"><span>DIFERENÇA</span><span>{{ $dif > 0.004 ? '+ ' : ($dif < -0.004 ? '− ' : '') }}{{ $m(abs($dif)) }}</span></div>
            <div class="caixa-situacao">{{ $dif > 0.004 ? 'SOBRA NO CAIXA' : ($dif < -0.004 ? 'FALTA NO CAIXA' : 'CAIXA CONFERIDO, SEM DIFERENÇA') }}</div>
        @endunless

        <div class="sec">— POR FORMA DE PAGAMENTO —</div>
        @forelse ($r['formas'] as $f)
            <div class="linha"><span>{{ $f['forma'] }}</span><span>{{ $m($f['entradas']) }}</span></div>
            @if ($f['saidas'] > 0)
                <div class="linha sub"><span>saídas</span><span>− {{ $m($f['saidas']) }}</span></div>
            @endif
        @empty
            <div class="c">Nenhuma movimentação.</div>
        @endforelse
        <div class="sep"></div>
        <div class="linha destaque"><span>DINHEIRO NA GAVETA</span><span>{{ $m($r['dinheiro_gaveta']) }}</span></div>
        <div style="font-size: 10px">(abertura + dinheiro recebido − saídas em dinheiro)</div>

        @if ($r['categorias'])
            <div class="sec">— POR CATEGORIA —</div>
            @foreach ($r['categorias'] as $c)
                <div class="linha"><span>{{ $c['tipo'] === 'saida' ? '(−) ' : '' }}{{ $c['categoria'] }} ({{ $c['quantidade'] }})</span><span>{{ $m($c['total']) }}</span></div>
            @endforeach
        @endif

        @if ($comMovimentacoes && $r['movimentacoes']->isNotEmpty())
            <div class="sec">— MOVIMENTAÇÕES ({{ $r['movimentacoes']->count() }}) —</div>
            @foreach ($r['movimentacoes'] as $mov)
                <div class="mov">
                    <span>{{ $mov->created_at->format('d/m H:i') }} {{ $mov->descricao ?: ucfirst(str_replace('_', ' ', $mov->categoria)) }}</span>
                    <span>{{ $mov->tipo === 'saida' ? '−' : '+' }}{{ $m($mov->valor) }}</span>
                </div>
            @endforeach
        @endif

        <div class="assinatura">Operador</div>
        <div class="assinatura">Conferente</div>
        <div class="sep"></div>
        <div class="c" style="font-size: 10px">Impresso em {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    @include('partials.escpos-imprimir-script')
    <script>
        const MODO = @json($impressaoModo);
        const imprimirCaixa = () => imprimirEscPos({
            url: @json(route('caixas.bobina.escpos', array_filter([$caixa, 'resumido' => $comMovimentacoes ? null : 1]))),
            agente: @json($escposAgenteUrl),
            colunas: @json((int) $escposColunas),
            arquivo: 'fechamento-caixa-{{ $caixa->id }}.bin',
            status: document.getElementById('status'),
            botao: document.getElementById('btn-escpos'),
        });

        document.getElementById('btn-escpos')?.addEventListener('click', imprimirCaixa);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') window.location.href = @json(route('caixas.show', $caixa));
        });

        // Imprime sozinho ao abrir; ?ver=1 só mostra.
        window.addEventListener('load', () => {
            if (new URLSearchParams(window.location.search).has('ver')) return;
            if (MODO === 'escpos') { imprimirCaixa(); return; }
            setTimeout(() => window.print(), 250);
        });
    </script>
</body>
</html>
