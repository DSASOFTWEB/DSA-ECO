<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px 30px; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1d2939; }

        .ticket {
            position: relative;
            width: 400px;
            margin: 0 auto 26px;
            display: table;
            table-layout: fixed;
            border-collapse: collapse;
            border-radius: 12px;
            border: 1.5px solid #0f172a;
            overflow: hidden;
            page-break-inside: avoid;
        }

        .lado-esquerda, .lado-direita, .perfuracao { display: table-cell; vertical-align: middle; }

        .lado-esquerda {
            width: 34%;
            background-color: #0369a1;
            color: #ffffff;
            text-align: center;
            padding: 22px 10px;
        }
        .lado-esquerda .icone {
            width: 30px; height: 30px;
            margin: 0 auto 8px;
            border-radius: 50%;
            background-color: rgba(255,255,255,0.2);
            text-align: center;
            line-height: 30px;
            font-size: 15px;
            font-weight: bold;
            color: #ffffff;
        }
        .lado-esquerda .rotulo { font-size: 11.5px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; line-height: 1.5; }

        .perfuracao {
            width: 14px;
            background-color: #ffffff;
            border-left: 2px dashed #94a3b8;
            border-right: 2px dashed #94a3b8;
        }
        .notch {
            position: absolute;
            left: 34%;
            margin-left: -9px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background-color: #ffffff;
        }
        .notch-topo { top: -9px; }
        .notch-base { bottom: -9px; }

        .lado-direita {
            width: 66%;
            background-color: #ffffff;
            padding: 16px 18px;
        }
        .lado-direita .empresa {
            font-size: 9.5px;
            font-weight: bold;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            color: #059669;
        }
        .lado-direita .empresa img { max-height: 20px; vertical-align: middle; margin-right: 6px; }
        .lado-direita .nome { margin-top: 6px; font-size: 15px; font-weight: bold; color: #0f172a; }
        .lado-direita .info { margin-top: 2px; font-size: 9px; color: #64748b; }

        .bloco-validacao { margin-top: 10px; }
        .bloco-validacao img.qr { width: 60px; height: 60px; vertical-align: middle; }
        .bloco-validacao .aviso-validacao { display: inline-block; vertical-align: middle; margin-left: 10px; font-size: 8.5px; font-weight: bold; color: #b45309; text-transform: uppercase; letter-spacing: 0.5px; max-width: 200px; }
        .codigo-barras { margin-top: 6px; }
        .codigo-barras img { height: 28px; }
        .codigo-barras .texto-codigo { display: block; margin-top: 1px; font-size: 6.5px; letter-spacing: 1px; color: #64748b; }

        .rodape-ticket { margin-top: 4px; font-size: 7.5px; letter-spacing: 1px; color: #94a3b8; text-transform: uppercase; }
    </style>
</head>
<body>
    @foreach ($acessos as $acesso)
        <div class="ticket">
            <div class="notch notch-topo"></div>
            <div class="notch notch-base"></div>

            <div class="lado-esquerda">
                <div class="icone">&#10003;</div>
                <div class="rotulo">Entrada<br>Paga</div>
            </div>

            <div class="perfuracao"></div>

            <div class="lado-direita">
                <div class="empresa">
                    @if ($empresa?->logoDataUri())
                        <img src="{{ $empresa->logoDataUri() }}" alt="">
                    @endif
                    {{ $empresa?->nome }}
                </div>
                <div class="nome">{{ $acesso->vendaItem?->tipoEntrada?->nome }}</div>
                <div class="info">{{ $venda->cliente?->nome ?? 'Consumidor final' }} &middot; {{ $venda->unidade->nome }}</div>
                <div class="info">Comprado em {{ $venda->created_at->format('d/m/Y \à\s H:i') }} &middot; {{ ucfirst(str_replace('_', ' ', $venda->forma_pagamento)) }}</div>

                @if ($qrCodes[$acesso->id] ?? null)
                    <div class="bloco-validacao">
                        <img class="qr" src="{{ $qrCodes[$acesso->id] }}" alt="QR de validação">
                        <span class="aviso-validacao">Escaneie na portaria para validar</span>
                    </div>
                @endif
                @if ($barcodes[$acesso->id] ?? null)
                    <div class="codigo-barras">
                        <img src="{{ $barcodes[$acesso->id] }}" alt="Código de barras">
                        <span class="texto-codigo">{{ $acesso->codigo_validacao }}</span>
                    </div>
                @endif

                <div class="rodape-ticket">Venda #{{ $venda->id }} &middot; acesso #{{ $acesso->id }}</div>
            </div>
        </div>
    @endforeach
</body>
</html>
