# Windows cut-over: owner checklist and rehearsed local procedure

Production is owner-run. Keep writers stopped from the final legacy backup until
acceptance or rollback. Announce the maintenance start/end, affected users and
fallback contact; record who confirmed no open bills/writes. Schedule enough time
for backup, a separate restore, every DDL/cleanup and smoke checks. Local timings
are evidence, not a production downtime promise. The old PHP Desktop 5.6/XAMPP
installation and data remain intact until owner sign-off. Do not upgrade it in place.

The local rehearsal below uses PHP 8.4.27, MySQL 5.7.44 → MySQL 8.4.13. Windows
launch/CGI/keyboard/printing and MariaDB are **pending production gates**. Local
`php -S` is only the regression server, never the production launch procedure.

## 1. Record the actual PC and prepare the new runtime

Before choosing ports or services, record Windows 10/11 edition/architecture,
existing PHP Desktop and XAMPP locations, executable versions, DB service name,
`SELECT VERSION(),@@port,@@datadir,@@sql_mode`, installed mysql/mysqldump paths,
upgrade/service-admin access, backup destinations and free space on both DB data
volumes. Record the new MySQL 8.4 LTS service/path/port separately. Do not assume
XAMPP's advertised name identifies its actual MySQL/MariaDB engine or version.
Measure free space on the actual DB volume (at least twice database bytes per
[Phase 1](phase1-indexes.md), plus backup/rehearsal space). Review the Phase 1
metadata check first: 001 may already be installed. A canonical APPLIED state
means skip 001 after restore; a partial/conflicting state means stop and inspect.

Chosen delivery candidate: a **separate** PHP Desktop Windows 130.1 directory,
with its PHP folder replaced by the official PHP **8.4 x64 NTS** package. The
[release](https://github.com/cztomczak/phpdesktop/releases/tag/chrome-v130.1)
bundles PHP 8.3 and documents broken native JS dialogs. Its official
[interpreter instructions](https://github.com/cztomczak/phpdesktop/wiki/Knowledge-Base#php-interpreter--extensions)
support replacing that folder; use the matching binaries/extensions from
[PHP for Windows](https://windows.php.net/download/). Record package filenames,
versions and verified download SHA256 values. Install the package's documented
Visual C++ runtime prerequisites. Never mix 5.6/8.3 DLLs with 8.4.
These sources were checked on 2026-10-28; replacement support is not proof that
this combination launches/prints on the target PC.

Create a private owner-only NTFS directory such as `C:\Doyel-private` with
`logs`, `sessions`, `cgi-temp`, `backups` and `evidence` children outside `www`.
Verify ACLs with the owner; PHP Desktop runs as that owner and must write there.
Set the new PHP's `php.ini`: `extension_dir` to its own `ext`, enable `pdo_mysql`
and `mbstring` and verify built-in DOM, `display_errors=Off`, `log_errors=On`,
`error_log=C:\Doyel-private\php-error.log`, `session.save_path` to private sessions,
`date.timezone=Asia/Dhaka`. Keep web memory at its tested 64M or greater; cleanup
CLI separately needs **512M** minimum (larger data may need more).

Merge [phpdesktop-settings.json](../../tools/prod/phpdesktop-settings.json)'s
`web_server` and `debugging` fields into the new shell's distributed settings;
retain its Chrome/GPU compatibility settings. Use loopback with port 0, `www`,
`php/php-cgi.exe`, and an empty 404 fallback. Adjust private paths consistently.
[hide_files](https://github.com/cztomczak/phpdesktop/wiki/Settings#hide_files)
patterns match **filenames**, not path rules. They supplement a sanitized package.

The settings field names/types were also checked against the official
[chrome130 template](https://github.com/cztomczak/phpdesktop/blob/chrome130/settings.json).
Its development layout uses `../www`; the delivery layout here deliberately
places `www` and `php` beside the executable. Verify those relative paths on PC.
Keep downloads and print context menu enabled from the distributed settings.

Windows Command Prompt templates below are **not executed on Windows here**.
Fill recorded paths/ports/accounts first. `%PHP84%` is the new **CLI php.exe**;
the shell uses the adjacent **php-cgi.exe** and the same explicit php.ini.

```bat
set "PHP84=<new desktop directory>\php\php.exe"
set "PHP84CGI=<new desktop directory>\php\php-cgi.exe"
"%PHP84%" -v
"%PHP84%" --ini
"%PHP84%" -m
"%PHP84CGI%" -v
"%PHP84CGI%" -m
sc.exe query "<recorded old DB service>"
sc.exe query "<recorded new DB service>"
```

Run `tools\prod\inspect.php --preflight` with the target connection below. It
checks PHP 8.4, pdo_mysql/mbstring/dom, actual Argon2id hash+verify for NUL and
73-byte passwords, DB metadata and grants. Check the CGI interpreter separately
with `-q -f tools\prod\runtime-probe.php` using the same ini; do not expose a phpinfo
page. CLI success alone does not prove the CGI process uses the same ini/DLLs.
Launch the new shell executable from its own directory; it serves `www` itself.
Use a distinct shortcut labelled with the release and retain the old shortcut.
Record the actual launch command/executable and loopback address from its log.
Reject external-interface listening. A startup error, missing extension, native
confirmation dependency or print/download failure blocks cut-over.

## 2. Freeze, dump, copy off the server, and prove restoration

Use private MySQL option files (`[client]` credentials, owner-only ACLs), never
passwords in arguments/git. Use separate old-server and new maintenance files.
Record `OLD_MYSQL`, `OLD_DUMP`, `NEW_MYSQL`, host, port and database from preflight.
Use client binaries compatible with the respective server. This is the general
Command Prompt syntax; all angle-bracket fields are owner-supplied:

```bat
"%OLD_MYSQL%" --defaults-extra-file="%OLD_CNF%" --host="%OLD_HOST%" --port="%OLD_PORT%" "%OLD_DB%" -e "SELECT VERSION(),@@port,@@datadir,@@sql_mode;"
"%OLD_DUMP%" --defaults-extra-file="%OLD_CNF%" --host="%OLD_HOST%" --port="%OLD_PORT%" --single-transaction --routines --triggers --hex-blob "%OLD_DB%" > "%PRIVATE%\backups\before.sql"
if errorlevel 1 exit /b 1
powershell.exe -NoProfile -Command "$p=$env:PRIVATE+'\backups\before.sql'; if((Get-Item -LiteralPath $p).Length -eq 0 -or (Get-Content -LiteralPath $p -Tail 1) -notlike '-- Dump completed*'){exit 1}; Get-FileHash -Algorithm SHA256 -LiteralPath $p"
copy "%PRIVATE%\backups\before.sql" "<off-server private destination>\before.sql"
powershell.exe -NoProfile -Command "Get-FileHash -Algorithm SHA256 -LiteralPath '<off-server private destination>\before.sql'"
```

Require identical hashes and restore the copy into a **new empty database** on
an isolated compatible rehearsal instance; compare 13 exact table counts/row
bytes using inspect snapshots. Set DOYEL_DB_* to each source/restore connection,
then use `--snapshot=<new-private.json>` and `--compare=<same-private.json>`.
Keep snapshots private (digests can expose small-domain values). Run from the
maintenance checkout, never copy `tools/` into `www`. Stop if restoration fails.
Restore the same tested final dump to the new server, with original database
charset metadata, before migrations. If the historical dump needs lenient mode,
limit `SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION'` to the import connection;
new migration/app connections must use MySQL 8.4 defaults. Do not weaken the server.

## 3. Run the reviewed migration order with maintenance credentials

Create an owner-only private `maintenance.php` returning `db_host`, `db_port`,
`db_name`, `db_user`, `db_pass` for the maintenance account. Use
`"%PHP84%" tools\prod\run-private.php "%PRIVATE%\maintenance.php" inspect --preflight`.
The launcher loads that file outside the web root and passes DOYEL_DB_* only to
the child PHP process, never as credential command arguments. It accepts only
inspect/cleanup/users and sets cleanup CLI memory to 512M. Source checkout
config.local.php is not the release stub and must never be silently relied on.
Alternatively explicitly provision `DOYEL_DB_HOST`, `DOYEL_DB_PORT`,
`DOYEL_DB_NAME`, `DOYEL_DB_USER` and `DOYEL_DB_PASS` in a trusted private
maintenance environment. These must identify the
**new** DB. MySQL client option files and PHP variables are independent; verify
both select the same DB/server before each phase. Maintenance needs SELECT,
INSERT/UPDATE/DELETE, CREATE/ALTER/DROP/INDEX/REFERENCES, CREATE ROUTINE,
ALTER ROUTINE and EXECUTE as required by the reviewed scripts. A narrowly scoped
migration account may need additional dump/metadata grants on its server; prove
those in rehearsal. Do not grant administrative privileges to the web account.

Every mysql invocation below uses `%NEW_MYSQL% --defaults-extra-file="%NEW_CNF%"
--host="%NEW_HOST%" --port="%NEW_PORT%" "%NEW_DB%"` followed by input redirection.
Run each as a separate command, check `if errorlevel 1 exit /b 1`, and retain
private stdout/stderr. **No `--force`**, no continuing after a failed statement.
MySQL DDL autocommits; partial execution requires inspection or verified restore.

1. Apply `db\migrations\001_indexes.up.sql` only if Phase 1 canonical state is
   BASELINE; verify APPLIED. Preserve existing valid 001 objects otherwise.
2. Save an inspect `--snapshot`, apply `002_utf8mb4.up.sql`, compare that snapshot
   with `--compare`. The migration's source-encoding guards must pass. Inspect
   database/table/text-column collations: all utf8mb4_unicode_ci; retain TEXT
   capacity and all 19 added indexes. See [S3.1](../reports/S3.1.md).
3. Record the **actual** owner-approved Asia/Dhaka `YYYY-MM-DD` as `%AS_OF%`.
   Local evidence's 2026-10-28 is not a permanent production cutoff. Preview,
   review counts/IDs and cutoffs, sign off, then apply the **same fresh preview**:

```bat
"%PHP84%" tools\prod\run-private.php "%PRIVATE%\maintenance.php" cleanup --allow-production --as-of=%AS_OF% --report="%PRIVATE%\evidence\cleanup-preview.json"
"%PHP84%" tools\prod\run-private.php "%PRIVATE%\maintenance.php" cleanup --allow-production --as-of=%AS_OF% --apply="%PRIVATE%\evidence\cleanup-preview.json" --backup="%PRIVATE%\backups\cleanup-rows.json"
"%PHP84%" tools\prod\run-private.php "%PRIVATE%\maintenance.php" cleanup --allow-production --as-of=%AS_OF% --report="%PRIVATE%\evidence\cleanup-after.json"
```

   Verify six eligible selections are empty; invalid snapshot dates/orphans must
   receive explicit review, never silently guessed stock corrections. Cleanup
   backs up all 13 tables and proves surviving row fields before committing.
   See [S3.2](../data/S3.2.md) and [cleanup instructions](../../db/cleanup/README.md).
4. Run inspect `--normalized-snapshot=<new-private.json>`, apply
   `003_types_defaults.up.sql`, then `--normalized-compare=<same-private.json>`.
   This checks all row fields with numeric columns cast through server-rendered
   decimal strings to the approved target scales; unsupported range/precision
   refuses. Raw row digests legitimately differ on FLOAT → DECIMAL padding;
   never use those differences to waive real discrepancies. Confirm all 22
   target column types/defaults, cent totals/customer dues and valid fractional
   history as described in [S3.3](../data/S3.3.md). The local numeric checker is
   **fixture-only** and must not be offered unchanged for production.
5. Preview/review duplicate users (expected removes **26,28**, survivors **27,29**),
   reference inventory and historical attribution. Stop on unexpected identities
   or references. Invalidate all old browser sessions before new-runtime launch.

```bat
"%PHP84%" tools\prod\run-private.php "%PRIVATE%\maintenance.php" users --allow-production --report="%PRIVATE%\evidence\users-preview.json"
"%PHP84%" tools\prod\run-private.php "%PRIVATE%\maintenance.php" users --allow-production --apply="%PRIVATE%\evidence\users-preview.json" --backup="%PRIVATE%\backups\users-rows.json"
```

6. Apply `004_constraints.up.sql` with FOREIGN_KEY_CHECKS=1. Verify UNIQUE UserName
   and four RESTRICT foreign keys, no dangling bill/item references, exact
   surviving users/history; [S3.4](../data/S3.4.md) has the SQL inventory.

After the migration sequence, save the portable read-only metadata inventory
outside the web root. It lists database/table charset/engine, all column
nullability/types/defaults/extra/collation, index fields and foreign-key columns
with update/delete rules, without reading business rows:

```bat
"%NEW_MYSQL%" --defaults-extra-file="%NEW_CNF%" --host="%NEW_HOST%" --port="%NEW_PORT%" "%NEW_DB%" < tools\prod\schema-status.sql > "%PRIVATE%\evidence\schema-status.txt"
if errorlevel 1 exit /b 1
```

Compare the saved metadata with the reviewed migrations and S3 data reports.
Require 13 InnoDB/utf8mb4_unicode_ci tables, all 19 migration-001 indexes, the
reviewed 22 numeric conversions/defaults, unique username key and four RESTRICT
foreign keys. The local rehearsal inventory had 99 columns and 37 distinct
indexes (50 index-field rows); preserve the actual production output for review
and stop on unexpected definitions. This inventory is evidence for review,
not an automatic validator or permission to waive mismatches.

## 4. Package runtime code and use a separate restricted account

Use reviewed source at the committed release. Build into a **new external**
`www` directory; do not serve the repository. The builder copies an explicit
104-file runtime allowlist, omitting `.git`, `db`, `tools`, `docs`, raw text,
source samples, logs and fixtures. It never reads/copies config.local.php.
The generated stub only requires a specified absolute **private** runtime.php.

```bat
"%PHP84%" tools\prod\build-release.php "<new desktop directory>\www" "C:\Doyel-private\runtime.php"
```

Create private runtime.php manually, returning `db_host`, `db_port`, `db_name`,
`db_user`, `db_pass`, `env => production` and `log_dir => C:\Doyel-private\logs`.
Use PHP string escaping correctly (`'C:\\Doyel-private\\logs'`). Do not retain
maintenance environment credentials: DOYEL_* overrides the private file.
Create a new non-root runtime account restricted to the target DB and actual
loopback host. Its only app grants are SELECT/INSERT/UPDATE/DELETE on that DB.
Create its password privately using the maintenance console, then:

```sql
GRANT SELECT,INSERT,UPDATE,DELETE ON `software`.* TO 'doyel_runtime'@'127.0.0.1';
SHOW GRANTS FOR 'doyel_runtime'@'127.0.0.1';
```

`software`/account host are examples: substitute the actual recorded DB and
server-matched account host. Revoke temporary migration access when complete;
prove runtime can do all workflows/backup and cannot ALTER or access another DB.
Keep the maintenance source/credentials outside every web root.

Mandatory **actual shell HTTP deny proof** before release: request `/.git/HEAD`,
`/db/migrations/001_indexes.up.sql`, `/tools/prod/inspect.php`, `/docs/plan/README.md`,
`/report/img/raw.txt`, `/config.sample.php`, `/config.local.php`, `/runtime.php`,
`/logs/app-YYYY-MM.log` and a nonexistent URL. Expect 403/404, no source/credential/
log body, no fallback app response, no directory listing. Prove PHP execution
with normal login, not a source download. The local CLI server packaging test
cannot validate PHP Desktop's hide_files behavior. Keep this production gate open
until the Windows results are recorded.

## 5. Acceptance while writers remain frozen

Log in as admin and ordinary user. For each role open Dashboard, representative
Sales bill, Payment, sales-period report and Closing Balance; compare quantities,
Unicode and totals with approved preflight records. Test keyboard sale/purchase
entry, item ledger autosuggest, calendar and Ctrl+P/actual invoice printing. Use
HTML deletion confirmation (no native confirm). Print to the client's actual
printer and verify pagination, amounts, Bengali text, paper size and copies.
Do one clearly recorded test sale, post, then admin void; prove header/details
removed and stock restored. Keep IDs in the maintenance log.

As admin download Data Backup with a 64M test limit where feasible. Require a
nonempty SQL ending `-- Doyel backup complete.` (the mysqldump marker differs).
Verify ordinary users redirect to Dashboard without SQL (the existing require_admin contract). Restore to a separate empty modern database;
compare all 13 exact counts and row bytes and run read-only smoke on the restore.
Compare restored pages using read-only workflows; login upgrades MD5 hashes, so
capture digests **before** logging in to the restored DB. Verify no PDO errors
reach the browser and durable private logs work. Owner signs each gate with time,
release revision, DB version, runtime package and measured results. Only then
open writes and record cut-over time, backup locations and rollback owner.

## 6. Rollback (rehearse before opening writes)

Close the new shell and stop all writers. Preserve a complete modern SQL export,
private logs, release config and migration evidence first. Before any rollback
after business writes resumed, the owner must reconcile **all post-cutover work**
from that export into the selected legacy recovery state; do not overwrite newer
sales/payments with the pre-cutover dump. Escalate reconciliation before reopening.

For a frozen/no-new-work rollback, restore the verified pre-cutover SQL into a
separate empty legacy-compatible recovery DB or the owner-approved old DB while
old services are stopped. Verify all 13 original table counts/bytes. Launch the
preserved old Desktop/code against the preserved old DB (or verified recovery DB),
log in as both roles, check the same five pages and print. Reopen writes only after
owner sign-off. Keep modern code/DB/export for investigation. Do not blindly run
003 down: it correctly refuses the fixture's valid fractions; down migrations
cannot recover cleanup deletions. Preserve rather than round business history.

## 7. Ninety days after sign-off

Record cutover +90-day calendar date and owner. Run `inspect.php --md5` against
the accepted production DB and retain only IDs/counts privately. Users migrate
passwords on successful login. For remaining MD5 IDs arrange a successful sign-in (which rehashes), or a
known-old-password change through password.php, and confirm access. There is no
admin reset UI: forgotten-password recovery is a separate owner/DBA procedure,
with identity verification, a securely delivered replacement, and a hash generated
using the accepted application password policy. Rehearse and review that recovery
with the owner before changing credentials; never publish plaintext or hashes. There is
no implemented disabled flag; do not repurpose `userAtho` or invent a disable
command. Re-run the inventory until none remain and record the result.

## Local end-to-end evidence

The exact local command transcript, results and limitations are recorded in
[S4.5 report](../reports/S4.5.md). Local tools/schema checks and Docker client
wrappers are local-only. Windows templates above translate those operations;
Windows syntax, service management and actual launch/printing are explicitly
unexecuted here. MariaDB requires its own full migration/restore/rollback and
runtime acceptance; no version claim is inferred from MySQL results.
