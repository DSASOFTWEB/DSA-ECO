<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 34px; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.45; color: #344054; }
        h1 { margin: 0 0 3px; font-size: 20px; color: #101828; }
        h2 { margin: 22px 0 8px; font-size: 14px; color: #101828; }
        .subtitulo { margin: 0 0 22px; color: #667085; }
        table { width: 100%; margin-bottom: 22px; border-collapse: collapse; page-break-inside: auto; }
        tr { page-break-inside: avoid; }
        th, td { padding: 8px 10px; border: 1px solid #eaecf0; text-align: left; vertical-align: middle; }
        th { background-color: #f9fafb; color: #475467; font-size: 10px; font-weight: bold; text-transform: uppercase; }
        tbody tr:nth-child(even) { background-color: #fcfcfd; }
        .valor { text-align: right; white-space: nowrap; }
        .rodape { margin-top: 28px; padding-top: 10px; border-top: 1px solid #eaecf0; font-size: 9px; color: #98a2b3; }
        .cabecalho-logo { margin-bottom: 8px; }
        .cabecalho-logo img { max-height: 50px; max-width: 200px; }
    </style>
</head>
<body>
    @if ($empresa?->logoDataUri())
        <div class="cabecalho-logo"><img src="{{ $empresa->logoDataUri() }}" alt="{{ $empresa->nome }}"></div>
    @endif
    <h1>Relatório da Pousada — {{ $inicio->format('d/m/Y') }} a {{ $fim->format('d/m/Y') }}</h1>
    <p class="subtitulo">Gerado em {{ $geradoEm->format('d/m/Y H:i') }}</p>

    <table>
        <thead><tr><th>Indicador</th><th class="valor">Valor</th></tr></thead>
        <tbody>
            <tr><td>Estadias finalizadas</td><td class="valor">{{ $relatorio['total_estadias'] }}</td></tr>
            <tr><td>Total faturado</td><td class="valor">R$ {{ number_format($relatorio['total_faturado'], 2, ',', '.') }}</td></tr>
            <tr><td>Ticket médio</td><td class="valor">R$ {{ number_format($relatorio['ticket_medio'], 2, ',', '.') }}</td></tr>
        </tbody>
    </table>

    <h2>Por quarto</h2>
    <table>
        <thead><tr><th>Quarto</th><th>Estadias</th><th class="valor">Total faturado</th></tr></thead>
        <tbody>
            @forelse ($relatorio['por_quarto'] as $linha)
                <tr>
                    <td>{{ $linha['quarto']->numero }}</td>
                    <td>{{ $linha['total_estadias'] }}</td>
                    <td class="valor">R$ {{ number_format($linha['total_faturado'], 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="3">Sem dados no período.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Estadias no período</h2>
    <table>
        <thead><tr><th>Quarto</th><th>Hóspede</th><th>Check-out</th><th>Noites</th><th class="valor">Total</th></tr></thead>
        <tbody>
            @forelse ($relatorio['hospedagens'] as $hospedagem)
                <tr>
                    <td>{{ $hospedagem->quarto->numero }}</td>
                    <td>{{ $hospedagem->cliente->nome }}</td>
                    <td>{{ $hospedagem->data_checkout_real->format('d/m/Y H:i') }}</td>
                    <td>{{ max(1, $hospedagem->data_checkin_real->diffInDays($hospedagem->data_checkout_real)) }}</td>
                    <td class="valor">R$ {{ number_format($hospedagem->valor_total, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Nenhuma estadia finalizada nesse período.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="rodape">Sistema de Gestão de Parque Aquático — documento gerado automaticamente.</p>
</body>
</html>
