@echo off
setlocal
rem This file is copied beside Doyel.iss into the external build bundle.
set "DOYEL_BUILD_BUNDLE=%~dp0"
set "DOYEL_BUILD_ISCC=%ProgramFiles(x86)%\Inno Setup 6\ISCC.exe"
if not "%~1"=="" set "DOYEL_BUILD_ISCC=%~f1"
if not exist "%DOYEL_BUILD_ISCC%" (
  echo Install Inno Setup 6, or pass the full path to ISCC.exe.
  exit /b 1
)
if not exist "%DOYEL_BUILD_BUNDLE%SHA256SUMS.json" (
  echo Missing SHA256SUMS.json. Assemble the bundle with build-bundle.php first.
  exit /b 1
)
powershell.exe -NoProfile -NonInteractive -Command "$ErrorActionPreference='Stop'; $base=$env:DOYEL_BUILD_BUNDLE; $manifest=Get-Content -Raw -LiteralPath (Join-Path $base 'SHA256SUMS.json') | ConvertFrom-Json; foreach($entry in $manifest.PSObject.Properties) { $file=Join-Path $base $entry.Name; if((Get-FileHash -Algorithm SHA256 -LiteralPath $file).Hash -ne $entry.Value) { throw ('Payload changed: '+$entry.Name) } }; Write-Host 'Payload hashes verified.'"
if errorlevel 1 exit /b 1
"%DOYEL_BUILD_ISCC%" "%DOYEL_BUILD_BUNDLE%Doyel.iss"
if errorlevel 1 exit /b 1
echo Build complete. Find the installer in the output folder beside this script.
exit /b 0
