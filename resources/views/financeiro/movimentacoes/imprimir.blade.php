<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Movimentações · {{ $empresa->nome ?? 'Parque Aquático' }}</title>
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
            max-width: 1100px;
            margin: 24px auto 48px;
            padding: 28px 32px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,.15);
        }
        .cabecalho { display: flex; align-items: center; justify-content: space-between; gap: 16px; border-bottom: 2px solid #0f172a; padding-bottom: 14px; margin-bottom: 16px; }
        .cabecalho img { max-height: 48px; max-width: 180px; }
        .cabecalho h1 { margin: 0; font-size: 18px; }
        .cabecalho p { margin: 2px 0 0; font-size: 12px; color: #64748b; }
        .filtros { margin-bottom: 18px; font-size: 12px; color: #475569; }
        .filtros span { display: inline-block; margin-right: 16px; margin-bottom: 4px; }
        .filtros strong { color: #0f172a; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { padding: 7px 8px; border: 1px solid #e2e8f0; text-align: left; }
        th { background: #f1f5f9; text-transform: uppercase; font-size: 10px; color: #475569; }
        td.valor, th.valor { text-align: right; white-space: nowrap; }
        tbody tr:nth-child(even) { background: #f8fafc; }
        .entrada { color: #16a34a; font-weight: 600; }
        .saida { color: #dc2626; font-weight: 600; }
        .estornada { color: #b45309; font-size: 10px; }
        tfoot td { font-weight: 700; background: #f1f5f9; }
        .rodape { margin-top: 18px; font-size: 10px; color: #94a3b8; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .folha { margin: 0; padding: 0; box-shadow: none; border-radius: 0; max-width: none; }
            @page { size: A4 landscape; margin: 12mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" class="btn-imprimir" onclick="window.print()">🖨 Imprimir / Gerar PDF</button>
        <a href="{{ route('movimentacoes.index', request()->query()) }}" class="btn-fechar">Voltar</a>
    </div>

    <div class="folha">
        <div class="cabecalho">
            <div>
                <h1>Relatório de Movimentações</h1>
                <p>{{ $empresa->nome ?? '' }}{{ $empresa?->cnpj ? ' — CNPJ '.$empresa->cnpj : '' }}</p>
            </div>
            @if ($empresa?->logoDataUri())
                <img src="{{ $empresa->logoDataUri() }}" alt="{{ $empresa->nome }}">
            @endif
        </div>

        <div class="filtros">
            <span><strong>Período:</strong> {{ $filtros['dataInicial'] ? \Illuminate\Support\Carbon::parse($filtros['dataInicial'])->format('d/m/Y') : 'início' }} até {{ $filtros['dataFinal'] ? \Illuminate\Support\Carbon::parse($filtros['dataFinal'])->format('d/m/Y') : 'hoje' }}</span>
            @if ($filtros['caixa'])
                <span><strong>Caixa/terminal:</strong> {{ $filtros['caixa']->terminal->nome ?? ('Caixa #'.$filtros['caixa']->id) }}</span>
            @endif
            @if ($filtros['tipo'])
                <span><strong>Tipo:</strong> {{ $filtros['tipo'] === 'entrada' ? 'Entrada' : 'Saída' }}</span>
            @endif
            @if ($filtros['categoria'])
                <span><strong>Categoria:</strong> {{ \App\Support\Financeiro::labelCategoria($filtros['tipo'] ?: 'entrada', $filtros['categoria']) }}</span>
            @endif
            @if ($filtros['formaPagamento'])
                <span><strong>Forma de pagamento:</strong> {{ \App\Support\Financeiro::labelFormaPagamento($filtros['formaPagamento']) }}</span>
            @endif
            @if ($filtros['usuario'])
                <span><strong>Usuário:</strong> {{ $filtros['usuario']->name }}</span>
            @endif
            <span><strong>Gerado em:</strong> {{ $geradoEm->format('d/m/Y H:i') }}</span>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Caixa/terminal</th>
                    <th>Categoria</th>
                    <th>Descrição</th>
                    <th>Forma</th>
                    <th>Usuário</th>
                    <th class="valor">Valor</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movimentacoes as $mov)
                    <tr>
                        <td>{{ $mov->created_at->format('d/m/Y H:i') }}</td>
                        <td>{{ $mov->caixa->terminal->nome ?? $mov->caixa->unidade->nome }}</td>
                        <td>
                            {{ \App\Support\Financeiro::labelCategoria($mov->tipo, $mov->categoria) }}
                            @if ($mov->estaEstornada())
                                <div class="estornada">Estornada</div>
                            @endif
                        </td>
                        <td>{{ $mov->descricao }}</td>
                        <td>{{ \App\Support\Financeiro::labelFormaPagamento($mov->forma_pagamento) ?? '—' }}</td>
                        <td>{{ $mov->usuario->name }}</td>
                        <td class="valor {{ $mov->tipo === 'saida' ? 'saida' : 'entrada' }}">{{ $mov->tipo === 'saida' ? '-' : '+' }} R$ {{ number_format($mov->valor, 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align:center; color:#94a3b8; padding:24px;">Nenhuma movimentação encontrada para esses filtros.</td></tr>
                @endforelse
            </tbody>
            @if ($movimentacoes->isNotEmpty())
                @php
                    $totalEntradas = $movimentacoes->where('tipo', 'entrada')->sum('valor');
                    $totalSaidas = $movimentacoes->where('tipo', 'saida')->sum('valor');
                @endphp
                <tfoot>
                    <tr>
                        <td colspan="6">Total de entradas</td>
                        <td class="valor entrada">R$ {{ number_format($totalEntradas, 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td colspan="6">Total de saídas</td>
                        <td class="valor saida">R$ {{ number_format($totalSaidas, 2, ',', '.') }}</td>
                    </tr>
                    <tr>
                        <td colspan="6">Saldo do período (entradas − saídas)</td>
                        <td class="valor">R$ {{ number_format($totalEntradas - $totalSaidas, 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>

        <p class="rodape">
            {{ count($movimentacoes) }} lançamento(s){{ count($movimentacoes) >= 1000 ? ' (limitado às 1000 primeiras — refine o período/filtros pra ver o restante)' : '' }}.
            Sistema de Gestão de Parque Aquático — documento gerado automaticamente, sem valor fiscal.
        </p>
    </div>
</body>
</html>
