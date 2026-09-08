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
        table { width: 100%; margin-bottom: 22px; border-collapse: collapse; page-break-inside: avoid; }
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
    <h1>Relatório Financeiro — {{ $mes->translatedFormat('F/Y') }}</h1>
    <p class="subtitulo">Gerado em {{ $geradoEm->format('d/m/Y H:i') }} · {{ $contratosAtivos }} contrato(s) ativo(s)</p>

    <table>
        <thead><tr><th>Indicador</th><th class="valor">Valor</th></tr></thead>
        <tbody>
            <tr><td>Previsto</td><td class="valor">R$ {{ number_format($resumo['previsto'], 2, ',', '.') }}</td></tr>
            <tr><td>Recebido</td><td class="valor">R$ {{ number_format($resumo['recebido'], 2, ',', '.') }}</td></tr>
            <tr><td>Pendente</td><td class="valor">R$ {{ number_format($resumo['pendente'], 2, ',', '.') }}</td></tr>
            <tr><td>Atrasado</td><td class="valor">R$ {{ number_format($resumo['atrasado'], 2, ',', '.') }}</td></tr>
            <tr><td>Vendas de produtos</td><td class="valor">R$ {{ number_format($resumo['vendas_produtos'], 2, ',', '.') }}</td></tr>
        </tbody>
    </table>

    <h2 style="font-size: 14px;">Inadimplência por unidade</h2>
    <table>
        <thead><tr><th>Unidade</th><th>Mensalidades em atraso</th><th class="valor">Valor total</th></tr></thead>
        <tbody>
            @forelse ($inadimplenciaPorUnidade as $linha)
                <tr>
                    <td>{{ $linha->nome }}</td>
                    <td>{{ $linha->total_mensalidades }}</td>
                    <td class="valor">R$ {{ number_format($linha->total_valor, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="3">Nenhuma inadimplência no período.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="rodape">Sistema de Gestão de Parque Aquático — documento gerado automaticamente.</p>
</body>
</html>
