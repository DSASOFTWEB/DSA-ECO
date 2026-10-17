{{--
    QR Code Pix para imprimir na térmica 80mm e entregar ao cliente.
    Abre numa janela à parte (o modal do Pix segue aguardando o pagamento)
    e imprime sozinha conforme o modo da empresa: DOM (window.print) ou
    ESC/POS (agente local → Web Serial → download do .bin).
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR Code Pix · {{ $empresaNome }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #e2e8f0; color: #000; font-family: system-ui, -apple-system, Segoe UI, sans-serif; }
        .toolbar { position: sticky; top: 0; display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; padding: 10px; background: #0f172a; }
        .toolbar button { border: 0; border-radius: 8px; padding: 9px 14px; font-size: 13px; font-weight: 700; cursor: pointer; background: #fff; color: #0f172a; }
        .toolbar .principal { background: #16a34a; color: #fff; }
        .toolbar .escpos { background: #0f766e; color: #fff; }
        #status { text-align: center; font-size: 12px; color: #334155; margin: 6px 0 0; min-height: 16px; }

        /* Bobina 80mm: ~72mm de área útil */
        .ticket { width: 302px; margin: 16px auto 32px; padding: 12px 10px 16px; background: #fff; text-align: center; font-family: 'Courier New', Courier, monospace; font-size: 11px; line-height: 1.35; }
        .ticket .empresa { font-size: 13px; font-weight: 700; text-transform: uppercase; }
        .ticket .sep { border-top: 1px dashed #000; margin: 8px 0; }
        .ticket .titulo { font-size: 14px; font-weight: 700; letter-spacing: .5px; }
        .ticket .valor { font-size: 22px; font-weight: 700; margin: 6px 0 2px; }
        .ticket img { width: 62mm; max-width: 100%; height: auto; image-rendering: pixelated; display: block; margin: 6px auto; }
        .ticket .copia { text-align: left; word-break: break-all; font-size: 9.5px; }
        .ticket .aviso { font-weight: 700; font-size: 10px; }

        @media print {
            @page { size: 80mm auto; margin: 0; }
            body { background: #fff; }
            .no-print { display: none !important; }
            .ticket { margin: 0 auto; width: 72mm; padding: 2mm 0 4mm; }
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
        <button type="button" onclick="window.close()">Fechar</button>
    </div>
    <p id="status" class="no-print"></p>

    <div class="ticket">
        <div class="empresa">{{ $empresaNome }}</div>
        <div class="sep"></div>
        <div class="titulo">PAGUE COM PIX</div>
        <div>{{ $referencia }}</div>
        <div class="valor">R$ {{ number_format((float) $cobranca->valor, 2, ',', '.') }}</div>
        <img src="{{ $qrCode }}" alt="QR Code Pix">
        <div>Abra o app do seu banco e leia o QR Code</div>
        @if ($cobranca->expira_em)
            <div>Válido até {{ $cobranca->expira_em->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</div>
        @endif
        <div class="sep"></div>
        <div class="copia"><strong>Pix copia e cola:</strong><br>{{ $cobranca->pix_copia_e_cola }}</div>
        <div class="sep"></div>
        <div class="aviso">*** NÃO É COMPROVANTE DE PAGAMENTO ***</div>
    </div>

    <script>
        const MODO = @json($impressaoModo);
        const ESCPOS_URL = @json(route('vendas.pix.imprimir.escpos', $cobranca));
        const ESCPOS_AGENTE = @json($escposAgenteUrl);
        const COLUNAS = @json((int) $escposColunas);
        const statusEl = document.getElementById('status');
        const setStatus = (msg) => { statusEl.textContent = msg || ''; };

        async function imprimirEscPos() {
            const btn = document.getElementById('btn-escpos');
            if (btn) btn.disabled = true;
            try {
                setStatus('Gerando QR Code ESC/POS...');
                const res = await fetch(ESCPOS_URL, { credentials: 'same-origin' });
                if (! res.ok) throw new Error('Falha ao gerar ESC/POS (HTTP ' + res.status + ')');
                const bytes = new Uint8Array(await res.arrayBuffer());

                if (ESCPOS_AGENTE) {
                    let binario = '';
                    for (let i = 0; i < bytes.length; i += 0x8000) binario += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
                    const ctrl = new AbortController();
                    const timer = setTimeout(() => ctrl.abort(), 2500);
                    try {
                        const r = await fetch(ESCPOS_AGENTE.replace(/\/$/, '') + '/print', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ columns: COLUNAS, cut: true, payload_base64: btoa(binario) }),
                            signal: ctrl.signal,
                        });
                        if (r.ok) { setStatus('QR Code enviado à impressora.'); return; }
                    } catch (e) { /* agente fora do ar: tenta Web Serial */ } finally { clearTimeout(timer); }
                }

                if ('serial' in navigator) {
                    try {
                        setStatus('Selecione a porta serial da impressora...');
                        const port = await navigator.serial.requestPort();
                        await port.open({ baudRate: 9600 });
                        const writer = port.writable.getWriter();
                        try { await writer.write(bytes); } finally { writer.releaseLock(); await port.close(); }
                        setStatus('QR Code enviado via Web Serial.');
                        return;
                    } catch (e) { /* cancelou a porta: cai no download */ }
                }

                const a = document.createElement('a');
                a.href = URL.createObjectURL(new Blob([bytes], { type: 'application/octet-stream' }));
                a.download = 'pix-qrcode-{{ $cobranca->id }}.bin';
                a.click();
                URL.revokeObjectURL(a.href);
                setStatus('Agente/Serial indisponíveis — arquivo .bin baixado.');
            } catch (e) {
                setStatus(e.message || 'Erro ao imprimir ESC/POS');
            } finally {
                if (btn) btn.disabled = false;
            }
        }

        document.getElementById('btn-escpos')?.addEventListener('click', imprimirEscPos);

        // Imprime sozinho ao abrir; na impressão DOM fecha a janela depois.
        window.addEventListener('load', () => {
            if (MODO === 'escpos') {
                imprimirEscPos();
                return;
            }
            if (window.opener) window.addEventListener('afterprint', () => setTimeout(() => window.close(), 300));
            setTimeout(() => window.print(), 250);
        });
    </script>
</body>
</html>
