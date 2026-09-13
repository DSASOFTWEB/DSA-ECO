<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 34px; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 10px; line-height: 1.45; color: #344054; }
        h1 { margin: 0 0 3px; font-size: 18px; color: #101828; }
        .subtitulo { margin: 0 0 22px; color: #667085; }
        table { width: 100%; margin-bottom: 22px; border-collapse: collapse; page-break-inside: auto; }
        tr { page-break-inside: avoid; }
        th, td { padding: 7px 8px; border: 1px solid #eaecf0; text-align: left; vertical-align: middle; }
        th { background-color: #f9fafb; color: #475467; font-size: 9px; font-weight: bold; text-transform: uppercase; }
        tbody tr:nth-child(even) { background-color: #fcfcfd; }
        tfoot td { font-weight: bold; background-color: #f9fafb; }
        .valor { text-align: right; white-space: nowrap; }
        .entrada { color: #067647; }
        .saida { color: #c01048; }
        .rodape { margin-top: 28px; padding-top: 10px; border-top: 1px solid #eaecf0; font-size: 9px; color: #98a2b3; }
        .cabecalho-logo { margin-bottom: 8px; }
        .cabecalho-logo img { max-height: 50px; max-width: 200px; }
    </style>
</head>
<body>
    @if ($empresa?->logoDataUri())
        <div class="cabecalho-logo"><img src="{{ $empresa->logoDataUri() }}" alt="{{ $empresa->nome }}"></div>
    @endif

    @php
        $titulos = [
            'consolidado' => 'Consolidado de Todos os Caixas',
            'por_caixa' => 'Movimentação por Caixa',
            'entradas' => 'Entradas por Período',
            'saidas' => 'Saídas por Período',
            'fluxo' => 'Fluxo de Caixa',
        ];
    @endphp
    <h1>{{ $titulos[$tipo] ?? 'Relatório de Movimentações' }} — {{ $inicio->format('d/m/Y') }} a {{ $fim->format('d/m/Y') }}</h1>
    <p class="subtitulo">Gerado em {{ $geradoEm->format('d/m/Y H:i') }}</p>

    @if (in_array($tipo, ['consolidado', 'por_caixa']))
        <table>
            <thead>
                <tr>
                    <th>Caixa/terminal</th>
                    <th class="valor">Saldo inicial</th>
                    <th class="valor">Entradas</th>
                    <th class="valor">Saídas</th>
                    @if ($tipo === 'por_caixa')
                        <th class="valor">Transf. recebidas</th>
                        <th class="valor">Transf. enviadas</th>
                        <th class="valor">Ajustes</th>
                    @endif
                    <th class="valor">Saldo final</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($porCaixa as $linha)
                    <tr>
                        <td>{{ $linha['caixa']->terminal->nome ?? $linha['caixa']->unidade->nome }}</td>
                        <td class="valor">R$ {{ number_format($linha['saldo_inicial'], 2, ',', '.') }}</td>
                        <td class="valor entrada">R$ {{ number_format($linha['entradas'], 2, ',', '.') }}</td>
                        <td class="valor saida">R$ {{ number_format($linha['saidas'], 2, ',', '.') }}</td>
                        @if ($tipo === 'por_caixa')
                            <td class="valor">R$ {{ number_format($linha['transferencias_recebidas'], 2, ',', '.') }}</td>
                            <td class="valor">R$ {{ number_format($linha['transferencias_enviadas'], 2, ',', '.') }}</td>
                            <td class="valor">R$ {{ number_format($linha['ajustes'], 2, ',', '.') }}</td>
                        @endif
                        <td class="valor">R$ {{ number_format($linha['saldo_final'], 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $tipo === 'por_caixa' ? 8 : 5 }}">Nenhum caixa no período.</td></tr>
                @endforelse
            </tbody>
            @if ($porCaixa->isNotEmpty())
                <tfoot>
                    <tr>
                        <td>Total geral</td>
                        <td class="valor">R$ {{ number_format($porCaixa->sum('saldo_inicial'), 2, ',', '.') }}</td>
                        <td class="valor">R$ {{ number_format($porCaixa->sum('entradas'), 2, ',', '.') }}</td>
                        <td class="valor">R$ {{ number_format($porCaixa->sum('saidas'), 2, ',', '.') }}</td>
                        @if ($tipo === 'por_caixa')
                            <td class="valor">R$ {{ number_format($porCaixa->sum('transferencias_recebidas'), 2, ',', '.') }}</td>
                            <td class="valor">R$ {{ number_format($porCaixa->sum('transferencias_enviadas'), 2, ',', '.') }}</td>
                            <td class="valor">R$ {{ number_format($porCaixa->sum('ajustes'), 2, ',', '.') }}</td>
                        @endif
                        <td class="valor">R$ {{ number_format($porCaixa->sum('saldo_final'), 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    @endif

    @if (in_array($tipo, ['entradas', 'saidas']))
        <table>
            <thead>
                <tr><th>Data</th><th>Descrição</th><th>Categoria</th><th>Caixa/terminal</th><th>Usuário</th><th class="valor">Valor</th></tr>
            </thead>
            <tbody>
                @forelse ($movimentacoes['itens'] as $mov)
                    <tr>
                        <td>{{ $mov->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $mov->descricao }}</td>
                        <td>{{ \App\Support\Financeiro::labelCategoria($mov->tipo, $mov->categoria) }}</td>
                        <td>{{ $mov->caixa->terminal->nome ?? $mov->caixa->unidade->nome }}</td>
                        <td>{{ $mov->usuario->name }}</td>
                        <td class="valor">R$ {{ number_format($mov->valor, 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6">Nenhuma movimentação no período.</td></tr>
                @endforelse
            </tbody>
            @if ($movimentacoes['itens']->isNotEmpty())
                <tfoot><tr><td colspan="5">Total</td><td class="valor">R$ {{ number_format($movimentacoes['total'], 2, ',', '.') }}</td></tr></tfoot>
            @endif
        </table>
    @endif

    @if ($tipo === 'fluxo')
        <table>
            <thead><tr><th>Data</th><th class="valor">Entradas</th><th class="valor">Saídas</th><th class="valor">Saldo do dia</th></tr></thead>
            <tbody>
                @forelse ($fluxo as $dia)
                    <tr>
                        <td>{{ $dia['data']->format('d/m/Y') }}</td>
                        <td class="valor entrada">R$ {{ number_format($dia['entradas'], 2, ',', '.') }}</td>
                        <td class="valor saida">R$ {{ number_format($dia['saidas'], 2, ',', '.') }}</td>
                        <td class="valor">R$ {{ number_format($dia['saldo'], 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">Período inválido.</td></tr>
                @endforelse
            </tbody>
            @if ($fluxo->isNotEmpty())
                <tfoot>
                    <tr>
                        <td>Total do período</td>
                        <td class="valor">R$ {{ number_format($fluxo->sum('entradas'), 2, ',', '.') }}</td>
                        <td class="valor">R$ {{ number_format($fluxo->sum('saidas'), 2, ',', '.') }}</td>
                        <td class="valor">R$ {{ number_format($fluxo->sum('saldo'), 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    @endif

    <p class="rodape">Sistema de Gestão de Parque Aquático — documento gerado automaticamente.</p>
</body>
</html>
