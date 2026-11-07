@echo off
setlocal
set "DOYEL_UPDATE_BUNDLE=%~dp0"
"%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe" -NoLogo -NoProfile -Command "$r=$env:DOYEL_UPDATE_BUNDLE; $b=[IO.File]::ReadAllBytes((Join-Path $r 'Update-Preflight.ps1')); $h=([BitConverter]::ToString([Security.Cryptography.SHA256]::Create().ComputeHash($b))).Replace('-','').ToLowerInvariant(); if($h -ne '__PREFLIGHT_SHA256__'){throw 'Update bootstrap checksum mismatch'}; &([ScriptBlock]::Create([Text.Encoding]::UTF8.GetString($b))) $r '__PREFLIGHT_SHA256__'"
if errorlevel 1 echo Update refused or interrupted. Keep all data and recovery files; use recover.
pause
