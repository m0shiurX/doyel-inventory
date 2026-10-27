@echo off
setlocal EnableExtensions DisableDelayedExpansion
rem Windows counterpart. Action comes from environment to avoid unsafe argument expansion.
rem Configure DOYEL_PHASE1_ACTION=check, apply or rollback. See the runbook.
if not defined DOYEL_PHASE1_ACTION set "DOYEL_PHASE1_ACTION=check"
if not defined MYSQL_CLIENT set "MYSQL_CLIENT=C:\xampp\mysql\bin\mysql.exe"
if not defined DOYEL_DB_HOST set "DOYEL_DB_HOST=localhost"
if not defined DOYEL_DB_PORT set "DOYEL_DB_PORT=3306"
if not defined DOYEL_DB_USER set "DOYEL_DB_USER=root"
if not defined DOYEL_DB_NAME set "DOYEL_DB_NAME=software"
powershell.exe -NoProfile -Command "$ErrorActionPreference='Stop'; if($env:DOYEL_PHASE1_ACTION -notmatch '^(check|apply|rollback)$' -or $env:DOYEL_DB_HOST -notmatch '^[A-Za-z0-9_.:-]+$' -or $env:DOYEL_DB_PORT -notmatch '^\d{1,5}$' -or [int]$env:DOYEL_DB_PORT -lt 1 -or [int]$env:DOYEL_DB_PORT -gt 65535 -or $env:DOYEL_DB_USER -notmatch '^[A-Za-z0-9_.-]+$' -or $env:DOYEL_DB_NAME -notmatch '^[A-Za-z0-9_]+$' -or $env:MYSQL_CLIENT.Contains([char]34) -or !(Test-Path -LiteralPath $env:MYSQL_CLIENT -PathType Leaf)) {exit 1}; if($env:DOYEL_MYSQL_DEFAULTS_FILE -and ($env:DOYEL_MYSQL_DEFAULTS_FILE.Contains([char]34) -or !(Test-Path -LiteralPath $env:DOYEL_MYSQL_DEFAULTS_FILE -PathType Leaf))) {exit 1}"
if errorlevel 1 goto invalid
set "STATE_SQL=%~dp0phase1-state.sql"
set "REPO=%~dp0..\.."
set "META=%TEMP%\doyel-phase1-%RANDOM%-%RANDOM%.tmp"
if exist "%META%" goto invalid
set "DEFAULTS_ARG="
if defined DOYEL_MYSQL_DEFAULTS_FILE set DEFAULTS_ARG=--defaults-extra-file="%DOYEL_MYSQL_DEFAULTS_FILE%"
call :metadata
if errorlevel 1 goto failed
for /f "usebackq tokens=1-4 delims=	" %%A in ("%META%") do (
    set "MIGRATION_STATE=%%A"
    set "SERVER_VERSION=%%B"
    set "DB_BYTES=%%C"
    set "DB_DATADIR=%%D"
)
powershell.exe -NoProfile -Command "if($env:SERVER_VERSION -notmatch '^(\d+)\.(\d+)' -or ([int]$matches[1] -lt 5) -or ([int]$matches[1] -eq 5 -and [int]$matches[2] -lt 5)) {exit 1}"
if errorlevel 1 goto invalid
echo Target %DOYEL_DB_HOST%:%DOYEL_DB_PORT%/%DOYEL_DB_NAME%; version %SERVER_VERSION%; state %MIGRATION_STATE%; database bytes %DB_BYTES%
if "%MIGRATION_STATE%"=="PARTIAL_OR_CONFLICT" goto conflict
if "%DOYEL_PHASE1_ACTION%"=="check" goto show
if "%DOYEL_PHASE1_ACTION%"=="apply" (
    if not "%MIGRATION_STATE%"=="BASELINE" goto wrongstate
    set "SOURCE_SQL=%REPO%\db\migrations\001_indexes.up.sql"
    set "EXPECTED=APPLIED"
) else (
    if not "%MIGRATION_STATE%"=="APPLIED" goto wrongstate
    set "SOURCE_SQL=%REPO%\db\migrations\001_indexes.down.sql"
    set "EXPECTED=BASELINE"
)
set "DOYEL_PHASE1_REPO=%REPO%"
powershell.exe -NoProfile -Command "$ErrorActionPreference='Stop'; if($env:DB_BYTES -notmatch '^\d{1,18}$' -or $env:DOYEL_DB_FREE_BYTES -notmatch '^\d{1,18}$' -or [decimal]$env:DOYEL_DB_FREE_BYTES -lt 2*[decimal]$env:DB_BYTES) {exit 1}; $p=$env:DOYEL_PHASE1_BACKUP; if(!$p -or !(Test-Path -LiteralPath $p -PathType Leaf) -or (Get-Item -LiteralPath $p).Length -eq 0) {exit 1}; $full=[IO.Path]::GetFullPath($p); $repo=[IO.Path]::GetFullPath($env:DOYEL_PHASE1_REPO).TrimEnd('\')+'\'; if($full.StartsWith($repo,[StringComparison]::OrdinalIgnoreCase) -or (Get-Content -LiteralPath $p -Tail 1) -notlike '-- Dump completed*') {exit 1}"
if errorlevel 1 goto prerequisites
if not exist "%SOURCE_SQL%" goto invalid
"%MYSQL_CLIENT%" %DEFAULTS_ARG% --protocol=TCP --host="%DOYEL_DB_HOST%" --port="%DOYEL_DB_PORT%" --user="%DOYEL_DB_USER%" --batch --raw --skip-column-names "%DOYEL_DB_NAME%" < "%SOURCE_SQL%"
if errorlevel 1 goto ddlfailed
call :metadata
if errorlevel 1 goto failed
for /f "usebackq tokens=1 delims=	" %%A in ("%META%") do set "MIGRATION_STATE=%%A"
if not "%MIGRATION_STATE%"=="%EXPECTED%" goto conflict
:show
for %%T in (canteen_sales canteen_sales_detail canteen_purchase canteen_purchase_detail canteen_collection canteen_expenses canteen_stock_detail) do (
    echo Indexes: %%T
    "%MYSQL_CLIENT%" %DEFAULTS_ARG% --protocol=TCP --host="%DOYEL_DB_HOST%" --port="%DOYEL_DB_PORT%" --user="%DOYEL_DB_USER%" --batch --raw --skip-column-names "%DOYEL_DB_NAME%" -e "SHOW INDEX FROM %%T;"
    if errorlevel 1 goto failed
)
echo Verified migration state: %MIGRATION_STATE%
del "%META%" >nul 2>&1
exit /b 0
:metadata
"%MYSQL_CLIENT%" %DEFAULTS_ARG% --protocol=TCP --host="%DOYEL_DB_HOST%" --port="%DOYEL_DB_PORT%" --user="%DOYEL_DB_USER%" --batch --raw --skip-column-names "%DOYEL_DB_NAME%" < "%STATE_SQL%" > "%META%"
exit /b %errorlevel%
:invalid
echo Error: invalid action, connection settings, client path, source file or server version. 1>&2
goto failed
:conflict
echo Error: partial or conflicting schema. Keep writes frozen and recover manually. 1>&2
goto failed
:wrongstate
echo Error: migration already applied or not fully applied for rollback. No DDL executed. 1>&2
goto failed
:prerequisites
echo Error: measured free bytes and completed external backup are required. 1>&2
goto failed
:ddlfailed
echo Error: DDL stopped; earlier ALTERs may already be committed. Keep writes frozen. 1>&2
:failed
if defined META if exist "%META%" del "%META%" >nul 2>&1
exit /b 1
