# Remove o agente de impressao ESC/POS deste computador.

$destino = Join-Path $env:LOCALAPPDATA 'DSA-AgenteImpressao'
$inicializar = Join-Path ([Environment]::GetFolderPath('Startup')) 'DSA Agente de Impressao.vbs'

Get-CimInstance Win32_Process -Filter "Name = 'powershell.exe'" |
    Where-Object { $_.CommandLine -like '*DSA-AgenteImpressao*agente.ps1*' } |
    ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }

Remove-Item $inicializar -Force -ErrorAction SilentlyContinue
Start-Sleep -Seconds 1
Remove-Item $destino -Recurse -Force -ErrorAction SilentlyContinue

Write-Host 'Agente de impressao removido.' -ForegroundColor Green
