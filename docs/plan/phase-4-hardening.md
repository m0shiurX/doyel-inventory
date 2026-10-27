# Phase 4: Hardening, usability, cut-over

**Goal:** close the remaining security gaps, give the client a backup they can actually use, refresh the front-end libraries, and deliver a rehearsed production cut-over.

## Shared rules

- The Phase 2 conversion rules (R1–R13 in `phase-2-php8-port.md`) still apply to every file you touch.
- The UI changes in this phase are **intentional behaviour changes**. List each one with a before/after description so the owner can tell the client's users.
- S4.1–S4.4 touch some of the same pages. Ownership is split by concern; when two slices need the same file, the **Owns** lists say who edits what. Rebase on `main` before you finish.

---

## S4.1 POST + CSRF for destructive actions

**Depends on:** Phase 2 merged (Phase 3 not required)
**Owns:** `lib/csrf.php` (new), the action handlers and the links/forms that trigger them in `Unpost.php`, `Sales.php`, `purchase.php`, `remove_payment.php`, `Payment.php`, `CashCollection.php`, `Expenses.php`, `SavePrestntStock.php`, and its trigger in `Report.php`; `tools/perf.php` (snapshot request only)
**Resolves:** S6

- `csrf_token()` stores one token per session. `csrf_field()` prints the hidden input. `require_csrf()` checks `hash_equals` and responds 403.
- Every delete, unpost, post or remove action becomes a small `<form method="post">` with a button styled like the old link, plus `require_csrf()` in the handler. GET requests to those handlers do nothing.
- Include the cash collection and expense `?d=` deletes, sales/purchase `NBill=add_new` bill creation, and stock-snapshot creation in the POST/CSRF conversion. The stock snapshot remains available to the same authenticated roles as before. GET may show a confirmation form but must never create a snapshot.
- Update the one-shot stock-snapshot performance request to use the authenticated CSRF form, keeping it a single measured write.
- Add a JS `confirm()` on delete buttons.
- Update scenarios 1, 3, 4, 5, 7 and 8 so they post the forms the new way. This is the only `tools/scenarios/` edit allowed, and the DB results must be unchanged.

**Done when:**
- A GET to any former action URL changes nothing in the DB. Prove it for each action.
- A POST without a token returns 403.
- The updated scenarios match the golden output.

## S4.2 Admin backup download

**Depends on:** Phase 2 merged
**Owns:** `backup.php` (new, root), the "Data Backup" link in `Accounts.php`
**Resolves:** D7 (the missing `Backup/new_backup.php`)

- `require_admin()`. It streams a `.sql` download built in PHP: `SHOW CREATE TABLE`, then batched `INSERT`s read through an unbuffered query, with `SET NAMES utf8mb4` and `DROP TABLE IF EXISTS` headers. It doesn't depend on `mysqldump` (shared or Windows hosting may not have it). The filename is `doyel-backup-YYYY-MM-DD-HHMM.sql`.
- Memory stays flat. Test it on the fixture with `memory_limit=64M`.

**Done when:**
- The downloaded file imports into an empty modern database.
- Row counts per table match the source, and the characterization run against the restored database matches.

## S4.3 Error handling and logging

**Depends on:** Phase 2 merged
**Owns:** `lib/bootstrap.php` (error section), `lib/errors.php` (new), `errors/500.php` (new)
**Resolves:** none listed (operability)

- Production config: `display_errors=0`, everything logged to `logs/app-YYYY-MM.log` (gitignored, created if missing).
- An uncaught exception logs the URL, user, message and trace, then shows a plain "Something went wrong" page with a short reference ID that's also in the log line.
- PDO exceptions never reach the browser. This replaces the legacy `or die(mysql_error())`, which leaked SQL.

**Done when:**
- A forced exception, e.g. a bad SQL string in a throwaway local edit that you revert, shows the 500 page in prod mode and the full error in dev mode, and writes one log line containing the reference ID.

## S4.4 Front-end library refresh

**Depends on:** Phase 2 merged
**Owns:** `codes/`, `report/AutoSuggestDropDown/` (JS/CSS), `css/`, and the `<script>`/`<link>` tags in pages
**Resolves:** F9

- Replace jQuery 1.5, 1.7.1 and 1.7.2 with **one** local copy of jQuery 3.7.1 in `codes/`, served locally because the client may be offline. Fix the removed APIs: `.live()` (gone since 1.9) becomes `.on()`, `.size()` (gone in 3.0) becomes `.length`, and `$.browser` is gone. `.bind()` still works but is deprecated, so move it to `.on()`. To find every call, load the jQuery Migrate 3.x warnings once during testing.
- `bnspell.js` uses Prototype's `Class.create` and `Ajax.Request`. Port it to plain JS (`fetch` + `JSON.parse` instead of `eval`), keep its keyboard behaviour, then delete `prototype.js`. `select.js` already uses raw XHR; leave it alone unless it breaks.
- Check the calendar picker (`css/calendar_eu.js`) and `keyshortcut.js` in current Chrome and Firefox.
- Verify by hand in a browser, keyboard only: sales entry (type an item, Enter, quantity, Enter, Alt+P to post), purchase entry, the item ledger autosuggest, report date pickers. List each flow and its result in the report.

**Done when:**
- No file references `prototype.js` or a jQuery other than 3.7.1.
- The browser console shows no errors on the pages from the S0.3 inventory.
- The keyboard flows above work, with evidence (screenshots kept outside git, or a written checklist).

## S4.5 Production cut-over runbook

**Depends on:** S4.1–S4.4 merged, Phase 3 merged, Windows PHP 8.4 runtime verified (Q1)
**Owns:** `docs/runbooks/cutover.md`, `tools/prod/`
**Resolves:** delivery

The owner executes the runbook. The agent writes it and rehearses it end to end locally: legacy stack with fresh data → full procedure → modern stack serving the migrated data.

1. **Server requirements:** Windows with a verified PHP 8.4 runtime and `pdo_mysql`, `mbstring`, `dom`, plus Argon2id password support for NUL-containing or >72-byte passwords; MySQL 8.4 LTS (or a separately verified MariaDB LTS version). Record the existing XAMPP database version, service names, ports, upgrade access, and executable paths. Verify PHP Desktop compatibility if retaining its shell; otherwise document the replacement local server and launch flow. Keep the old PHP Desktop 5.6/XAMPP installation available for rollback. Include Windows commands and rehearse launch, login, printing and backup download in the chosen runtime.
2. **Freeze and backup:** the time window, the users told, a `mysqldump` copied off the server and verified.
3. **Migrate:** restore the dump into the new DB server if the server changes, then apply `001` → `002` → the signed-off S3.2 clean-up scripts → `003` → the signed-off duplicate-user cleanup and `004`. Cleanup must precede constraints. Rehearse the exact sequence, not all migrations followed by cleanup.
4. **Deploy code:** `config.local.php` with real credentials and a **non-root DB user** limited to the `software` DB (SELECT/INSERT/UPDATE/DELETE, and ALTER only during migrations).
5. **Smoke test:** log in as each role, open the five key pages, do one test sale and void it.
6. **Rollback:** point back to the old server and code (kept untouched until sign-off), and restore the backup.
7. **After cut-over:** passwords migrate as users log in (S2.2). After 90 days, list any users still on MD5 so the owner can reset or disable them.

**Done when:**
- The local rehearsal log is attached. Every command in the runbook comes from that run.
- The rollback path was rehearsed too.
