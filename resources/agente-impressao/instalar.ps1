# Instala o agente de impressao ESC/POS na maquina do caixa:
# escolhe a impressora, copia os arquivos para a pasta do usuario e deixa
# o agente iniciando sozinho (oculto) junto com o Windows.

$ErrorActionPreference = 'Stop'
$origem = $PSScriptRoot
$destino = Join-Path $env:LOCALAPPDATA 'DSA-AgenteImpressao'
$inicializar = Join-Path ([Environment]::GetFolderPath('Startup')) 'DSA Agente de Impressao.vbs'

Write-Host ''
Write-Host '=== Agente de impressao 80mm - DSA ===' -ForegroundColor Cyan
Write-Host ''

# Para uma versao antiga que esteja rodando.
Get-CimInstance Win32_Process -Filter "Name = 'powershell.exe'" |
    Where-Object { $_.CommandLine -like '*DSA-AgenteImpressao*agente.ps1*' } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

$cfg = Get-Content (Join-Path $origem 'config.json') -Raw | ConvertFrom-Json

Add-Type -AssemblyName System.Drawing
$lista = @([System.Drawing.Printing.PrinterSettings]::InstalledPrinters)

Write-Host 'Impressoras instaladas neste computador:'
for ($i = 0; $i -lt $lista.Count; $i++) { Write-Host ("  [{0}] {1}" -f ($i + 1), $lista[$i]) }
Write-Host '  [R] Impressora de rede (IP, porta 9100)'
Write-Host ''

$escolha = Read-Host 'Digite o numero da impressora termica (ou R)'
if ($escolha -match '^[Rr]$') {
    $ip = Read-Host 'IP da impressora (ex.: 192.168.0.50)'
    $impressora = "tcp://$($ip.Trim()):9100"
} elseif ($escolha -match '^\d+$' -and [int]$escolha -ge 1 -and [int]$escolha -le $lista.Count) {
    $impressora = $lista[[int]$escolha - 1]
} else {
    Write-Host 'Opcao invalida. Rode o INSTALAR.bat de novo.' -ForegroundColor Red
    exit 1
}

New-Item -ItemType Directory -Force -Path $destino | Out-Null
Copy-Item (Join-Path $origem 'agente.ps1') $destino -Force
Copy-Item (Join-Path $origem 'desinstalar.ps1') $destino -Force -ErrorAction SilentlyContinue

$cfg | Add-Member -NotePropertyName impressora -NotePropertyValue $impressora -Force
$cfg | ConvertTo-Json -Depth 4 | Set-Content -Path (Join-Path $destino 'config.json') -Encoding UTF8

# Inicia oculto com o Windows (o .vbs evita a janela preta do PowerShell).
$agente = Join-Path $destino 'agente.ps1'
$vbs = "CreateObject(""WScript.Shell"").Run ""powershell.exe -NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File """"$agente"""""", 0, False"
Set-Content -Path $inicializar -Value $vbs -Encoding ASCII

Start-Process wscript.exe -ArgumentList "`"$inicializar`""
Start-Sleep -Seconds 3

try {
    $status = Invoke-RestMethod -Uri ("http://127.0.0.1:{0}/status" -f $cfg.porta) -TimeoutSec 5
    Write-Host ''
    Write-Host ("Agente funcionando! Impressora: {0}" -f $status.impressora) -ForegroundColor Green
} catch {
    Write-Host ''
    Write-Host 'O agente nao respondeu. Veja o arquivo agente.log em:' -ForegroundColor Yellow
    Write-Host "  $destino"
    exit 1
}

$teste = Read-Host 'Imprimir uma pagina de teste agora? (S/N)'
if ($teste -match '^[Ss]') {
    $esc = [char]27; $gs = [char]29
    $texto = "$esc@$esc" + 'a' + [char]1 + "$esc" + 'E' + [char]1 + "AGENTE DE IMPRESSAO DSA`n$esc" + 'E' + [char]0 +
        "Teste OK - $(Get-Date -Format 'dd/MM/yyyy HH:mm')`n`nSe este texto saiu nitido,`no sistema ja pode imprimir`nos cupons por aqui.`n`n`n`n$gs" + 'V' + [char]0
    $b64 = [Convert]::ToBase64String([System.Text.Encoding]::GetEncoding(850).GetBytes($texto))
    try {
        Invoke-RestMethod -Uri ("http://127.0.0.1:{0}/print" -f $cfg.porta) -Method Post -ContentType 'application/json' -Body (@{ payload_base64 = $b64 } | ConvertTo-Json) | Out-Null
        Write-Host 'Teste enviado para a impressora.' -ForegroundColor Green
    } catch {
        Write-Host ("Falha no teste: {0}" -f $_.Exception.Message) -ForegroundColor Red
    }
}

Write-Host ''
Write-Host 'Pronto. No sistema: Minha Empresa > Gerencial > Impressao > modo ESC/POS (ou Ambos).'
Write-Host 'O agente inicia sozinho sempre que o Windows ligar.'
