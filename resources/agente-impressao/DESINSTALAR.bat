@echo off
rem Remove o agente de impressao 80mm - DSA
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0desinstalar.ps1"
echo.
pause
