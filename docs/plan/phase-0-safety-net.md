# Phase 0: Safety net

**Goal:** before any app code changes, make the legacy app runnable locally and record what it does today: the visible output of every page, the DB effect of every write flow, and each page's timing and query count. Every later slice proves itself against this record.

## Shared rules

- Tooling lives in `docker/` and `tools/`. Scripts are POSIX `bash` plus host PHP 8.4 CLI (Herd, already installed, with `pdo_mysql` and `dom`). Every script prints usage with `-h` and exits non-zero on failure.
- Fixtures sit outside git, under `${DOYEL_FIXTURES:-$HOME/.doyel-fixtures}`:
  - `dump.sql`, a copy of `docs/software-sql-backup-25-10-2026--11-01-41.sql`
  - `golden/legacy/{pages,scenarios}/`
  - `perf/`
- The dump comes from the app's own backup script. It has `CREATE TABLE` + `INSERT` only: no `DROP`, no `SET NAMES`, no version header. Import it into an **empty** database with the connection charset set to `utf8`.
- Test logins exist only in fixture databases. `tools/db-reset.sh` sets them after import:
  - `rayhan` (id 17, admin, `userAtho=1`) → password `admin-test`
  - `jems` (id 24, non-admin) → password `user-test`

  Stored as `md5('<password>')`, which equals the app's `md5(mysql_real_escape_string(...))` for these plain strings.

---

## S0.1 Repo hygiene

**Depends on:** none
**Owns:** `.gitignore`, `.gitattributes`, `tools/purge-history.sh`, `docs/runbooks/history-purge.md`
**Resolves:** S8 (ignore future dumps and perform the local history purge approved in Q5)

Steps:
1. Write `.gitignore` covering: `*.sql`, `*.sql.zip`, `*.sql.gz`, `unsused_Backups/myBackups/`, `graft/`, `.DS_Store`, `config.local.php`, `logs/`, `.fixtures/`.
2. Write `.gitattributes` so `*.php`, `*.js` and `*.css` are `text eol=lf`. Leave existing files' line endings untouched. Normalising them is a separate commit after the owner agrees.
3. Before removing anything, preserve the fixture dump outside git and verify its checksum. Then confirm that `docs/software-sql-backup-*.sql` and `unsused_Backups/myBackups/*` are untracked and ignored.
4. Prepare a private recovery copy outside the repo and document its location without exposing client data. Inventory dump paths across all refs, including compressed dumps. With Q5 already approved, run the local purge through `tools/purge-history.sh`; account for the existing non-fresh checkout and preserve all uncommitted work. Do not blindly bypass filter-repo safety checks. Remove SQL and compressed SQL dump paths across all relevant refs, then verify they are absent from reachable history and the working tree still has its intended files. No remote push. Recovery copies contain client data and must stay private.

**Done when:**
- `git check-ignore -v docs/*.sql unsused_Backups/myBackups/*` lists every dump.
- `git status --porcelain` shows no `.sql`, `.zip` or `graft/` entries.
- Report the executed purge command (base: `git filter-repo --path-glob '*.sql' --path-glob '*.sql.zip' --path-glob '*.sql.gz' --invert-paths`, extended for any other discovered dump paths), before/after commit IDs, and verification that dumps are absent from all relevant reachable refs. Confirm preserved fixtures and uncommitted work. Q5 authorizes this local rewrite; report any execution blocker instead of claiming completion.

## S0.2 Local stacks and fixture DB

**Depends on:** S0.1
**Owns:** `docker/`, `tools/db-reset.sh`, `tools/serve-modern.sh`, `connect.php`
**Resolves:** F8 (credentials can now come from env; the defaults keep production working)

Steps:
1. Change `connect.php` to read `DOYEL_DB_HOST`, `DOYEL_DB_PORT`, `DOYEL_DB_USER`, `DOYEL_DB_PASS` and `DOYEL_DB_NAME` from `getenv()`. Fall back to the current literal values, so production with no env set behaves exactly as now. The file must still run on PHP 5.6: use `getenv('X') ?: 'default'`, no `??`. This is the only app-code change in Phase 0.
2. Write `docker/compose.yaml` with two profiles:
   - **legacy**: `php:5.6-apache` (install the `mysql` extension with `docker-php-ext-install mysql`, set `date.timezone=Asia/Dhaka`) on port **8056**, and `mysql:5.7` on port **33057** with `sql_mode=NO_ENGINE_SUBSTITUTION` to match the lenient production server. Both use `platform: linux/amd64` (mysql:5.7 has no arm64 image; OrbStack emulates it). The Apache docroot is the directory in `LEGACY_SRC` (default: the repo root).
   - **modern**: `mysql:8.4` only, on port **33084**, with **default** `sql_mode`, `character-set-server=utf8mb4`, `general_log` available (off by default). There is no PHP container. See DEC6.
3. Write `tools/db-reset.sh <legacy|modern> [dbname]`. It drops and recreates the database (default name `software`), imports `$DOYEL_FIXTURES/dump.sql`, and sets the test logins. On `modern` the import must still succeed: the DDL is plain 5.x DDL, so fix any import failure inside the script (for example a session `sql_mode` for the import only) and leave the dump file unchanged.
4. Write `tools/serve-modern.sh <port> [dbname]`. It runs `php -S 127.0.0.1:<port> -t <repo-root>` with the `DOYEL_DB_*` env pointing at the modern MySQL and the given database. Every Phase 2+ agent uses it as `tools/serve-modern.sh 81NN doyel_sNN`, where NN is the slice number (S2.3 → 8123 / `doyel_s23`).
5. Record Windows delivery requirements: verify the chosen PHP Desktop build or local web-server package runs PHP 8.4 with the required extensions and that the app opens correctly. Do not assume the existing PHP Desktop 5.6 shell supports PHP 8.4. Linux containers provide regression testing; Windows runtime verification is a cut-over prerequisite.
6. Once the legacy app works, tag the commit `baseline-legacy`. Later slices recreate the legacy app from this tag with `git worktree add ../doyel-baseline baseline-legacy` and `LEGACY_SRC=../doyel-baseline`.

**Done when:**
- `docker compose --profile legacy up -d` → http://localhost:8056/login.php loads.
- Logging in as the admin test user reaches the dashboard. `Sales.php?Bill=7559`, `Payment.php` and `Report.php` render real data.
- `tools/db-reset.sh legacy` run twice in a row succeeds both times. Row counts match `findings.md` (190207 rows in `canteen_stock_detail`).
- `tools/db-reset.sh modern doyel_s00` succeeds against the modern profile with the same row counts.
- Tag `baseline-legacy` exists.

## S0.3 Read-only characterization

**Depends on:** S0.2
**Owns:** `tools/characterize/` (`urls.txt`, `capture.sh`, `extract.php`, `compare.sh`, `README.md`)
**Resolves:** none (tooling)

Steps:
1. **Inventory.** List every `*.php` file in the repo in `tools/characterize/README.md` with one of these classifications:
   - `page` (GET-renderable)
   - `endpoint` (AJAX, e.g. `dictionary/*`)
   - `write` (covered by S0.4)
   - `include` (`menu.php`, `footer.php`, `connect.php`, `report/menu.php`)
   - `dead`, with the reason
2. **`urls.txt`.** One line per request: `<label> <METHOD> <path?query> [form-body]`. Cover every `page` and `endpoint` at least once, and the parameterised pages with real IDs from the dump: a posted sale (`Sales.php?Bill=7559`), an unposted sale, a purchase bill, a customer with payments (`Payment.php?ID=..&Name=..`), an item ledger (`report/ItemLedgr.php` POSTed with an item name). Labels are neutral (`payment-customer-with-dues`); URLs may contain customer names, but they stay in the fixture-only output.
3. **Report date range.** Every report reads `$_SESSION['form_date'|'to_date']`, which `Report.php` sets from a POST. `capture.sh` POSTs `Report.php` with a fixed range before the report URLs. Capture the reports twice: full history (`01-01-2016 00:00:00` to `25-10-2026 23:59:59`) and one month (`01-09-2026` to `30-09-2026`).
4. **`capture.sh <base-url> <out-dir>`.** It logs in with a curl cookie jar, sets the range, fetches every line, and pipes each response body through `php tools/characterize/extract.php` into `<out-dir>/<label>.txt`. Also record the HTTP status and any PHP error text found in the body (`Warning:`, `Notice:`, `Fatal error`, `Deprecated:`) in `<out-dir>/_errors.txt`.
5. **`extract.php`.** Parse the HTML with `DOMDocument` and emit the visible text: one line per table row (cells joined with ` | `), one line per other block element, whitespace collapsed. Include form field values (`<input value>`, the selected `<option>`). Then apply the normalisation rules from a single `normalize` table at the top of the file: today's date and time stamps, "this month/previous month" figures on `Accounts.php`, the logged-in username in the `<title>`. Every rule carries a comment naming the page that needs it.
6. **`compare.sh <golden-dir> <candidate-dir>`.** Show a unified diff per label and print a summary line (`N same, M differ, K missing`). Exit 0 only when nothing differs.
7. Capture the legacy golden output into `$DOYEL_FIXTURES/golden/legacy/pages/`.

**Done when:**
- Two consecutive `capture.sh` runs against legacy, each after `db-reset`, give `compare.sh` exit 0. This proves the normalisation is complete.
- Every `page`/`endpoint` file in the inventory appears in `urls.txt`.
- `_errors.txt` for legacy is committed to the README as the *known legacy error baseline* (it may be non-empty; PHP 5.6 notices count).

## S0.4 Write-flow scenarios

**Depends on:** S0.3
**Owns:** `tools/scenarios/`
**Resolves:** none (tooling)

Each scenario is one script, `tools/scenarios/NN-name.sh <base-url> <db-target> <out-dir>`. It starts from `db-reset`, performs a user flow over HTTP with curl exactly as the browser forms submit it (read the form markup for field names and methods), then dumps the affected tables in a fixed format: `SELECT` of the named columns `ORDER BY` the primary key, restricted to rows created or changed by the scenario (ID above the pre-scenario `MAX(id)`, or the touched IDs). Mask `Date` columns as `<ts>` and leave every other column as is.

Scenarios (one script each):
1. New sale: Add New → add 2 items (one by autocomplete name) → remove one line → add again → set discount and note → Post. Tables: `canteen_sales`, `canteen_sales_detail`, `canteen_item` (the touched items' `Qnty`).
2. Sale line with a quantity above stock. Expect it to be rejected; capture the current behaviour.
3. New purchase bill, including a new price for an item. Tables: `canteen_purchase`, `canteen_purchase_detail`, `canteen_item` (`Qnty`, `PurPrice`).
4. Customer collection on `Payment.php`, as admin (negative amount allowed) and as non-admin (abs value). Then delete a payment through `remove_payment.php` as admin and as non-admin.
5. Cash collection and expense entries; add a head of account.
6. Add item, edit item; add customer, edit customer; add supplier.
7. Admin unpost and delete of a sale and of a purchase bill. Then the **same requests as the non-admin user**. This records S3 (a non-admin can delete); Phase 2 changes that result on purpose.
8. Save Present Stock. Tables: `canteen_stock`, and `canteen_stock_detail` reduced to `COUNT(*)`, `SUM(Qnty)` and `SUM(PurPrice*Qnty)` for the new `SlNo`.
9. Change password.

Capture the golden output into `$DOYEL_FIXTURES/golden/legacy/scenarios/`. Add `tools/scenarios/run-all.sh <base-url> <db-target> <out-dir>`.

**Done when:**
- Two consecutive `run-all.sh` runs against legacy give `compare.sh` exit 0.
- Every `write` file from the S0.3 inventory is exercised by at least one scenario, or is listed in `tools/scenarios/README.md` with the reason it's not.

## S0.5 Perf and query-count baseline

**Depends on:** S0.3
**Owns:** `tools/perf.sh`, `docs/perf/baseline-legacy.md`
**Resolves:** none (measurement)

Steps:
1. `tools/perf.sh <base-url> <legacy|modern> [dbname]` replays `urls.txt` (logged in, report range = full history). For each label it records the **median of 5** wall-clock times. It also records the **number of queries**: enable `general_log` (table output) around each request and count the rows for the app's connection.
2. Write `docs/perf/baseline-legacy.md` with a table of label | median ms | queries, sorted by ms descending, and the machine and stack versions it ran on. Labels only: no URLs with names. Raw output goes to `$DOYEL_FIXTURES/perf/`.

**Done when:**
- The table covers every `urls.txt` label.
- `Payment.php` shows several hundred queries. If it doesn't, the counting is wrong (P2 predicts about 700).
- Running `SavePrestntStock.php` is measured once, separately, after a `db-reset`, because it writes.
