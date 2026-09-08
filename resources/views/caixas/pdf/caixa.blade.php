<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 34px; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.45; color: #344054; }
        h1 { margin: 0 0 3px; font-size: 18px; color: #101828; }
        .subtitulo { margin: 0 0 20px; color: #667085; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        .badge-aberto { background: #d1fadf; color: #05603a; }
        .badge-fechado { background: #eaecf0; color: #344054; }
        h2 { margin: 22px 0 8px; font-size: 13px; color: #101828; }
        table { width: 100%; margin-bottom: 6px; border-collapse: collapse; page-break-inside: auto; }
        tr { page-break-inside: avoid; }
        th, td { padding: 7px 10px; border: 1px solid #eaecf0; text-align: left; vertical-align: middle; }
        th { background-color: #f9fafb; color: #475467; font-size: 9.5px; font-weight: bold; text-transform: uppercase; }
        tbody tr:nth-child(even) { background-color: #fcfcfd; }
        .valor { text-align: right; white-space: nowrap; }
        .entrada { color: #05603a; }
        .saida { color: #b32318; }
        .resumo td { border: none; padding: 4px 0; }
        .resumo .rotulo { color: #667085; }
        .resumo .total td { border-top: 1px solid #d0d5dd; padding-top: 8px; font-weight: bold; }
        .rodape { margin-top: 22px; padding-top: 10px; border-top: 1px solid #eaecf0; font-size: 9px; color: #98a2b3; }
        .cabecalho-logo { margin-bottom: 8px; }
        .cabecalho-logo img { max-height: 50px; max-width: 200px; }
    </style>
</head>
<body>
    @if ($empresa?->logoDataUri())
        <div class="cabecalho-logo"><img src="{{ $empresa->logoDataUri() }}" alt="{{ $empresa->nome }}"></div>
    @endif
    <h1>Caixa #{{ $caixa->id }} — {{ $caixa->unidade->nome }}</h1>
    <p class="subtitulo">
        <span class="badge {{ $caixa->estaAberto() ? 'badge-aberto' : 'badge-fechado' }}">{{ $caixa->estaAberto() ? 'Aberto' : 'Fechado' }}</span>
        · Terminal: <strong>{{ $caixa->terminal->nome ?? '—' }}</strong>
        · Aberto por {{ $caixa->usuarioAbertura->name }} em {{ $caixa->data_abertura->format('d/m/Y H:i') }}
        @if (! $caixa->estaAberto())
            · Fechado por {{ $caixa->usuarioFechamento?->name }} em {{ $caixa->data_fechamento->format('d/m/Y H:i') }}
        @endif
    </p>

    <table class="resumo">
        <tr><td class="rotulo" style="width: 200px;">Valor de abertura</td><td class="valor">R$ {{ number_format($caixa->valor_abertura, 2, ',', '.') }}</td></tr>
        <tr><td class="rotulo">Total de entradas</td><td class="valor entrada">+ R$ {{ number_format($entradas, 2, ',', '.') }}</td></tr>
        <tr><td class="rotulo">Total de saídas</td><td class="valor saida">- R$ {{ number_format($saidas, 2, ',', '.') }}</td></tr>
        @if (! $caixa->estaAberto())
            <tr class="total"><td>Saldo apurado pelo sistema</td><td class="valor">R$ {{ number_format($caixa->valor_fechamento_sistema, 2, ',', '.') }}</td></tr>
            <tr><td class="rotulo">Valor contado (informado)</td><td class="valor">R$ {{ number_format($caixa->valor_fechamento_informado, 2, ',', '.') }}</td></tr>
            <tr><td class="rotulo">Diferença</td><td class="valor {{ $caixa->diferenca < 0 ? 'saida' : 'entrada' }}">R$ {{ number_format($caixa->diferenca, 2, ',', '.') }}</td></tr>
        @else
            <tr class="total"><td>Saldo esperado até o momento</td><td class="valor">R$ {{ number_format($caixa->valor_abertura + $entradas - $saidas, 2, ',', '.') }}</td></tr>
        @endif
    </table>

    <h2>Movimentações ({{ $caixa->movimentacoes->count() }})</h2>
    <table>
        <thead>
            <tr><th>Data/hora</th><th>Categoria</th><th>Descrição</th><th>Forma pgto.</th><th>Usuário</th><th class="valor">Valor</th></tr>
        </thead>
        <tbody>
            @forelse ($caixa->movimentacoes as $mov)
                <tr>
                    <td>{{ $mov->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ ucfirst(str_replace('_', ' ', $mov->categoria)) }}</td>
                    <td>{{ $mov->descricao ?: '—' }}</td>
                    <td>{{ $mov->forma_pagamento ? ucfirst(str_replace('_', ' ', $mov->forma_pagamento)) : '—' }}</td>
                    <td>{{ $mov->usuario->name }}</td>
                    <td class="valor {{ $mov->tipo === 'saida' ? 'saida' : 'entrada' }}">{{ $mov->tipo === 'saida' ? '-' : '+' }} R$ {{ number_format($mov->valor, 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="6">Nenhuma movimentação registrada.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="rodape">Documento gerado automaticamente em {{ $geradoEm->format('d/m/Y H:i') }}.</p>
</body>
</html>
