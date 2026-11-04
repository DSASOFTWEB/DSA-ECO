{{--
    Comprovante 80mm de sangria/suprimento, com assinaturas. Imprime sozinho
    ao abrir (navegador ou ESC/POS, conforme a configuração); ?ver=1 só mostra.
--}}
@php
    $sangria = $mov->categoria === 'sangria';
    $m = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $sangria ? 'Sangria' : 'Suprimento' }} nº {{ $mov->id }}</title>
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
        .titulo { font-size: 15px; font-weight: 800; letter-spacing: .04em; }
        .sep { border-top: 1px dashed #000; margin: 6px 0; }
        .sep2 { border-top: 2px solid #000; margin: 6px 0; }
        .linha { display: flex; justify-content: space-between; gap: 8px; }
        .linha span:last-child { white-space: nowrap; }
        .valor { font-size: 15px; font-weight: 800; border: 2px solid #000; padding: 4px 6px; margin: 4px 0; }
        .assinatura { margin-top: 38px; border-top: 1px solid #000; text-align: center; padding-top: 2px; }

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
        <button type="button" onclick="window.close()">Fechar (Esc)</button>
    </div>
    <p id="status" class="no-print"></p>

    <div class="bobina">
        <div class="c b">{{ $caixa->empresa?->nome ?? $caixa->unidade?->nome }}</div>
        <div class="sep2"></div>
        <div class="c titulo">{{ $sangria ? 'SANGRIA DE CAIXA' : 'SUPRIMENTO DE CAIXA' }}</div>
        <div class="c b">Comprovante Nº {{ str_pad((string) $mov->id, 6, '0', STR_PAD_LEFT) }}</div>
        <div class="sep"></div>

        <div class="linha"><span>Caixa</span><span>Nº {{ str_pad((string) $caixa->id, 6, '0', STR_PAD_LEFT) }}</span></div>
        <div class="linha"><span>Terminal</span><span>{{ $caixa->terminal?->nome ?? '—' }}</span></div>
        <div class="linha"><span>Unidade</span><span>{{ $caixa->unidade?->nome ?? '—' }}</span></div>
        <div class="linha"><span>Data/Hora</span><span>{{ $mov->created_at->format('d/m/Y H:i') }}</span></div>
        <div class="linha"><span>Operador</span><span>{{ $mov->usuario?->name ?? '—' }}</span></div>
        <div class="sep"></div>

        <div class="linha valor"><span>{{ $sangria ? 'VALOR RETIRADO' : 'VALOR COLOCADO' }}</span><span>{{ $m($mov->valor) }}</span></div>
        <div class="linha"><span>Forma</span><span>Dinheiro</span></div>
        @if (filled($mov->descricao))
            <div style="margin-top: 4px">Motivo: {{ $mov->descricao }}</div>
        @endif
        @if ($mov->estaEstornada())
            <div class="c b" style="margin-top: 6px; border: 2px solid #000; padding: 2px">ESTORNADO EM {{ $mov->estornado_em->format('d/m/Y H:i') }}</div>
        @endif

        <div class="assinatura">{{ $sangria ? 'Entregue por (operador do caixa)' : 'Entregue por (quem trouxe o troco)' }}</div>
        <div class="assinatura">{{ $sangria ? 'Recebido por (gerente / cofre)' : 'Recebido por (operador do caixa)' }}</div>
        <div class="sep"></div>
        <div class="c" style="font-size: 10px">Impresso em {{ now()->format('d/m/Y H:i') }}</div>
    </div>

    @include('partials.escpos-imprimir-script')
    <script>
        const MODO = @json($impressaoModo);
        const imprimirComprovante = () => imprimirEscPos({
            url: @json(route('caixas.movimentacoes.comprovante.escpos', [$caixa, $mov])),
            agente: @json($escposAgenteUrl),
            colunas: @json((int) $escposColunas),
            arquivo: '{{ $mov->categoria }}-{{ $mov->id }}.bin',
            status: document.getElementById('status'),
            botao: document.getElementById('btn-escpos'),
        });

        document.getElementById('btn-escpos')?.addEventListener('click', imprimirComprovante);
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') window.close(); });

        // Imprime sozinho ao abrir; ?ver=1 só mostra.
        window.addEventListener('load', async () => {
            if (new URLSearchParams(window.location.search).has('ver')) return;
            if (MODO === 'escpos') { if (await imprimirComprovante()) setTimeout(() => window.close(), 800); return; }
            setTimeout(() => window.print(), 250);
        });
        window.addEventListener('afterprint', () => setTimeout(() => window.close(), 300));
    </script>
</body>
</html>
