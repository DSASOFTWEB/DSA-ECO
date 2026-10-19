{{-- DANFE NFC-e em PDF (dompdf: só tabelas, sem flexbox). Mesmos dados de fiscal.danfce. --}}
@php
    $num = fn ($v) => number_format((float) $v, 2, ',', '.');
    $qtd = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', ''), '0'), ',');
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 6pt 8pt; }
        body { font-family: 'DejaVu Sans Mono', monospace; font-size: 7pt; line-height: 1.3; color: #000; }
        .c { text-align: center; }
        .r { text-align: right; }
        .b { font-weight: bold; }
        .sep { border-top: 1px dashed #000; margin: 4pt 0; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 0; vertical-align: top; }
        .homolog { border: 1px solid #000; padding: 2pt; text-align: center; font-weight: bold; margin: 3pt 0; }
        .chave { font-weight: bold; text-align: center; word-wrap: break-word; }
        .qr { text-align: center; margin-top: 4pt; }
        .qr img { width: 120pt; height: 120pt; }
    </style>
</head>
<body>
    <div class="c b">{{ $d['emitente']['razao'] }}</div>
    <div class="c">CNPJ: {{ $d['emitente']['cnpj'] }}@if ($d['emitente']['ie']) IE: {{ $d['emitente']['ie'] }}@endif</div>
    <div class="c">{{ $d['emitente']['endereco'] }}</div>
    <div class="c">{{ $d['emitente']['cidade'] }}</div>
    <div class="sep"></div>
    <div class="c b">Documento Auxiliar da Nota Fiscal de Consumidor Eletrônica</div>
    @if ($d['homologacao'])
        <div class="homolog">EMITIDA EM AMBIENTE DE HOMOLOGAÇÃO — SEM VALOR FISCAL</div>
    @endif
    <div class="sep"></div>

    <table>
        <tr class="b"><td>CÓDIGO DESCRIÇÃO</td><td class="r"></td></tr>
        <tr class="b"><td>QTD UN × VL UNIT</td><td class="r">VL TOTAL</td></tr>
        @foreach ($d['itens'] as $item)
            <tr><td colspan="2">{{ $item['codigo'] }} {{ $item['descricao'] }}</td></tr>
            <tr><td>&nbsp;&nbsp;{{ $qtd($item['quantidade']) }} {{ $item['unidade'] }} × {{ $num($item['valor_unitario']) }}</td><td class="r">{{ $num($item['valor_total']) }}</td></tr>
            @if ($item['desconto'] > 0)
                <tr><td>&nbsp;&nbsp;desconto</td><td class="r">-{{ $num($item['desconto']) }}</td></tr>
            @endif
        @endforeach
    </table>
    <div class="sep"></div>

    <table>
        <tr><td>Qtd. total de itens</td><td class="r">{{ $d['quantidade_itens'] }}</td></tr>
        <tr><td>Valor total R$</td><td class="r">{{ $num($d['total_produtos']) }}</td></tr>
        @if ($d['desconto'] > 0)
            <tr><td>Desconto R$</td><td class="r">-{{ $num($d['desconto']) }}</td></tr>
        @endif
        <tr class="b"><td>Valor a pagar R$</td><td class="r">{{ $num($d['total']) }}</td></tr>
        <tr class="b"><td>FORMA DE PAGAMENTO</td><td class="r">VALOR PAGO R$</td></tr>
        @foreach ($d['pagamentos'] as $pag)
            <tr><td>{{ $pag['forma'] }}</td><td class="r">{{ $num($pag['valor']) }}</td></tr>
        @endforeach
        @if ($d['troco'] > 0)
            <tr><td>Troco R$</td><td class="r">{{ $num($d['troco']) }}</td></tr>
        @endif
    </table>
    <div class="sep"></div>

    <div class="c">Consulte pela Chave de Acesso em</div>
    <div class="c">{{ $d['url_consulta'] }}</div>
    <div class="chave">{{ $d['chave_formatada'] }}</div>
    <div class="sep"></div>
    @if ($d['consumidor'])
        <div class="c">CONSUMIDOR — CPF/CNPJ: {{ $d['consumidor']['documento'] }}</div>
        @if ($d['consumidor']['nome'])<div class="c">{{ $d['consumidor']['nome'] }}</div>@endif
    @else
        <div class="c b">CONSUMIDOR FINAL — NÃO IDENTIFICADO</div>
    @endif
    <div class="sep"></div>

    <div class="c b">NFC-e nº {{ $d['numero'] }} Série {{ $d['serie'] }} {{ $d['emissao']?->format('d/m/Y H:i:s') }}</div>
    <div class="c">Protocolo de autorização: {{ $d['protocolo'] }}</div>
    @if ($d['autorizacao'])
        <div class="c">Data de autorização: {{ $d['autorizacao']->format('d/m/Y H:i:s') }}</div>
    @endif
    @if ($d['qr_code'] !== '')
        <div class="qr"><img src="{{ $qrCode }}" alt="QR Code"></div>
    @endif
    @if ($d['info_complementar'] !== '')
        <div class="sep"></div>
        <div class="c">{{ $d['info_complementar'] }}</div>
    @endif
</body>
</html>
