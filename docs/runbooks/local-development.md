# Local regression stacks

All databases below are disposable local fixtures. Ports bind to `127.0.0.1`.
Compose project `doyel` has independent legacy and modern data volumes. The
published test password `doyel-local` is deliberately local-only; never reuse it
in production. The app still uses its original defaults without environment
configuration. Phase 2 replaces that connection with PDO.

## Start and reset

From the repository root:

```bash
export DOYEL_FIXTURES=/private/tmp/doyel-fixtures
docker compose -f docker/compose.yaml --profile legacy --profile modern up -d --build --wait
tools/db-reset.sh legacy
tools/db-reset.sh modern doyel_s00
```

Elsewhere, the fixture default is `~/.doyel-fixtures/dump.sql`. Private fixtures
must stay outside git. The current `/private/tmp` fixture location is temporary;
preserve a durable private copy before OS temporary cleanup.

The legacy app runs at `http://127.0.0.1:8056/login.php`. Test fixture logins are
admin ID 17 (`rayhan`, `admin-test`) and non-admin ID 24 (`jems`, `user-test`).
Only fixture imports set these passwords. Application files are mounted read-only.
MySQL 5.7 on port 33057 uses lenient mode to characterize the existing app.

The modern profile runs only MySQL 8.4 on port 33084. It keeps the server's
default `sql_mode` and uses utf8mb4. Reset imports the original old dump using a
single lenient UTF-8 session; this does not change subsequent connection modes.
The dump is never modified. Until Phase 2 ports `mysql_*`, the app cannot run on
modern PHP.

```bash
tools/serve-modern.sh 8123 doyel_s23
```

This starts the host's PHP CLI server with the isolated modern DB credentials.
Use a separate database per slice, populated with `db-reset.sh modern doyel_sNN`.
Scripts accept `-h`; invalid target, port and database values fail before mutation.
Reset deliberately supports only `software` or names beginning `doyel_`, and uses
the Compose database services rather than configurable remote hosts.

Stop the stacks without deleting their data:

```bash
docker compose -f docker/compose.yaml --profile legacy --profile modern down
```

## Frozen legacy source

After the orchestrator commits verified S0.2, it tags that commit
`baseline-legacy`. The owner requested one checkout without extra worktrees.
Before Phase 2, materialize an immutable baseline archive outside the repo and
point the existing legacy web container at it:

```bash
mkdir -p /private/tmp/doyel-baseline
git archive baseline-legacy | tar -x -C /private/tmp/doyel-baseline
LEGACY_SRC=/private/tmp/doyel-baseline \
  docker compose -f docker/compose.yaml --profile legacy up -d --force-recreate legacy-web
```

Always pass the same absolute `LEGACY_SRC` for later legacy web recreations.
`LEGACY_SRC` defaults to the repository root; relative paths resolve from the
`docker/` directory. The modern server continues to serve the active checkout.
Never commit baseline outputs, SQL, cookies or HTML with real fixture records.

## Windows delivery acceptance (S4.5)

Linux containers validate regression behavior, not the client's desktop shell.
The current PHP Desktop 5.6 executable is not evidence of PHP 8.4 support.
Before production cut-over, record and rehearse on the actual Windows runtime:

- PHP 8.4 executable/package and `php -v`; `pdo_mysql`, `mbstring`, `dom` enabled.
- Chosen PHP Desktop shell or replacement local server, its document root, local
  launch procedure, and restriction to the intended machine/network.
- Actual XAMPP MySQL/MariaDB version, service names, upgrade access, ports and paths.
- Login for both roles, key pages, entry keyboard shortcuts, printing and backup
  download in the chosen browser/shell.
- Restore and rollback to the preserved old PHP Desktop/XAMPP installation.

This Windows acceptance remains a cut-over requirement. It cannot be claimed from
this macOS/Linux development environment.

## Optional source migrations after a fixture reset

`DOYEL_DB_MIGRATIONS` can name comma-separated three-digit source migration IDs,
for example `DOYEL_DB_MIGRATIONS=001 tools/db-reset.sh legacy`. Each ID must
resolve to exactly one regular, non-symlink `db/migrations/NNN_*.up.sql` file in
the repository. Duplicate, malformed, missing or ambiguous IDs fail before the
database is dropped. No arbitrary SQL path is accepted. Migrations run in the
listed order, after import, in fresh connections using the server's normal SQL
mode. Leave the variable unset to reproduce the original schema exactly.

The HTTP scenario runner inherits this setting on every per-scenario reset; use
`DOYEL_DB_MIGRATIONS=001` to verify Phase 1 against the indexed schema rather than
accidentally resetting back to the unindexed baseline. Reset output records each
applied migration. Source migrations belong in git; private dumps never do.

## Phase 3 cleanup on disposable fixtures

Opt in explicitly with `DOYEL_DB_CLEANUP_AS_OF=2026-10-28` and
`DOYEL_DB_MIGRATIONS=001,002`. The reset validates the calendar date, modern
`doyel_*` database, ordered migration list and cleanup source before DROP.
It imports the original dump, applies 001 and 002, previews and applies S3.2,
then applies any later requested migrations. Each reset writes a new private
preview, full JSON row backup and apply log beneath `$DOYEL_FIXTURES/s32-reset.*`.
Do not publish those files. Unset cleanup preserves the original reset behavior.

```bash
export DOYEL_FIXTURES=/private/tmp/doyel-fixtures
export DOYEL_DB_HOST=127.0.0.1 DOYEL_DB_PORT=33084
export DOYEL_DB_USER=root DOYEL_DB_PASS=doyel-local DOYEL_DB_NAME=doyel_s32
DOYEL_DB_MIGRATIONS=001,002 DOYEL_DB_CLEANUP_AS_OF=2026-10-28 \
  tools/db-reset.sh modern doyel_s32
# Same two opt-in variables must accompany run-all: EVERY scenario resets.
DOYEL_DB_MIGRATIONS=001,002 DOYEL_DB_CLEANUP_AS_OF=2026-10-28 \
  tools/scenarios/run-all.sh http://127.0.0.1:8132 modern "$DOYEL_FIXTURES/s32-scenarios"
```

For later Phase 3 rehearsal use `001,002,003` or `001,002,003,004`; cleanup still
runs immediately after 002. Never run fixture reset against production.
