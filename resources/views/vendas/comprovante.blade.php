{{-- Comprovante térmico 80mm — layout alinhado ao cupom do GestorWEB (PdvCupomSimplesPrint). --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cupom #{{ str_pad((string) $venda->id, 6, '0', STR_PAD_LEFT) }} · {{ $empresaNome }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: #e2e8f0;
            color: #0f172a;
            font-family: system-ui, -apple-system, Segoe UI, sans-serif;
        }
        .cupom-toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            justify-content: center;
            padding: 12px 16px;
            background: #0f172a;
            color: #fff;
        }
        .cupom-toolbar a,
        .cupom-toolbar button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            border-radius: 8px;
            border: 0;
            padding: 10px 14px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            color: #0f172a;
            background: #fff;
        }
        .cupom-toolbar .btn-print { background: #16a34a; color: #fff; }
        .cupom-toolbar .btn-muted { background: #334155; color: #e2e8f0; }
        .cupom-preview {
            padding: 24px 12px 40px;
        }

        /* === Cupom térmico 80mm (GestorWEB cupom-thermal.css) === */
        .cupom-thermal {
            --cw: 302px;
            --ff: 'Courier New', Courier, monospace;
            --fs: 10.5px;
            --fw-bold: 700;
            width: var(--cw);
            margin: 0 auto;
            padding: 10px 10px 14px;
            background: #fff;
            color: #111;
            font-family: var(--ff);
            font-size: var(--fs);
            line-height: 1.45;
            box-shadow: 0 2px 24px rgba(0, 0, 0, 0.12);
            border-radius: 2px;
        }
        .cupom-thermal__header { text-align: center; margin-bottom: 6px; }
        .cupom-thermal__empresa {
            font-size: 12.5px;
            font-weight: var(--fw-bold);
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin: 0 0 2px;
        }
        .cupom-thermal__info { font-size: 9.5px; color: #333; margin: 1px 0; }
        .cupom-thermal__num {
            text-align: center;
            font-weight: var(--fw-bold);
            font-size: 11px;
            margin: 0 0 4px;
            letter-spacing: 0.08em;
        }
        .cupom-thermal__datahora {
            text-align: center;
            font-size: 9px;
            color: #555;
            margin: 0 0 4px;
        }
        .cupom-thermal__sep {
            border-top: 1px dashed #555;
            border-bottom: 1px dashed #555;
            text-align: center;
            font-size: 9px;
            font-weight: var(--fw-bold);
            letter-spacing: 0.1em;
            padding: 2px 0;
            margin: 5px 0;
            text-transform: uppercase;
        }
        .cupom-thermal__sep--dbl {
            border-top: 2px solid #111;
            border-bottom: 2px solid #111;
            font-size: 10px;
            padding: 3px 0;
            margin: 6px 0 2px;
        }
        .cupom-thermal__sep--thin {
            border-top: 1px dashed #aaa;
            border-bottom: 0;
            margin: 6px 0;
            padding: 0;
            letter-spacing: 0;
            font-size: 0;
            height: 0;
        }
        .cupom-thermal__itens {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
        }
        .cupom-thermal__itens thead th {
            font-weight: var(--fw-bold);
            font-size: 8.5px;
            letter-spacing: 0.06em;
            padding: 2px;
            border-bottom: 1px solid #444;
        }
        .cupom-thermal__itens tbody td { padding: 2px; vertical-align: top; }
        .cupom-thermal__item--alt td { background: #f7f7f7; }
        .cupom-thermal .al-l { text-align: left; }
        .cupom-thermal .al-r { text-align: right; white-space: nowrap; }
        .cupom-thermal__item-n { width: 16px; color: #666; }
        .cupom-thermal__item-desc { max-width: 118px; word-break: break-word; line-height: 1.3; }
        .cupom-thermal__item-qtd { width: 30px; }
        .cupom-thermal__item-unit { width: 42px; }
        .cupom-thermal__item-tot { width: 46px; font-weight: var(--fw-bold); }
        .cupom-thermal__totais { margin: 2px 0; }
        .cupom-thermal__bloco { margin: 2px 0; }
        .cupom-thermal__tot-linha,
        .cupom-thermal__linha-par {
            display: flex;
            justify-content: space-between;
            gap: 4px;
            font-size: 9.5px;
            padding: 1.5px 0;
        }
        .cupom-thermal__tot-linha--desc { color: #b91c1c; }
        .cupom-thermal__tot-linha--pgto { font-weight: var(--fw-bold); }
        .cupom-thermal__tot-linha--troco { font-weight: var(--fw-bold); color: #15803d; }
        .cupom-thermal__tot-linha--muted { color: #888; }
        .cupom-thermal__total-geral {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            border-top: 2px solid #111;
            border-bottom: 2px solid #111;
            padding: 4px 0;
            margin: 4px 0 5px;
            font-weight: var(--fw-bold);
        }
        .cupom-thermal__total-geral span:first-child { font-size: 13px; letter-spacing: 0.08em; }
        .cupom-thermal__total-geral span:last-child { font-size: 15px; letter-spacing: 0.04em; }
        .cupom-thermal__rodape { margin: 2px 0; }
        .cupom-thermal__barcode {
            display: flex;
            align-items: stretch;
            justify-content: center;
            gap: 1.5px;
            height: 36px;
            margin: 6px 0 2px;
            overflow: hidden;
        }
        .cupom-thermal__bar { display: inline-block; background: #111; }
        .cupom-thermal__barcode-num {
            text-align: center;
            font-size: 8px;
            letter-spacing: 0.2em;
            margin: 0 0 4px;
            color: #333;
        }
        .cupom-thermal__msg {
            text-align: center;
            font-weight: var(--fw-bold);
            font-size: 10.5px;
            margin: 3px 0 1px;
            letter-spacing: 0.03em;
        }
        .cupom-thermal__msg--sub {
            font-weight: 400;
            font-size: 9px;
            color: #555;
            margin-bottom: 4px;
            text-align: center;
        }
        .cupom-thermal__doc-aux {
            text-align: center;
            font-size: 8px;
            color: #888;
            margin-top: 5px;
            font-style: italic;
        }
        .cupom-thermal__obs {
            font-size: 9px;
            margin: 2px 0;
            line-height: 1.35;
            word-break: break-word;
        }
        .cupom-thermal__tickets { margin-top: 6px; text-align: center; }
        .cupom-thermal__ticket {
            border-top: 1px dashed #aaa;
            padding: 6px 0;
        }
        .cupom-thermal__ticket img { display: block; margin: 4px auto; max-width: 100%; }
        .cupom-thermal__ticket-cod {
            font-size: 8px;
            letter-spacing: 0.12em;
            color: #555;
            margin: 2px 0 0;
        }

        @media print {
            body { background: #fff; }
            .cupom-toolbar { display: none !important; }
            .cupom-preview { padding: 0; }
            .cupom-thermal {
                --cw: 80mm;
                width: 80mm;
                padding: 4mm;
                box-shadow: none;
                margin: 0;
                border-radius: 0;
            }
            @page { size: 80mm auto; margin: 0; }
        }
    </style>
</head>
<body>
    @php
        $fmt = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
        $fmtNum = fn ($v) => number_format((float) $v, 2, ',', '.');
        $formaLabel = match ($venda->forma_pagamento) {
            'dinheiro' => 'Dinheiro',
            'pix' => 'Pix',
            'cartao_credito' => 'Cartão crédito',
            'cartao_debito' => 'Cartão débito',
            default => ucfirst(str_replace('_', ' ', (string) $venda->forma_pagamento)),
        };
        $vendaIdPad = str_pad((string) $venda->id, 6, '0', STR_PAD_LEFT);
        $barcodePad = str_pad((string) $venda->id, 13, '0', STR_PAD_LEFT);
        $dataHora = $venda->created_at?->format('d/m/Y H:i') ?? now()->format('d/m/Y H:i');
        $qtdeItens = $venda->itens->sum('quantidade');

        // Barras decorativas (mesmo espírito do GestorWEB) — seed pelo id da venda
        $bars = [];
        $seed = max(1, (int) $venda->id);
        for ($i = 0; $i < 48; $i++) {
            $seed = ($seed * 1103515245 + 12345) & 0x7fffffff;
            $bars[] = ($seed % 3 === 0) ? 3 : 1.5;
        }
    @endphp

    @php
        $modo = $impressaoModo ?? 'dom';
        $mostrarDom = in_array($modo, ['dom', 'ambos'], true);
        $mostrarEscpos = in_array($modo, ['escpos', 'ambos'], true);
        $primarioEscpos = $modo === 'escpos' || ($modo === 'ambos' && ($impressaoAuto ?? false));
    @endphp

    <div class="cupom-toolbar no-print">
        @if ($mostrarDom)
            <button type="button" class="btn-print" id="btn-dom" style="{{ $primarioEscpos ? 'background:#334155' : '' }}" onclick="window.print()">Imprimir DOM 80mm</button>
        @endif
        @if ($mostrarEscpos)
            <button type="button" class="btn-print" id="btn-escpos" style="background:{{ $primarioEscpos || $modo === 'escpos' ? '#0f766e' : '#334155' }}">Imprimir ESC/POS</button>
            <a href="{{ $escposUrl }}" class="btn-muted" download>Baixar .bin ESC/POS</a>
        @endif
        <a href="{{ route('vendas.create') }}" class="btn-print" style="background:#1d4ed8">Nova venda</a>
        @if ($venda->status === 'pago' && $venda->ehEntradaAvulsa())
            <a href="{{ route('vendas.voucher', $venda) }}" target="_blank">Baixar PDF voucher</a>
        @endif
        <a href="{{ route('vendas.show', $venda) }}" class="btn-muted">Detalhes</a>
        <a href="{{ route('vendas.index') }}" class="btn-muted">Lista</a>
        <a href="{{ route('empresa.edit') }}" class="btn-muted" title="Configurar impressão">⚙ Impressão</a>
    </div>
    <p id="escpos-status" class="no-print" style="text-align:center;font-size:12px;color:#334155;margin:8px 0 0;font-family:system-ui,sans-serif"></p>

    <div class="cupom-preview">
        <div class="cupom-thermal" id="recibo">
            <header class="cupom-thermal__header">
                <p class="cupom-thermal__empresa">{{ $empresaNome }}</p>
                @if ($empresaCnpj)
                    <p class="cupom-thermal__info">CNPJ: {{ $empresaCnpj }}</p>
                @endif
                @if ($empresaEndereco)
                    <p class="cupom-thermal__info">{{ $empresaEndereco }}</p>
                @endif
                @if ($empresaTelefone)
                    <p class="cupom-thermal__info">Tel.: {{ $empresaTelefone }}</p>
                @endif
            </header>

            <div class="cupom-thermal__sep cupom-thermal__sep--dbl">CUPOM DE VENDA</div>
            <p class="cupom-thermal__num">Nº {{ $vendaIdPad }}</p>
            <p class="cupom-thermal__datahora">{{ $dataHora }}</p>

            @if ($venda->cliente)
                <div class="cupom-thermal__bloco">
                    <div class="cupom-thermal__sep">CLIENTE</div>
                    <p class="cupom-thermal__linha-par">
                        <span>Nome</span>
                        <span>{{ $venda->cliente->nome }}</span>
                    </p>
                    @if ($venda->cliente->cpf)
                        <p class="cupom-thermal__linha-par">
                            <span>CPF</span>
                            <span>{{ $venda->cliente->cpf }}</span>
                        </p>
                    @endif
                </div>
            @endif

            <div class="cupom-thermal__sep">ITENS</div>
            <table class="cupom-thermal__itens">
                <thead>
                    <tr>
                        <th class="al-l">#</th>
                        <th class="al-l">DESCRIÇÃO</th>
                        <th class="al-r">QTD</th>
                        <th class="al-r">UNIT</th>
                        <th class="al-r">TOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($venda->itens as $idx => $item)
                        <tr @class(['cupom-thermal__item--alt' => $idx % 2 === 1])>
                            <td class="al-l cupom-thermal__item-n">{{ $idx + 1 }}</td>
                            <td class="al-l cupom-thermal__item-desc">
                                {{ $item->nomeItem() }}
                                @if ((float) $item->desconto > 0)
                                    <br><span style="color:#b91c1c;font-size:8px">desc. -{{ $fmtNum($item->desconto) }}</span>
                                @endif
                            </td>
                            <td class="al-r cupom-thermal__item-qtd">{{ (int) $item->quantidade }}</td>
                            <td class="al-r cupom-thermal__item-unit">{{ $fmtNum($item->preco_unitario) }}</td>
                            <td class="al-r cupom-thermal__item-tot">{{ $fmtNum($item->subtotal) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="cupom-thermal__sep--thin"></div>

            <div class="cupom-thermal__totais">
                <div class="cupom-thermal__tot-linha">
                    <span>Qtde de itens</span>
                    <span>{{ $qtdeItens }}</span>
                </div>
                <div class="cupom-thermal__tot-linha">
                    <span>Subtotal</span>
                    <span>{{ $fmt($venda->valor_bruto) }}</span>
                </div>
                @if ((float) $venda->desconto > 0)
                    <div class="cupom-thermal__tot-linha cupom-thermal__tot-linha--desc">
                        <span>Desconto</span>
                        <span>- {{ $fmt($venda->desconto) }}</span>
                    </div>
                @endif
            </div>

            <div class="cupom-thermal__total-geral">
                <span>TOTAL</span>
                <span>{{ $fmt($venda->valor_total) }}</span>
            </div>

            <div class="cupom-thermal__sep">PAGAMENTO</div>
            <div class="cupom-thermal__totais">
                <div class="cupom-thermal__tot-linha cupom-thermal__tot-linha--pgto">
                    <span>{{ $formaLabel }}</span>
                    <span>{{ $fmt($venda->valor_total) }}</span>
                </div>
                @if ($valorRecebido !== null)
                    <div class="cupom-thermal__tot-linha">
                        <span>Valor recebido</span>
                        <span>{{ $fmt($valorRecebido) }}</span>
                    </div>
                @endif
                @if ($troco !== null && $troco > 0)
                    <div class="cupom-thermal__tot-linha cupom-thermal__tot-linha--troco">
                        <span>Troco</span>
                        <span>{{ $fmt($troco) }}</span>
                    </div>
                @endif
            </div>

            @if ($obsLimpa)
                <div class="cupom-thermal__bloco">
                    <div class="cupom-thermal__sep">OBSERVACOES</div>
                    <p class="cupom-thermal__obs">{{ $obsLimpa }}</p>
                </div>
            @endif

            <div class="cupom-thermal__sep--thin"></div>
            <div class="cupom-thermal__rodape">
                <div class="cupom-thermal__tot-linha">
                    <span>Operador</span>
                    <span>{{ $venda->vendedor?->name ?? '—' }}</span>
                </div>
                <div class="cupom-thermal__tot-linha">
                    <span>Data / Hora</span>
                    <span>{{ $dataHora }}</span>
                </div>
                <div class="cupom-thermal__tot-linha">
                    <span>Código da venda</span>
                    <span>{{ $vendaIdPad }}</span>
                </div>
                @if ($venda->unidade)
                    <div class="cupom-thermal__tot-linha cupom-thermal__tot-linha--muted">
                        <span>Unidade</span>
                        <span>{{ $venda->unidade->nome }}</span>
                    </div>
                @endif
            </div>

            <div class="cupom-thermal__barcode" aria-hidden="true">
                @foreach ($bars as $w)
                    <span class="cupom-thermal__bar" style="width: {{ $w }}px"></span>
                @endforeach
            </div>
            <p class="cupom-thermal__barcode-num">{{ $barcodePad }}</p>

            @if ($acessos->isNotEmpty())
                <div class="cupom-thermal__sep">INGRESSOS / QR</div>
                <div class="cupom-thermal__tickets">
                    <p class="cupom-thermal__msg--sub">Escaneie na portaria para validar</p>
                    @foreach ($acessos as $acesso)
                        <div class="cupom-thermal__ticket">
                            <p class="cupom-thermal__info">{{ $acesso->vendaItem?->tipoEntrada?->nome ?? 'Entrada' }}</p>
                            @if ($qrCodes[$acesso->id] ?? null)
                                <img src="{{ $qrCodes[$acesso->id] }}" alt="QR" width="120" height="120">
                            @endif
                            @if ($barcodes[$acesso->id] ?? null)
                                <img src="{{ $barcodes[$acesso->id] }}" alt="Barcode" style="height:28px;width:auto;max-width:100%">
                            @endif
                            <p class="cupom-thermal__ticket-cod">{{ $acesso->codigo_validacao }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

            <p class="cupom-thermal__msg">OBRIGADO PELA PREFERÊNCIA</p>
            <p class="cupom-thermal__msg cupom-thermal__msg--sub">Volte sempre!</p>
            <p class="cupom-thermal__doc-aux">*** DOCUMENTO AUXILIAR — SEM VALIDADE FISCAL ***</p>
        </div>
    </div>

    <script>
        const ESCPOS_URL = @json($escposUrl);
        const ESCPOS_AGENTE = @json($escposAgenteUrl);
        const IMPRESSAO_MODO = @json($impressaoModo ?? 'dom');
        const IMPRESSAO_AUTO = @json((bool) ($impressaoAuto ?? false));
        const statusEl = document.getElementById('escpos-status');

        function setStatus(msg) {
            if (statusEl) statusEl.textContent = msg || '';
        }

        async function baixarEscPosBytes() {
            const res = await fetch(ESCPOS_URL, { credentials: 'same-origin', headers: { 'Accept': 'application/octet-stream' } });
            if (! res.ok) throw new Error('Falha ao gerar ESC/POS (HTTP ' + res.status + ')');
            return new Uint8Array(await res.arrayBuffer());
        }

        function bytesToBase64(bytes) {
            let binary = '';
            const chunk = 0x8000;
            for (let i = 0; i < bytes.length; i += chunk) {
                binary += String.fromCharCode(...bytes.subarray(i, i + chunk));
            }
            return btoa(binary);
        }

        async function enviarAgenteLocal(bytes) {
            if (! ESCPOS_AGENTE) return false;
            const base64 = bytesToBase64(bytes);
            const ctrl = new AbortController();
            const timer = setTimeout(() => ctrl.abort(), 2500);
            try {
                const res = await fetch(ESCPOS_AGENTE.replace(/\/$/, '') + '/print', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        columns: {{ (int) $escposColunas }},
                        cut: true,
                        payload_base64: base64,
                    }),
                    signal: ctrl.signal,
                });
                clearTimeout(timer);
                return res.ok;
            } catch (e) {
                clearTimeout(timer);
                return false;
            }
        }

        async function enviarWebSerial(bytes) {
            if (! ('serial' in navigator)) return false;
            const port = await navigator.serial.requestPort();
            await port.open({ baudRate: 9600 });
            const writer = port.writable.getWriter();
            try {
                await writer.write(bytes);
            } finally {
                writer.releaseLock();
                await port.close();
            }
            return true;
        }

        async function imprimirEscPos() {
            const btn = document.getElementById('btn-escpos');
            if (btn) btn.disabled = true;
            setStatus('Gerando cupom ESC/POS...');
            try {
                const bytes = await baixarEscPosBytes();

                setStatus('Tentando agente local (' + ESCPOS_AGENTE + ')...');
                if (await enviarAgenteLocal(bytes)) {
                    setStatus('Enviado ao agente ESC/POS.');
                    return;
                }

                if ('serial' in navigator) {
                    setStatus('Selecione a porta serial da impressora...');
                    try {
                        if (await enviarWebSerial(bytes)) {
                            setStatus('Enviado via Web Serial.');
                            return;
                        }
                    } catch (e) {
                        // usuário cancelou a porta — cai no download
                    }
                }

                const blob = new Blob([bytes], { type: 'application/octet-stream' });
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'cupom-venda-{{ str_pad((string) $venda->id, 6, '0', STR_PAD_LEFT) }}.bin';
                a.click();
                URL.revokeObjectURL(a.href);
                setStatus('Agente/Serial indisponíveis — arquivo .bin baixado.');
            } catch (e) {
                setStatus(e.message || 'Erro ao imprimir ESC/POS');
                alert(e.message || 'Erro ao imprimir ESC/POS');
            } finally {
                if (btn) btn.disabled = false;
            }
        }

        function imprimirPadrao() {
            if (IMPRESSAO_MODO === 'escpos') {
                imprimirEscPos();
            } else {
                window.print();
            }
        }

        document.getElementById('btn-escpos')?.addEventListener('click', imprimirEscPos);

        document.addEventListener('keydown', (e) => {
            if (['INPUT', 'TEXTAREA'].includes(e.target.tagName)) return;
            if (e.key === 'Enter' || e.key === 'p' || e.key === 'P') {
                e.preventDefault();
                imprimirPadrao();
            }
            if ((e.key === 'e' || e.key === 'E') && document.getElementById('btn-escpos')) {
                e.preventDefault();
                imprimirEscPos();
            }
        });

        if (IMPRESSAO_AUTO) {
            window.addEventListener('load', () => setTimeout(imprimirPadrao, 400));
        }
    </script>
</body>
</html>
