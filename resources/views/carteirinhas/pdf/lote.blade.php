<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 10mm 8mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #1d2939; }

        /* Grade de cartões via <table> de propósito — dompdf calcula mal a
           posição de floats irmãos quando o conteúdo de um deles varia de
           altura (ex.: nome do plano mais comprido quebrando linha), e
           chegava a sobrepor um cartão em cima do outro. Table é o layout
           mais previsível no dompdf. */
        table.grade { width: 100%; border-collapse: separate; border-spacing: 3mm; }
        table.grade td { width: 50%; vertical-align: top; padding: 0; }
        tr { page-break-inside: avoid; }

        .cartao {
            width: 85.6mm;
            height: 54mm;
            padding: 3.5mm 5mm 3mm;
            border-radius: 3mm;
            /* Azul mais escuro + textos brancos: no papel (jato de tinta) os tons
               claros sobre azul ficavam apagados. */
            background-color: #1e3a8a;
            color: #ffffff;
            overflow: hidden;
        }

        .linha-topo, .corpo, .rodape { display: table; width: 100%; }
        .linha-topo { margin-bottom: 2.5mm; }
        .corpo { margin-bottom: 2mm; }

        .linha-topo .cel-logo { display: table-cell; vertical-align: middle; width: 70%; }
        .linha-topo .cel-rotulo { display: table-cell; vertical-align: middle; width: 30%; text-align: right; font-size: 7pt; font-weight: bold; letter-spacing: 0.5pt; text-transform: uppercase; color: #ffffff; }
        .logo { max-height: 7mm; max-width: 28mm; }
        .empresa-nome { font-size: 8.5pt; font-weight: bold; color: #ffffff; }

        .corpo .cel-foto { display: table-cell; vertical-align: middle; width: 16mm; }
        /* dompdf ignora line-height para centralizar e soma padding à altura:
           padding-top 5mm + height 9mm = círculo de 14mm com as iniciais no meio. */
        .foto { width: 14mm; height: 9mm; border-radius: 7mm; background-color: #1e40af; text-align: center; line-height: 1; padding-top: 5mm; font-size: 11pt; font-weight: bold; color: #ffffff; }
        .foto.com-imagem { padding-top: 0; height: 14mm; }
        .foto img { width: 14mm; height: 14mm; border-radius: 7mm; }
        .corpo .cel-dados { display: table-cell; vertical-align: middle; }
        /* Tamanho calculado por nome (CarteirinhaPdfExport::fonteDoNome): o nome
           completo sempre cabe — diminui a fonte e, se preciso, usa 2 linhas. */
        .dados .nome { font-weight: bold; color: #ffffff; line-height: 1.15; word-wrap: break-word; }
        .dados .info { font-size: 8pt; font-weight: bold; color: #ffffff; margin-top: 1mm; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .rodape .cel-info { display: table-cell; vertical-align: bottom; }
        .rodape .cel-qr { display: table-cell; vertical-align: bottom; width: 15mm; text-align: right; }
        .codigo { font-size: 7pt; font-weight: bold; color: #ffffff; letter-spacing: 0.3pt; }
        .validade { font-size: 7pt; color: #ffffff; margin-top: 1mm; }
        .qr-caixa { display: inline-block; background: #ffffff; padding: 1mm; border-radius: 1mm; }
        .qr-caixa img { width: 12mm; height: 12mm; }

        .info-impressao { margin-top: 10mm; font-size: 8pt; color: #98a2b3; }
    </style>
</head>
<body>
    <table class="grade">
        @foreach ($cartoes->chunk(2) as $linha)
            <tr>
                @foreach ($linha as $cartao)
                    <td>
                        <div class="cartao">
                            <div class="linha-topo">
                                <div class="cel-logo">
                                    @if ($cartao['empresa']?->logoDataUri())
                                        <img class="logo" src="{{ $cartao['empresa']->logoDataUri() }}" alt="">
                                    @else
                                        <span class="empresa-nome">{{ $cartao['empresa']?->nome }}</span>
                                    @endif
                                </div>
                                <div class="cel-rotulo">Carteirinha</div>
                            </div>

                            <div class="corpo">
                                <div class="cel-foto">
                                    <div class="foto {{ $cartao['fotoDataUri'] ? 'com-imagem' : '' }}">
                                        @if ($cartao['fotoDataUri'])
                                            <img src="{{ $cartao['fotoDataUri'] }}" alt="">
                                        @else
                                            {{ collect(explode(' ', $cartao['nome']))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') }}
                                        @endif
                                    </div>
                                </div>
                                <div class="cel-dados">
                                    <div class="dados">
                                        <div class="nome" style="font-size: {{ $cartao['nomeFonte'] }}pt">{{ $cartao['nome'] }}</div>
                                        <div class="info">{{ $cartao['ehDependente'] ? 'Dependente' : 'Titular' }}@if($cartao['planoNome']) &middot; {{ $cartao['planoNome'] }}@endif</div>
                                    </div>
                                </div>
                            </div>

                            <div class="rodape">
                                <div class="cel-info">
                                    <div class="codigo">{{ $cartao['carteirinha']->codigo }}</div>
                                    <div class="validade">Validade: {{ $cartao['validade'] ?? 'enquanto o plano estiver ativo' }}</div>
                                </div>
                                <div class="cel-qr">
                                    <span class="qr-caixa"><img src="{{ $cartao['qrDataUri'] }}" alt="QR"></span>
                                </div>
                            </div>
                        </div>
                    </td>
                @endforeach
                @if ($linha->count() < 2)
                    <td></td>
                @endif
            </tr>
        @endforeach
    </table>

    <p class="info-impressao">{{ $cartoes->count() }} carteirinha(s) &middot; gerado em {{ $geradoEm->format('d/m/Y H:i') }}</p>
</body>
</html>
