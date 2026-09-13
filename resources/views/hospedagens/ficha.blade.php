<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ficha de Hospedagem #{{ str_pad((string) $hospedagem->id, 6, '0', STR_PAD_LEFT) }} · {{ $empresa->nome ?? 'Pousada' }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: #e2e8f0;
            color: #0f172a;
            font-family: system-ui, -apple-system, Segoe UI, sans-serif;
        }
        .toolbar {
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
        }
        .toolbar button, .toolbar a {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border-radius: 8px;
            border: 0;
            padding: 10px 16px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
        }
        .btn-imprimir { background: #16a34a; color: #fff; }
        .btn-fechar { background: #334155; color: #e2e8f0; }
        .folha {
            max-width: 800px;
            margin: 24px auto 48px;
            padding: 32px 36px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,.15);
        }
        .cabecalho { display: flex; align-items: center; justify-content: space-between; gap: 16px; border-bottom: 2px solid #0f172a; padding-bottom: 14px; margin-bottom: 18px; }
        .cabecalho img { max-height: 48px; max-width: 180px; }
        .cabecalho h1 { margin: 0; font-size: 18px; }
        .cabecalho p { margin: 2px 0 0; font-size: 12px; color: #64748b; }
        .status { display: inline-block; margin-top: 4px; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: uppercase; background: #f1f5f9; color: #334155; }
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 14px 24px; margin-bottom: 22px; }
        .campo dt { font-size: 10px; text-transform: uppercase; color: #94a3b8; margin-bottom: 2px; }
        .campo dd { margin: 0; font-size: 14px; font-weight: 600; }
        h2 { margin: 0 0 10px; font-size: 13px; text-transform: uppercase; color: #475569; border-bottom: 1px solid #e2e8f0; padding-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 18px; }
        th, td { padding: 7px 8px; border: 1px solid #e2e8f0; text-align: left; }
        th { background: #f1f5f9; text-transform: uppercase; font-size: 10px; color: #475569; }
        td.valor, th.valor { text-align: right; white-space: nowrap; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        .resumo { margin-left: auto; width: 260px; font-size: 13px; }
        .resumo div { display: flex; justify-content: space-between; padding: 4px 0; }
        .resumo .total { border-top: 2px solid #0f172a; margin-top: 4px; padding-top: 8px; font-size: 16px; font-weight: 800; }
        .assinaturas { margin-top: 48px; display: grid; grid-template-columns: 1fr 1fr; gap: 40px; }
        .assinaturas div { border-top: 1px solid #94a3b8; padding-top: 6px; text-align: center; font-size: 11px; color: #64748b; }
        .rodape { margin-top: 24px; font-size: 10px; color: #94a3b8; text-align: center; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .folha { margin: 0; padding: 0; box-shadow: none; border-radius: 0; max-width: none; }
            @page { size: A4 portrait; margin: 14mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="btn-imprimir" onclick="window.print()">🖨 Imprimir ficha</button>
        <a href="{{ route('hospedagens.show', $hospedagem) }}" class="btn-fechar">Voltar</a>
    </div>

    <div class="folha">
        <div class="cabecalho">
            <div>
                <h1>Ficha de Hospedagem #{{ str_pad((string) $hospedagem->id, 6, '0', STR_PAD_LEFT) }}</h1>
                <p>{{ $empresa->nome ?? '' }}{{ $empresa?->cnpj ? ' — CNPJ '.$empresa->cnpj : '' }}</p>
                <span class="status">{{ str_replace('_', ' ', $hospedagem->status) }}</span>
            </div>
            @if ($empresa?->logoDataUri())
                <img src="{{ $empresa->logoDataUri() }}" alt="{{ $empresa->nome }}">
            @endif
        </div>

        <h2>Hóspede e quarto</h2>
        <dl class="grid">
            <div class="campo"><dt>Hóspede titular</dt><dd>{{ $hospedagem->cliente->nome }}</dd></div>
            <div class="campo"><dt>CPF</dt><dd>{{ $hospedagem->cliente->cpf ?? '—' }}</dd></div>
            <div class="campo"><dt>Quarto</dt><dd>{{ $hospedagem->quarto->numero }} — {{ $hospedagem->quarto->unidade->nome }}</dd></div>
            <div class="campo"><dt>Hóspedes</dt><dd>{{ $hospedagem->quantidade_adultos }} adulto(s), {{ $hospedagem->quantidade_criancas }} criança(s), {{ $hospedagem->quantidade_isentos }} isento(s)</dd></div>
            <div class="campo"><dt>Check-in previsto</dt><dd>{{ $hospedagem->data_checkin_prevista->format('d/m/Y') }}</dd></div>
            <div class="campo"><dt>Check-out previsto</dt><dd>{{ $hospedagem->data_checkout_prevista->format('d/m/Y') }}</dd></div>
            <div class="campo"><dt>Check-in real</dt><dd>{{ $hospedagem->data_checkin_real?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            <div class="campo"><dt>Check-out real</dt><dd>{{ $hospedagem->data_checkout_real?->format('d/m/Y H:i') ?? '—' }}</dd></div>
        </dl>

        @if ($hospedagem->observacoes)
            <h2>Observações</h2>
            <p style="margin: 0 0 18px; font-size: 13px;">{{ $hospedagem->observacoes }}</p>
        @endif

        <h2>Consumos durante a estadia</h2>
        <table>
            <thead><tr><th>Item</th><th class="valor">Qtd.</th><th class="valor">Unit.</th><th class="valor">Subtotal</th></tr></thead>
            <tbody>
                @forelse ($hospedagem->consumos as $consumo)
                    <tr>
                        <td>{{ $consumo->produto->nome ?? $consumo->descricao }}</td>
                        <td class="valor">{{ $consumo->quantidade }}</td>
                        <td class="valor">R$ {{ number_format($consumo->valor_unitario, 2, ',', '.') }}</td>
                        <td class="valor">R$ {{ number_format($consumo->subtotal, 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="text-align:center; color:#94a3b8;">Nenhum consumo lançado.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="resumo">
            <div><span>Diárias ({{ $noites }} noite(s) × R$ {{ number_format($hospedagem->valor_diaria, 2, ',', '.') }})</span> <strong>R$ {{ number_format($noites * $hospedagem->valor_diaria, 2, ',', '.') }}</strong></div>
            <div><span>Consumos</span> <strong>R$ {{ number_format($totalConsumos, 2, ',', '.') }}</strong></div>
            @if ($hospedagem->desconto > 0)
                <div><span>Desconto</span> <strong>- R$ {{ number_format($hospedagem->desconto, 2, ',', '.') }}</strong></div>
            @endif
            <div class="total"><span>{{ $hospedagem->estaFinalizado() ? 'Total pago' : 'Total estimado' }}</span> <span>R$ {{ number_format($total, 2, ',', '.') }}</span></div>
            @if ($hospedagem->estaFinalizado())
                <div style="margin-top:6px;"><span>Forma de pagamento</span> <strong>{{ ucfirst(str_replace('_', ' ', (string) $hospedagem->forma_pagamento)) }}</strong></div>
            @endif
        </div>

        <div class="assinaturas">
            <div>Assinatura do hóspede</div>
            <div>Assinatura da recepção{{ $hospedagem->registradoPor ? ' — '.$hospedagem->registradoPor->name : '' }}</div>
        </div>

        <p class="rodape">Documento gerado em {{ $geradoEm->format('d/m/Y H:i') }} — sem valor fiscal.</p>
    </div>
</body>
</html>
