# Agente de impressao ESC/POS - DSA
# Recebe o cupom do navegador (POST http://127.0.0.1:9110/print) e envia os
# bytes ESC/POS direto para a impressora termica (modo RAW, sem driver
# grafico: texto 100% preto e nitido). So escuta na propria maquina.
#
# Rotas:  GET /status   GET /impressoras   POST /print {payload_base64, ...}
# Config: config.json na mesma pasta (porta, impressora, origens).

param([string]$Config = (Join-Path $PSScriptRoot 'config.json'))

$ErrorActionPreference = 'Stop'
$Versao = '1.0.0'
$Log = Join-Path $PSScriptRoot 'agente.log'

function Escrever-Log([string]$texto) {
    try {
        if ((Test-Path $Log) -and (Get-Item $Log).Length -gt 1MB) { Remove-Item $Log -Force }
        Add-Content -Path $Log -Value ("[{0}] {1}" -f (Get-Date -Format 'yyyy-MM-dd HH:mm:ss'), $texto)
    } catch {}
}

$cfg = Get-Content $Config -Raw | ConvertFrom-Json
$porta = if ($cfg.porta) { [int]$cfg.porta } else { 9110 }
$origens = @($cfg.origens | Where-Object { $_ })

Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;

public static class DsaRawPrinter {
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
    public class DOCINFO {
        [MarshalAs(UnmanagedType.LPWStr)] public string pDocName;
        [MarshalAs(UnmanagedType.LPWStr)] public string pOutputFile;
        [MarshalAs(UnmanagedType.LPWStr)] public string pDataType;
    }

    [DllImport("winspool.drv", CharSet = CharSet.Unicode, SetLastError = true)]
    static extern bool OpenPrinter(string nome, out IntPtr h, IntPtr padrao);
    [DllImport("winspool.drv", SetLastError = true)]
    static extern bool ClosePrinter(IntPtr h);
    [DllImport("winspool.drv", CharSet = CharSet.Unicode, SetLastError = true)]
    static extern int StartDocPrinter(IntPtr h, int nivel, [In] DOCINFO di);
    [DllImport("winspool.drv", SetLastError = true)]
    static extern bool EndDocPrinter(IntPtr h);
    [DllImport("winspool.drv", SetLastError = true)]
    static extern bool StartPagePrinter(IntPtr h);
    [DllImport("winspool.drv", SetLastError = true)]
    static extern bool EndPagePrinter(IntPtr h);
    [DllImport("winspool.drv", SetLastError = true)]
    static extern bool WritePrinter(IntPtr h, byte[] dados, int tamanho, out int escritos);

    public static void Enviar(string impressora, byte[] dados) {
        IntPtr h;
        if (!OpenPrinter(impressora, out h, IntPtr.Zero))
            throw new Exception("Impressora nao encontrada no Windows: " + impressora);
        try {
            DOCINFO di = new DOCINFO();
            di.pDocName = "Cupom DSA";
            di.pDataType = "RAW";
            if (StartDocPrinter(h, 1, di) == 0)
                throw new Exception("A impressora recusou o trabalho (erro " + Marshal.GetLastWin32Error() + ").");
            try {
                StartPagePrinter(h);
                int escritos;
                if (!WritePrinter(h, dados, dados.Length, out escritos))
                    throw new Exception("Falha ao enviar para a impressora (erro " + Marshal.GetLastWin32Error() + ").");
                EndPagePrinter(h);
            } finally {
                EndDocPrinter(h);
            }
        } finally {
            ClosePrinter(h);
        }
    }
}
"@

# Impressora de rede (ex.: tcp://192.168.0.50:9100) ou nome no Windows.
function Imprimir([byte[]]$dados) {
    $alvo = [string]$cfg.impressora
    if (-not $alvo) { throw 'Nenhuma impressora configurada. Rode o INSTALAR.bat de novo.' }

    if ($alvo -match '^tcp://([^:/]+)(?::(\d+))?') {
        $cliente = New-Object System.Net.Sockets.TcpClient
        try {
            $cliente.Connect($Matches[1], $(if ($Matches[2]) { [int]$Matches[2] } else { 9100 }))
            $s = $cliente.GetStream()
            $s.Write($dados, 0, $dados.Length)
            $s.Flush()
        } finally { $cliente.Close() }
        return
    }

    [DsaRawPrinter]::Enviar($alvo, $dados)
}

function Listar-Impressoras {
    Add-Type -AssemblyName System.Drawing
    return @([System.Drawing.Printing.PrinterSettings]::InstalledPrinters)
}

# So responde para o site do sistema (e para testes locais).
function Origem-Permitida([string]$origem) {
    if (-not $origem) { return $true }
    if ($origem -match '^https?://(localhost|127\.0\.0\.1)(:\d+)?$') { return $true }
    return $origens -contains $origem.TrimEnd('/')
}

function Responder($stream, [int]$codigo, [string]$texto, $corpo, [string]$origem) {
    $json = if ($null -ne $corpo) { $corpo | ConvertTo-Json -Compress -Depth 4 } else { '' }
    $bytesCorpo = [System.Text.Encoding]::UTF8.GetBytes($json)
    $cab = "HTTP/1.1 $codigo $texto`r`n" +
        "Content-Type: application/json; charset=utf-8`r`n" +
        "Content-Length: $($bytesCorpo.Length)`r`n" +
        "Access-Control-Allow-Origin: $(if ($origem) { $origem } else { '*' })`r`n" +
        "Access-Control-Allow-Methods: GET, POST, OPTIONS`r`n" +
        "Access-Control-Allow-Headers: Content-Type`r`n" +
        "Access-Control-Allow-Private-Network: true`r`n" +
        "Vary: Origin`r`n" +
        "Connection: close`r`n`r`n"
    $bytesCab = [System.Text.Encoding]::ASCII.GetBytes($cab)
    $stream.Write($bytesCab, 0, $bytesCab.Length)
    if ($bytesCorpo.Length) { $stream.Write($bytesCorpo, 0, $bytesCorpo.Length) }
    $stream.Flush()
}

# Le uma requisicao HTTP simples (cabecalhos + corpo pelo Content-Length).
function Ler-Requisicao($stream) {
    $buffer = New-Object byte[] 65536
    $acumulado = New-Object System.IO.MemoryStream
    $fimCabecalho = -1
    while ($fimCabecalho -lt 0) {
        $n = $stream.Read($buffer, 0, $buffer.Length)
        if ($n -le 0) { return $null }
        $acumulado.Write($buffer, 0, $n)
        $texto = [System.Text.Encoding]::ASCII.GetString($acumulado.ToArray())
        $fimCabecalho = $texto.IndexOf("`r`n`r`n")
        if ($acumulado.Length -gt 1MB) { return $null }
    }

    $cabecalho = $texto.Substring(0, $fimCabecalho)
    $linhas = $cabecalho -split "`r`n"
    $partes = $linhas[0] -split ' '
    $hs = @{}
    foreach ($l in ($linhas | Select-Object -Skip 1)) {
        $i = $l.IndexOf(':')
        if ($i -gt 0) { $hs[$l.Substring(0, $i).Trim().ToLower()] = $l.Substring($i + 1).Trim() }
    }

    $tamanho = if ($hs['content-length']) { [int]$hs['content-length'] } else { 0 }
    if ($tamanho -gt 4MB) { throw 'Cupom grande demais.' }
    $todos = $acumulado.ToArray()
    $inicio = $fimCabecalho + 4
    while (($todos.Length - $inicio) -lt $tamanho) {
        $n = $stream.Read($buffer, 0, $buffer.Length)
        if ($n -le 0) { break }
        $acumulado.Write($buffer, 0, $n)
        $todos = $acumulado.ToArray()
    }
    $corpo = if ($tamanho -gt 0) { [System.Text.Encoding]::UTF8.GetString($todos, $inicio, [Math]::Min($tamanho, $todos.Length - $inicio)) } else { '' }

    return @{ metodo = $partes[0]; caminho = ($partes[1] -split '\?')[0]; cabecalhos = $hs; corpo = $corpo }
}

$ouvinte = New-Object System.Net.Sockets.TcpListener([System.Net.IPAddress]::Loopback, $porta)
try {
    $ouvinte.Start()
} catch {
    Escrever-Log "Porta $porta ja em uso (o agente provavelmente ja esta rodando)."
    exit 0
}
Escrever-Log "Agente $Versao iniciado na porta $porta - impressora: $($cfg.impressora)"

while ($true) {
    $cliente = $ouvinte.AcceptTcpClient()
    try {
        $cliente.ReceiveTimeout = 10000
        $stream = $cliente.GetStream()
        $req = Ler-Requisicao $stream
        if ($null -eq $req) { continue }
        $origem = [string]$req.cabecalhos['origin']

        if (-not (Origem-Permitida $origem)) {
            Escrever-Log "Bloqueado: origem $origem"
            Responder $stream 403 'Forbidden' @{ ok = $false; erro = "Site nao autorizado neste agente: $origem" } $origem
            continue
        }

        switch -Regex ("$($req.metodo) $($req.caminho)") {
            '^OPTIONS ' { Responder $stream 204 'No Content' $null $origem; break }
            '^GET /(status)?$' {
                Responder $stream 200 'OK' @{ ok = $true; agente = 'dsa-escpos'; versao = $Versao; impressora = [string]$cfg.impressora } $origem
                break
            }
            '^GET /impressoras$' { Responder $stream 200 'OK' @{ ok = $true; impressoras = (Listar-Impressoras) } $origem; break }
            '^POST /print$' {
                try {
                    $dados = $req.corpo | ConvertFrom-Json
                    $bytes = [Convert]::FromBase64String([string]$dados.payload_base64)
                    Imprimir $bytes
                    Escrever-Log "Impresso: $($bytes.Length) bytes"
                    Responder $stream 200 'OK' @{ ok = $true; bytes = $bytes.Length } $origem
                } catch {
                    # Erro do .NET chega embrulhado ("Excecao ao chamar..."): mostra o de dentro.
                    $erro = $_.Exception
                    while ($erro.InnerException) { $erro = $erro.InnerException }
                    Escrever-Log "Erro ao imprimir: $($erro.Message)"
                    Responder $stream 500 'Internal Server Error' @{ ok = $false; erro = $erro.Message } $origem
                }
                break
            }
            default { Responder $stream 404 'Not Found' @{ ok = $false; erro = 'Rota inexistente' } $origem }
        }
    } catch {
        Escrever-Log "Falha na conexao: $($_.Exception.Message)"
    } finally {
        $cliente.Close()
    }
}
