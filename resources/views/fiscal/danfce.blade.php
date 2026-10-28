{{--
    DANFE NFC-e (cupom fiscal) 80mm, no leiaute do Manual do DANFE NFC-e.
    Dados lidos do XML autorizado (App\Services\Fiscal\DanfceService).
    Imprime sozinho ao abrir, conforme o modo da empresa (DOM ou ESC/POS).
--}}
@php
    $num = fn ($v) => number_format((float) $v, 2, ',', '.');
    $qtd = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', ''), '0'), ',');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NFC-e nº {{ $d['numero'] }} · {{ $d['emitente']['fantasia'] ?: $d['emitente']['razao'] }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #e2e8f0; color: #000; font-family: system-ui, -apple-system, Segoe UI, sans-serif; }
        .toolbar { position: sticky; top: 0; display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; padding: 10px; background: #0f172a; }
        .toolbar button, .toolbar a { border: 0; border-radius: 8px; padding: 9px 14px; font-size: 13px; font-weight: 700; cursor: pointer; background: #fff; color: #0f172a; text-decoration: none; }
        .toolbar .principal { background: #16a34a; color: #fff; }
        .toolbar .escpos { background: #0f766e; color: #fff; }
        .toolbar .nova { background: #1d4ed8; color: #fff; }
        #status { text-align: center; font-size: 12px; color: #334155; margin: 6px 0 0; min-height: 16px; }

        .danfe { width: 302px; margin: 16px auto 32px; padding: 10px 8px 14px; background: #fff; font-family: 'Courier New', Courier, monospace; font-size: 10.5px; line-height: 1.35; }
        .c { text-align: center; }
        .b { font-weight: 700; }
        .sep { border-top: 1px dashed #000; margin: 6px 0; }
        .linha { display: flex; justify-content: space-between; gap: 8px; }
        .item-det { display: flex; justify-content: space-between; padding-left: 8px; }
        .homolog { border: 1px solid #000; padding: 3px; font-weight: 700; text-align: center; margin: 4px 0; }
        .chave { word-break: break-all; font-weight: 700; }
        .qr { display: block; width: 46mm; height: 46mm; margin: 6px auto; image-rendering: pixelated; }
        .total-pagar { font-size: 12px; }

        @media print {
            @page { size: 80mm auto; margin: 0; }
            body { background: #fff; }
            .no-print { display: none !important; }
            .danfe { margin: 0 auto; width: 72mm; padding: 1mm 0 4mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        @if (in_array($impressaoModo, ['dom', 'ambos'], true))
            <button type="button" class="principal" onclick="window.print()">Imprimir NFC-e 80mm</button>
        @endif
        @if (in_array($impressaoModo, ['escpos', 'ambos'], true))
            <button type="button" class="escpos" id="btn-escpos">Imprimir ESC/POS</button>
        @endif
        <a href="{{ $voltarUrl }}" class="nova">{{ $documento->hospedagem_id ? 'Voltar à hospedagem' : ($documento->origem === 'venda_mesa' ? 'Voltar às mesas' : '➕ Nova venda (Esc)') }}</a>
        @if ($documento->venda_id)
            <a href="{{ route('vendas.show', $documento->venda_id) }}">Detalhes da venda</a>
        @endif
    </div>
    <p id="status" class="no-print"></p>

    <div class="danfe">
        {{-- Divisão I: emitente --}}
        <div class="c b">{{ $d['emitente']['razao'] }}</div>
        <div class="c">CNPJ: {{ $d['emitente']['cnpj'] }}@if ($d['emitente']['ie']) &nbsp;IE: {{ $d['emitente']['ie'] }}@endif</div>
        <div class="c">{{ $d['emitente']['endereco'] }}</div>
        <div class="c">{{ $d['emitente']['cidade'] }}</div>
        <div class="sep"></div>
        <div class="c b">Documento Auxiliar da Nota Fiscal de Consumidor Eletrônica</div>

        @if ($d['homologacao'])
            <div class="homolog">EMITIDA EM AMBIENTE DE HOMOLOGAÇÃO — SEM VALOR FISCAL</div>
        @endif
        <div class="sep"></div>

        {{-- Divisão II: itens --}}
        <div class="linha b"><span>CÓDIGO DESCRIÇÃO</span></div>
        <div class="linha b"><span>QTD UN × VL UNIT</span><span>VL TOTAL</span></div>
        @foreach ($d['itens'] as $item)
            <div>{{ $item['codigo'] }} {{ $item['descricao'] }}</div>
            <div class="item-det"><span>{{ $qtd($item['quantidade']) }} {{ $item['unidade'] }} × {{ $num($item['valor_unitario']) }}</span><span>{{ $num($item['valor_total']) }}</span></div>
            @if ($item['desconto'] > 0)
                <div class="item-det"><span>desconto</span><span>-{{ $num($item['desconto']) }}</span></div>
            @endif
        @endforeach
        <div class="sep"></div>

        {{-- Divisão III: totais e pagamento --}}
        <div class="linha"><span>Qtd. total de itens</span><span>{{ $d['quantidade_itens'] }}</span></div>
        <div class="linha"><span>Valor total R$</span><span>{{ $num($d['total_produtos']) }}</span></div>
        @if ($d['desconto'] > 0)
            <div class="linha"><span>Desconto R$</span><span>-{{ $num($d['desconto']) }}</span></div>
        @endif
        <div class="linha b total-pagar"><span>Valor a pagar R$</span><span>{{ $num($d['total']) }}</span></div>
        <div class="linha b"><span>FORMA DE PAGAMENTO</span><span>VALOR PAGO R$</span></div>
        @foreach ($d['pagamentos'] as $pag)
            <div class="linha"><span>{{ $pag['forma'] }}</span><span>{{ $num($pag['valor']) }}</span></div>
        @endforeach
        @if ($d['troco'] > 0)
            <div class="linha"><span>Troco R$</span><span>{{ $num($d['troco']) }}</span></div>
        @endif
        <div class="sep"></div>

        {{-- Divisão IV/V: consulta pela chave e consumidor --}}
        <div class="c">Consulte pela Chave de Acesso em</div>
        <div class="c" style="word-break: break-all">{{ $d['url_consulta'] }}</div>
        <div class="c chave">{{ $d['chave_formatada'] }}</div>
        <div class="sep"></div>
        @if ($d['consumidor'])
            <div class="c">CONSUMIDOR — CPF/CNPJ: {{ $d['consumidor']['documento'] }}</div>
            @if ($d['consumidor']['nome'])<div class="c">{{ $d['consumidor']['nome'] }}</div>@endif
        @else
            <div class="c b">CONSUMIDOR FINAL — NÃO IDENTIFICADO</div>
        @endif
        <div class="sep"></div>

        {{-- Divisão VI/VII: identificação, protocolo e QR Code --}}
        <div class="c b">NFC-e nº {{ $d['numero'] }} &nbsp;Série {{ $d['serie'] }} &nbsp;{{ $d['emissao']?->format('d/m/Y H:i:s') }}</div>
        <div class="c">Protocolo de autorização: {{ $d['protocolo'] }}</div>
        @if ($d['autorizacao'])
            <div class="c">Data de autorização: {{ $d['autorizacao']->format('d/m/Y H:i:s') }}</div>
        @endif
        @if ($d['qr_code'] !== '')
            <img class="qr" src="{{ app(\App\Services\QrCodeService::class)->gerarDataUri($d['qr_code'], 260) }}" alt="QR Code de consulta da NFC-e">
        @endif

        @if ($d['info_complementar'] !== '')
            <div class="sep"></div>
            <div class="c">{{ $d['info_complementar'] }}</div>
        @endif
    </div>

    @include('partials.escpos-imprimir-script')
    <script>
        const MODO = @json($impressaoModo);
        const imprimirDanfce = () => imprimirEscPos({
            url: @json(route('fiscal.danfce.escpos', $documento)),
            agente: @json($escposAgenteUrl),
            colunas: @json((int) $escposColunas),
            arquivo: 'danfce-{{ $d['numero'] }}.bin',
            status: document.getElementById('status'),
            botao: document.getElementById('btn-escpos'),
        });

        const VOLTAR_URL = @json($voltarUrl);
        // Venda recém-finalizada no PDV: impresso o cupom, já abre a próxima venda.
        const CONTINUAR_PDV = @json((bool) session('pdv_continuar') && $documento->origem === 'venda_pdv');
        const aposImprimir = () => {
            if (! CONTINUAR_PDV) return;
            document.getElementById('status').textContent = 'Impresso. Abrindo nova venda...';
            setTimeout(() => { window.location.href = VOLTAR_URL; }, 600);
        };

        document.getElementById('btn-escpos')?.addEventListener('click', async () => { if (await imprimirDanfce()) aposImprimir(); });
        window.addEventListener('afterprint', aposImprimir);
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') { e.preventDefault(); window.location.href = VOLTAR_URL; }
        });

        // Imprime sozinho ao abrir (como o cupom do PDV); ?ver=1 só mostra.
        window.addEventListener('load', async () => {
            if (new URLSearchParams(window.location.search).has('ver')) return;
            if (MODO === 'escpos') { if (await imprimirDanfce()) aposImprimir(); return; }
            setTimeout(() => window.print(), 250);
        });
    </script>
</body>
</html>
