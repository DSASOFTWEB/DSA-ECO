{{-- Resumo do fechamento mensal de NFC-e (PDF A4, dompdf: só tabelas). --}}
@php $moeda = fn ($v) => 'R$ '.number_format((float) $v, 2, ',', '.'); @endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28pt 32pt; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.5pt; color: #0f172a; }
        h1 { font-size: 15pt; margin: 0; }
        h2 { font-size: 10.5pt; margin: 16pt 0 6pt; border-bottom: 1px solid #cbd5e1; padding-bottom: 3pt; }
        .sub { color: #475569; margin-top: 2pt; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; text-align: left; font-size: 7.5pt; text-transform: uppercase; padding: 4pt; border-bottom: 1px solid #cbd5e1; }
        td { padding: 3.5pt 4pt; border-bottom: 1px solid #e2e8f0; }
        .r { text-align: right; }
        .cards td { border: 1px solid #cbd5e1; padding: 8pt; width: 25%; }
        .cards .rot { font-size: 7.5pt; color: #475569; }
        .cards .val { font-size: 13pt; font-weight: bold; }
        .alerta { margin-top: 10pt; padding: 6pt; border: 1px solid #fca5a5; background: #fef2f2; color: #991b1b; }
        .rodape { margin-top: 16pt; font-size: 7pt; color: #64748b; }
    </style>
</head>
<body>
    <h1>Fechamento de NFC-e — {{ $competencia->format('m/Y') }}</h1>
    <div class="sub">{{ $empresa->razao_social ?: $empresa->nome }} @if ($empresa->cnpj) · CNPJ {{ $empresa->cnpj }} @endif</div>

    <table class="cards" style="margin-top: 12pt">
        <tr>
            <td><div class="rot">NFC-e autorizadas</div><div class="val">{{ $resumo['quantidade'] }}</div></td>
            <td><div class="rot">Valor total</div><div class="val">{{ $moeda($resumo['valor_total']) }}</div></td>
            <td><div class="rot">Ticket médio</div><div class="val">{{ $moeda($resumo['ticket_medio']) }}</div></td>
            <td><div class="rot">Canceladas / não autorizadas</div><div class="val">{{ $resumo['canceladas'] }} / {{ $resumo['nao_autorizadas'] }}</div></td>
        </tr>
    </table>

    <h2>Por série</h2>
    <table>
        <tr><th>Série</th><th class="r">Notas</th><th class="r">Faixa de numeração</th><th class="r">Valor</th></tr>
        @forelse ($resumo['por_serie'] as $s)
            <tr><td>Série {{ $s['serie'] }}</td><td class="r">{{ $s['quantidade'] }}</td><td class="r">{{ $s['primeiro'] }} a {{ $s['ultimo'] }}</td><td class="r">{{ $moeda($s['valor']) }}</td></tr>
        @empty
            <tr><td colspan="4">Nenhuma NFC-e autorizada no mês.</td></tr>
        @endforelse
    </table>

    <h2>Por forma de pagamento</h2>
    <table>
        <tr><th>Forma</th><th class="r">Notas</th><th class="r">Valor</th></tr>
        @foreach ($resumo['por_forma'] as $f)
            <tr><td>{{ $f['forma'] }}</td><td class="r">{{ $f['quantidade'] }}</td><td class="r">{{ $moeda($f['valor']) }}</td></tr>
        @endforeach
    </table>

    <h2>Por dia</h2>
    <table>
        <tr><th>Dia</th><th class="r">Notas</th><th class="r">Valor</th></tr>
        @foreach ($resumo['por_dia'] as $dia)
            <tr><td>{{ \Illuminate\Support\Carbon::parse($dia['dia'])->format('d/m/Y') }}</td><td class="r">{{ $dia['quantidade'] }}</td><td class="r">{{ $moeda($dia['valor']) }}</td></tr>
        @endforeach
    </table>

    <h2>Notas do mês</h2>
    <table>
        <tr><th>Série</th><th>Número</th><th>Data</th><th>Chave de acesso</th><th>Situação</th><th class="r">Valor</th></tr>
        @foreach ($notas as $n)
            <tr>
                <td>{{ $n->serie }}</td>
                <td>{{ $n->numero }}</td>
                <td>{{ ($n->autorizado_em ?? $n->created_at)->format('d/m/Y H:i') }}</td>
                <td style="font-size: 6.5pt">{{ $n->chave }}</td>
                <td>{{ ucfirst((string) $n->status) }}</td>
                <td class="r">{{ $moeda($n->valor_total) }}</td>
            </tr>
        @endforeach
    </table>

    @if ($falhas)
        <div class="alerta"><strong>Atenção:</strong> {{ implode(' | ', $falhas) }}</div>
    @endif

    <div class="rodape">Gerado em {{ $geradoEm->format('d/m/Y H:i') }}. XMLs na pasta XML/ e DANFEs na pasta PDF/ deste pacote.</div>
</body>
</html>
