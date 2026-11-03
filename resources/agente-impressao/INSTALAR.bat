@echo off
rem Instala o agente de impressao 80mm (ESC/POS) - DSA
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0instalar.ps1"
echo.
pause
