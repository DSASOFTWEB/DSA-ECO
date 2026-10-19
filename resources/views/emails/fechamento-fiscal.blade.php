<!DOCTYPE html>
<html lang="pt-BR">
<body style="margin:0;padding:24px;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;padding:24px">
        <h1 style="margin:0 0 4px;font-size:18px">Fechamento de NFC-e — {{ $fechamento->rotuloCompetencia() }}</h1>
        <p style="margin:0 0 16px;color:#475569">{{ $empresa->razao_social ?: $empresa->nome }}@if ($empresa->cnpj) · CNPJ {{ $empresa->cnpj }}@endif</p>

        @if ($mensagem)
            <p style="white-space:pre-line">{{ $mensagem }}</p>
        @endif

        <table style="width:100%;border-collapse:collapse;margin:12px 0">
            <tr><td style="padding:6px 0;color:#475569">NFC-e autorizadas</td><td style="padding:6px 0;text-align:right;font-weight:bold">{{ $fechamento->quantidade }}</td></tr>
            <tr><td style="padding:6px 0;color:#475569">Valor total</td><td style="padding:6px 0;text-align:right;font-weight:bold">R$ {{ number_format((float) $fechamento->valor_total, 2, ',', '.') }}</td></tr>
        </table>

        <p>O pacote <strong>{{ $fechamento->arquivo_nome }}</strong> traz os XMLs, os DANFEs em PDF e o resumo (PDF e planilha).
            @if ($anexado) Ele está anexado a este e-mail. @else O arquivo é grande para anexo — baixe pelo link abaixo. @endif
        </p>

        <p style="text-align:center;margin:20px 0">
            <a href="{{ $linkDownload }}" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:bold">Baixar pacote (link válido por 7 dias)</a>
        </p>
    </div>
</body>
</html>
