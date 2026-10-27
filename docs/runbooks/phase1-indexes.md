# Phase 1 index deployment

The owner runs this procedure on the Windows/XAMPP installation outside business
hours, with application writes stopped. It changes indexes and adds the stock
snapshot detail surrogate ID; it changes no application code or business rows.
The local rehearsal below passed on MySQL 5.7.44. SQL uses the MySQL/MariaDB 5.5
syntax floor; actual 5.5/MariaDB execution is not claimed. Windows commands and
`apply-migration.bat` are **untested on Windows** until the owner rehearses them.

The measured S1.1 migration took 2.297 seconds locally; the complete S1.2 Bash
runner took 4.49 seconds including Docker-client process overhead. These timings
are not production estimates. Each ALTER can hold a table lock; allow a maintenance
window and keep writes stopped until verification completes.

## Preflight and backup

Identify the actual server, database and MySQL data volume. Record `SELECT
VERSION()`, `SELECT @@datadir`, all seven target tables' `SHOW INDEX`, and measured
free space on **the database server's data volume**, not the operator's working
folder. Require free bytes at least twice the reported database bytes. The runner
reads database size from information_schema and enforces that bound against
`DOYEL_DB_FREE_BYTES`; it cannot remotely verify the operator's disk measurement.

Use an account allowed to back up and ALTER these tables. Supply credentials
through a private MySQL client option file or inherited `MYSQL_PWD`; never put a
production password in this repository or a command argument. A client option
file contains a `[client]` section with credentials and must be readable only by
the operator. The runners put `--defaults-extra-file` first when configured.

Both runners share `tools/prod/phase1-state.sql`, which reads metadata only.
`BASELINE` means the six original primary keys are correct and none of migration
001's objects or the stock ID column exists. `APPLIED` means all 19 secondary
indexes, seven target primary keys and the stock ID definition match exactly.
Any partial/wrong definition or renamed equivalent/covering index is refused for
manual review. Unrelated indexes on other columns are permitted and preserved.
No automatic partial-DDL recovery or duplicate-index creation is attempted.

Back up before applying. Verify a nonempty SQL file ending in `-- Dump completed`,
copy it off the server, compare its checksum, and restore it into a separate
empty test database to confirm all 13 table counts match. Keep the backup outside
the repository. Do not proceed if this restore fails. Never restore over the live
DB merely to test the backup.

Windows/XAMPP template, run from Command Prompt after changing to the repository:

```bat
set "MYSQL_CLIENT=C:\xampp\mysql\bin\mysql.exe"
set "DOYEL_MYSQL_DEFAULTS_FILE=C:\Doyel-private\migration.cnf"
set "DOYEL_DB_HOST=127.0.0.1"
set "DOYEL_DB_PORT=3306"
set "DOYEL_DB_USER=root"
set "DOYEL_DB_NAME=software"
"%MYSQL_CLIENT%" --defaults-extra-file="%DOYEL_MYSQL_DEFAULTS_FILE%" --host="%DOYEL_DB_HOST%" --port="%DOYEL_DB_PORT%" --user="%DOYEL_DB_USER%" "%DOYEL_DB_NAME%" -e "SELECT VERSION(),@@datadir;"
set "DOYEL_PHASE1_ACTION=check"
tools\prod\apply-migration.bat
```

Use the actual datadir from the query in this PowerShell measurement, then copy
its integer output into `DOYEL_DB_FREE_BYTES`:

```bat
powershell.exe -NoProfile -Command "[IO.DriveInfo]::new([IO.Path]::GetPathRoot('C:\xampp\mysql\data\')).AvailableFreeSpace"
set "DOYEL_DB_FREE_BYTES=<measured integer bytes>"
set "DOYEL_PHASE1_BACKUP=C:\Doyel-private\software-pre-phase1-YYYYMMDD.sql"
"C:\xampp\mysql\bin\mysqldump.exe" --defaults-extra-file="%DOYEL_MYSQL_DEFAULTS_FILE%" --host="%DOYEL_DB_HOST%" --port="%DOYEL_DB_PORT%" --user="%DOYEL_DB_USER%" --single-transaction --routines "%DOYEL_DB_NAME%" > "%DOYEL_PHASE1_BACKUP%"
if errorlevel 1 exit /b 1
powershell.exe -NoProfile -Command "$p=$env:DOYEL_PHASE1_BACKUP; if((Get-Item -LiteralPath $p).Length -eq 0 -or (Get-Content -LiteralPath $p -Tail 1) -notlike '-- Dump completed*'){exit 1}; Get-FileHash -Algorithm SHA256 -LiteralPath $p"
```

Create `C:\Doyel-private` beforehand and protect it with the operator's NTFS
permissions. Replace paths, database, username and measured bytes with recorded
values. A UNC/remote DB needs measurement on the actual server volume; do not
substitute the local C: drive. The runner's disk/backup checks apply to rollback too.

## Apply, verify and rollback

Windows (untested here):

```bat
set "DOYEL_PHASE1_ACTION=apply"
tools\prod\apply-migration.bat
if errorlevel 1 exit /b 1
```

POSIX counterpart with the same environment variables:

```bash
tools/prod/apply-migration.sh --check
tools/prod/apply-migration.sh --apply
```

Both runners refuse an already applied migration, partial/conflicting metadata,
invalid connection identifiers, insufficient disk evidence or missing/incomplete
external backup before ALTER. They run the reviewed `001_indexes.up.sql`, abort
on the first SQL error, require the resulting canonical state, and print SHOW
INDEX for every target table. Save output privately, including any failure.
Do not use mysql's `--force` option.

With writes still stopped, log in and check Dashboard, sale bill 7559 (or a
recorded representative production ID), Payment, a sales period report, and
Closing Balance. Verify quantities/totals against pre-maintenance values. The
local full regression already proved all page and write contracts unchanged.
Representative measured medians from S1.1:

| Page | Before | Indexed |
|---|---:|---:|
| Full sales-bill report | 39.627 s | 0.904 s |
| Sales by user | 19.268 s | 0.780 s |
| Payment list | 1.018 s | 0.087 s |
| Saved stock | 0.169 s | 0.006 s |

Query counts remain unchanged; Phase 2 removes their N+1 loops. Client hardware
and its data may produce different timings. Release writes only after owner
verification.

If a fully applied migration must be removed before releasing writes:

```bat
set "DOYEL_PHASE1_ACTION=rollback"
tools\prod\apply-migration.bat
```

```bash
tools/prod/apply-migration.sh --rollback
```

The down file removes only 001's objects and requires the fully applied state.
It does not delete business rows. MySQL DDL autocommits: if any ALTER fails,
earlier statements may remain committed. Keep writes stopped; inspect saved
SHOW INDEX and the first error. Do not blindly rerun up/down. The owner either
repairs the specific schema discrepancy after review or restores the verified
pre-migration backup. A restore overwrites data and must follow the owner's
backup/restore procedure; it is never automatic in these scripts. If writes have
already resumed, reconcile new work before restoring an old backup.

## Actual local rehearsal

Completed on 2026-10-27 using frozen `baseline-legacy` PHP 5.6 source, MySQL 5.7.44
and temporary executable `mysql`/`mysqldump` wrappers that invoke the existing
legacy Docker container and pass arguments unchanged. These wrappers are private
verification artifacts, not production dependencies. No host client installation
was required. Actual commands (private paths already contain the completed run):

```bash
export DOYEL_FIXTURES=/private/tmp/doyel-fixtures
export MYSQL_CLIENT="$DOYEL_FIXTURES/s12/bin/mysql"
export MYSQL_PWD=doyel-local
export DOYEL_DB_HOST=legacy-db DOYEL_DB_PORT=3306 DOYEL_DB_USER=root DOYEL_DB_NAME=software
export DOYEL_PHASE1_BACKUP="$DOYEL_FIXTURES/s12/software-pre-phase1.sql"
env -u DOYEL_DB_MIGRATIONS tools/db-reset.sh legacy
"$DOYEL_FIXTURES/s12/bin/mysqldump" --host=legacy-db --port=3306 --user=root --single-transaction --routines software > "$DOYEL_PHASE1_BACKUP"
docker compose -f docker/compose.yaml exec -T legacy-db df -Pk /var/lib/mysql > "$DOYEL_FIXTURES/s12/disk.private"
export DOYEL_DB_FREE_BYTES=$(awk 'NR==2 {printf "%.0f",$4*1024}' "$DOYEL_FIXTURES/s12/disk.private")
tools/prod/apply-migration.sh --check
tools/prod/apply-migration.sh --apply
DOYEL_DB_HOST=127.0.0.1 DOYEL_DB_PORT=33057 DOYEL_CAPTURE_LABELS=dashboard,sale-posted,payment-list,report-sales-bill,report-closing tools/characterize/capture.sh http://127.0.0.1:8056 "$DOYEL_FIXTURES/s12/smoke"
tools/prod/apply-migration.sh --rollback
tools/prod/apply-migration.sh --apply
```

Outputs were redirected to private `s12/*.private` logs during the run. Applying
twice was refused. Separate local tests confirmed insufficient space, one applied
index, a renamed covering index, and invalid host input are refused; an unrelated
Discount index was accepted by read-only preflight. Test-only indexes were removed
before final reapplication. SHOW CREATE TABLE for all 13 tables matched exactly
before/down and first-up/re-up. The five page routes produced seven output labels
(two reports in both ranges): **7 same, 0 differ, 0 missing**. The actual smoke
capture retained its 7 legacy diagnostics privately; metadata-free comparison
subsets omit their sidecars and therefore print zero diagnostic counts.

The completed backup was restored into the separate local `doyel_s12_restore`
database and all 13 table counts matched the source. No production DB was touched.
Windows execution remains untested. The source fixture is left fully indexed
with original test credentials, ready for the next phase. See `docs/reports/S1.2.md`
for result details; use fresh output paths if repeating the rehearsal.
