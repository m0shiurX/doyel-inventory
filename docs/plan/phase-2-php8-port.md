# Phase 2: Port to PHP 8.4 + PDO

**Goal:** every page runs on PHP 8.4 against MySQL 8.4 (default `sql_mode`). Every query is parameterised, every page has its auth guard, all output is escaped, and the N+1 loops are gone. Users see the same data as on legacy.

S2.1 builds the foundation and one converted exemplar page. Then S2.2–S2.9 convert disjoint file sets **in parallel**, and S2.10 sweeps up. The file lists below cover every PHP file in the repo exactly once.

## Shared rules (all Phase 2 slices)

### Environment

- Run: `docker compose -f docker/compose.yaml --profile modern up -d`, then `tools/db-reset.sh modern doyel_sNN`, then `tools/serve-modern.sh 81NN doyel_sNN` (NN = slice number).
- Compare against the legacy golden output: `tools/characterize/capture.sh http://127.0.0.1:81NN <out>` and `tools/characterize/compare.sh $DOYEL_FIXTURES/golden/legacy/pages <out>`. Do the same with `tools/scenarios/run-all.sh`.
- Diff only the labels for your owned files. Other slices' pages are still unported and will fail on PHP 8.

### Conversion rules

Every converted file follows all of these. `index.php`, converted in S2.1, is the reference: copy its shape.

| # | Rule |
|---|------|
| R1 | **Bootstrap first.** The first line of each page is `require __DIR__ . '/lib/bootstrap.php';` (from `report/`: `__DIR__ . '/../lib/bootstrap.php'`). Then `require_login();`, or `require_admin();` for admin pages. Only `login.php` skips the guard. AJAX endpoints use `require_login_json()`, which answers `401` instead of redirecting. Delete the old `session_start()`, `include 'connect.php'` and `date_default_timezone_set(...)` lines. |
| R2 | **SQL only through `db_*` helpers with `?` placeholders.** Request values, session values *and values read from the DB* are always bound, never concatenated. Identifiers and `ORDER BY` directions come from a whitelist in the same file. `LIMIT`/`OFFSET` use `(int)` literals, because emulated prepares quote bound values and `LIMIT '10'` is a syntax error. |
| R3 | **Escape on output.** HTML text and attributes use `e($v)`. Values inside a URL use `e(urlencode($v))`. Values inside inline JS use `json_encode($v)`. AJAX bodies that the JS `eval()`s or `split('/')`s keep their exact format (see S2.3). |
| R4 | **Keep result order.** MySQL 8 dropped implicit `GROUP BY` sorting. Wherever a legacy query has `GROUP BY x` and no `ORDER BY`, add `ORDER BY x`. Wherever it has neither and lists rows, add `ORDER BY <primary key>`. That reproduces legacy order. |
| R5 | **`ONLY_FULL_GROUP_BY` (F6).** Replace `SELECT *, SUM(..) GROUP BY c` with only the columns the page prints. A column that's constant within the group (e.g. `ItemName` per `ItemID`) is wrapped in `MIN()`. A column that varies within the group: read what legacy printed (MySQL 5.x took the first row, in practice the lowest primary key) and reproduce it deliberately, with a one-line comment. Characterization proves it. |
| R6 | **Strict mode (F7).** When an INSERT or UPDATE leaves out a NOT NULL column that has no default, write the value lenient MySQL used implicitly: `0` for numbers, `''` for strings. Write datetimes with `now_sql()` (`Y-m-d H:i:s`, F5). Schema defaults arrive in Phase 3; Phase 2 code must not depend on them. |
| R7 | **PHP 8 numeric strictness (F10).** `"" + 1`, `"abc" * 2` and `round("")` throw `TypeError`. Initialise accumulators as `0`. Cast request numbers with `(float)` or `(int)` where they're read. Never do arithmetic on a value that may be `''`. |
| R8 | **No undefined keys or variables.** Read request input with `$_GET['x'] ?? ''` / `$_POST['x'] ?? ''`. Initialise every variable before a loop or condition uses it. The page must emit zero warnings, notices and deprecations at `E_ALL`. |
| R9 | **Fetch shape.** `db_all()` returns a `stdClass` list, so `$row->Column` code keeps working. Replace `if (mysql_num_rows($rs)>0) { while ($row=mysql_fetch_object($rs)) {...} }` with `foreach (db_all(...) as $row) {...}`. |
| R10 | **No N+1.** A query inside a loop over rows becomes one `GROUP BY` or `JOIN` query that runs before the loop, mapped by key. |
| R11 | **Links.** Fix `Report/` → `report/` (F3) and other case mismatches in your own files. |
| R12 | **Writes that belong together go in `db_tx()`.** A detail INSERT plus its stock UPDATE is one transaction. Stock changes are relative: `UPDATE canteen_item SET Qnty = Qnty - ? WHERE ItemID = ?` instead of writing a value computed in PHP (D2). |
| R13 | **Keep the markup.** Same forms, field names, IDs and JS hooks. Restyling and layout changes belong to Phase 4. |

### Done when (every slice in S2.2–S2.9, in addition to its own list)

- `grep -nE "mysql_|split\(|get_magic_quotes|Asia/Dacca" <owned files>` returns nothing.
- `php -l` passes on each owned file.
- For each owned page, `capture.sh` on modern shows no errors in `_errors.txt`. `compare.sh` against the legacy golden output shows no diffs except entries listed under *Intentional behaviour changes*.
- The owned scenarios in `run-all.sh` match the legacy golden output, apart from listed intentional changes.
- The query count for each owned page is recorded with `tools/perf.sh ... modern` in the report. Any page that was N+1 on legacy now runs a constant number of queries.

---

## S2.1 Foundation and exemplar

**Depends on:** Phase 0 merged
**Owns:** `lib/` (new), `config.sample.php` (new), `connect.php`, `menu.php`, `footer.php`, `index.php`, `logout.php`, `report/menu.php`
**Resolves:** F1/F4/F8 for the owned files; supplies the tools for S1–S5

Build `lib/` with this API. Other slices rely on exactly these names and signatures:

```php
// lib/bootstrap.php: the only file pages require
//   starts the session; sets the timezone (Asia/Dhaka); sets error reporting
//   (dev: display; prod: log only, from config); loads config; requires the files below
// lib/config.php: env DOYEL_DB_* first, then config.local.php, then the legacy defaults
// lib/db.php
function db(): PDO;                                          // lazy singleton; options per DEC4 + ERRMODE_EXCEPTION, FETCH_OBJ, charset utf8mb4
function db_all(string $sql, array $params = []): array;     // list of stdClass
function db_one(string $sql, array $params = []): ?object;   // first row or null
function db_value(string $sql, array $params = []): mixed;   // first column of first row, or null
function db_exec(string $sql, array $params = []): int;      // affected rows
function db_insert_id(): string;
function db_tx(callable $fn): mixed;                         // begin/commit, rollback + rethrow on any Throwable
function now_sql(): string;                                  // date('Y-m-d H:i:s')
// lib/auth.php
function current_user(): ?string;                            // $_SESSION['Username']
function is_admin(): bool;                                   // $_SESSION['admin'] == 1
function require_login(): void;                              // redirect to login.php (path-aware from report/)
function require_admin(): void;                              // redirect to index.php AND exit (fixes the S3 pattern)
function require_login_json(): void;                         // 401 + exit
// lib/html.php
function e(mixed $v): string;                                // htmlspecialchars(ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); null → ''
function redirect(string $url): never;                       // header + exit; JS fallback if headers were sent
function redirectTo(string $url): never;                     // alias kept for unported callers
```

Steps:
1. Write `lib/`, plus `config.sample.php` that documents each setting.
2. `menu.php`: keep the navigation markup. Its redirect function becomes the alias from `lib/html.php`, and its login check becomes `require_login()`. It must not re-declare functions if a page already loaded the bootstrap.
3. `connect.php`: becomes `require_once __DIR__ . '/lib/bootstrap.php';`, so any page another slice hasn't converted yet still gets a session and config. Its `mysql_connect` call goes away.
4. `report/menu.php` (the number-to-words helper): make it PHP-8 safe (R7/R8). Keep the function name and signature.
5. Convert `index.php` (dashboard) and `logout.php` fully, following every rule. These are the exemplar.
6. Write `tools/lint.sh`, which runs `php -l` on every file passed to it, and add a usage line to `tools/characterize/README.md`.

**Done when:**
- The common Done-when list passes for `index.php` and `logout.php`. Labels `dashboard` and `logout` match the legacy golden output. At this intermediate slice `login.php` still uses the old connection API: create a private fixture session through a CLI-only verification helper for authenticated checks, then port login in S2.2 immediately afterwards. Do not add a mysql compatibility shim or claim the legacy login works on PHP 8.
- `php -r 'require "lib/bootstrap.php"; var_dump(db_value("SELECT COUNT(*) FROM canteen_item"));'` prints `string(3) "868"`. The string type proves DEC4 is in effect.
- `db_tx` rolls back on an exception. Show this with a throwaway CLI check: insert a row, throw, then count the rows.
- A request to `index.php` with no session redirects to `login.php`. A request to a `report/` page that already uses the bootstrap redirects to `../login.php`.

## S2.2 Auth and users

**Depends on:** S2.1
**Owns:** `login.php`, `password.php`, `list_of_user.php`
**Resolves:** S1, S2, S4, S5, S9 for these files; F2 (`get_magic_quotes_gpc`)

- **Login:** look the user up by `UserName` and the stored hash.
  - If the stored value starts with `$2y$` / `$argon2`, check it with `password_verify`.
  - Otherwise compare against `md5(legacy_escape($password))`. `legacy_escape` reproduces `mysql_real_escape_string`: it backslash-escapes `\0`, `\n`, `\r`, `\`, `'`, `"` and `\x1a`. That's how the hashes were made (S4). When the MD5 check passes, rehash with `password_hash(PASSWORD_DEFAULT)` and store the new hash. Every active user migrates silently on their next login.
  - Duplicate usernames (D5): select all rows with that name and accept the first one whose password verifies, in `user_id` order.
  - Call `session_regenerate_id(true)` after a successful login.
- **Change password** and **add/edit user:** store `password_hash`. Reject an empty new password. Keep the existing form flow.
- `list_of_user.php`: `require_admin()` (S9). This is an intentional change: non-admins lose user management. List it.

**Done when (in addition to the common list):**
- Login with `admin-test` works and rewrites that user's `password` column to a `$2y$` hash (check the DB). A second login with the same password still works.
- A wrong password fails. A user whose password is stored as `md5('')` (id 28) cannot log in with an empty password.
- Scenario 9 (change password) passes. Its only intentional change is the hash format, so mask the `password` column for this scenario.

## S2.3 Sales flow

**Depends on:** S2.1
**Owns:** `Sales.php`, `dictionary/SalesItem.php`, `dictionary/select.php`, `report/SalesMemo.php`
**Resolves:** S1 (including the unauthenticated `select.php`), S2, S5, S7, D2, P6, F5, F7 for these files

- **Response formats are contracts** consumed by `codes/bnspell.js` and `codes/select.js`. Keep them:
  - `dictionary/SalesItem.php` returns a JS array literal that the JS `eval()`s. Emit `json_encode(array_column(...))`. It's valid for `eval` and handles quotes in names.
  - `dictionary/select.php?q=` returns `ItemID/Qnty/PurPrice/SalesPrice`, `/`-separated, as raw values. Keep it byte-identical for normal items. `purchase.php` uses it too (S2.4 must not edit it).
- **Add line:**
  - Read `Rate` and `PurPrice` from `canteen_item` on the server, by `ItemID`, and compute `Amt = Qnty * SalesPrice` and `PurAmt = Qnty * PurPrice` (S7).
  - Run the stock check, the detail INSERT and `Qnty = Qnty - ?` in one `db_tx`. Guard the decrement with `WHERE ItemID = ? AND Qnty >= ?` and check the affected rows (D2).
  - Record in the report that this is an intentional change: values typed into the read-only fields are now ignored. The normal flow must give identical numbers.
- **Remove line:** delete the detail row and restore stock in one `db_tx`. Restore the quantity read from the detail row in the DB, not the `qnty` GET parameter. That parameter is client-controlled today, so this is an intentional change.
- **Open bill:** replace `Date LIKE 'Y-m-d%'` with `Date >= ? AND Date < ? + INTERVAL 1 DAY`. Take the new bill's ID from `db_insert_id()`, not from `ORDER BY SalesID DESC LIMIT 1`, which races between users.
- **Post:** stays a GET form in this phase. S4.1 moves it to POST.
- **Customer `<select>`:** one query, ordered as legacy ordered it (R4). Fixing the load size is Phase 4 UX; this slice only fixes the query.

**Done when (in addition to the common list):**
- Scenarios 1 and 2 match the legacy golden output.
- A crafted POST with `rate=1` stores the catalogue price.
- `curl 'http://127.0.0.1:8123/dictionary/select.php?q=x%27%20OR%201=1--%20'` with no session returns 401. With a session it returns empty.

## S2.4 Purchase and suppliers

**Depends on:** S2.1
**Owns:** `purchase.php`, `dictionary/PurItem.php`, `dictionary/supplier.php`, `AddSupplierName.php`, `Supplier.php`, `listOfSupplier.php`, `report/PurMemo.php`, `report/PurchaseMemo.php`
**Resolves:** S1, S2 (`AddSupplierName.php`), S5, D2, F5, F7 for these files

- These follow the same patterns as S2.3: same dictionary format contract, `db_tx` for detail plus stock, relative stock updates, `db_insert_id()`, and a date range instead of `LIKE`.
- On purchase, `PurPrice` updates from the entered rate, as legacy does. Purchase prices are supposed to come from the user, so they stay client input.
- `AddSupplierName.php` is called by AJAX from `Supplier.php` and `listOfSupplier.php`. Keep its response body format. Guard it with `require_login_json()`.

**Done when (in addition to the common list):** scenarios 3 and 6 (the supplier part) match the legacy golden output.

## S2.5 Items and customers

**Depends on:** S2.1
**Owns:** `add_item.php`, `list_of_item.php`, `add_member.php`, `list_of_member.php`, `report/AutoSuggestDropDown/search.php`
**Resolves:** S1 (unauthenticated `search.php`), S2, S5 for these files

- `search.php`: `LIKE ?` with the bound value `'%' . $term . '%'`. Keep `LIMIT 10` and the `<tr><td class="re">` markup that `primary.js` parses. Escape the names with `e()`.
- Renaming a customer changes `Nickname`, but sales and collections still reference the old name (D4). Keep the legacy behaviour and add a visible warning on the edit form. The ID migration is optional work after Phase 4.

**Done when (in addition to the common list):** scenario 6 (item and customer parts) matches the legacy golden output.

## S2.6 Cash, payments, accounts

**Depends on:** S2.1
**Owns:** `Payment.php`, `remove_payment.php`, `Accounts.php`, `CashCollection.php`, `Expenses.php`, `HeadOfAccount.php`
**Resolves:** P2 (`Payment.php`), S1, S2, S5 for these files

- `Payment.php` customer list: replace `netTotal()` per customer with one query that `LEFT JOIN`s two pre-aggregated subqueries (`SUM(Total)` from `canteen_sales` grouped by `CustomerName`, `SUM(amount)` from `canteen_collection` grouped by `Description`) onto `canteen_customar`. The legacy printed `$due - $paid`, with `NULL` treated as 0. Print exactly what legacy printed, including the number format (e.g. `-0`, decimals), as the characterization shows. Keep the single-customer `netTotal()` for the detail panel, as one query.
- `remove_payment.php`: `require_admin()`. Legacy alerted and redirected non-admins; keep that UX. Fix the `payment.php` → `Payment.php` link case.
- `Accounts.php`: its month and year sums use `Date >= ? AND Date < ?` ranges. The legacy `date('Y-m-31')` bug (it gives an invalid date for 30-day months) is reproduced only if characterization proves legacy output depended on it. Otherwise compute the real month end and list that as an intentional change.

**Done when (in addition to the common list):**
- `Payment.php` without a customer selected runs ≤ 5 queries (legacy: ~700).
- Scenarios 4 and 5 match the legacy golden output.

## S2.7 Admin and stock snapshots

**Depends on:** S2.1
**Owns:** `Unpost.php`, `SavePrestntStock.php`, `updateClosingBalance.php`, `report/updateClosingBalance.php`, `Report.php`
**Resolves:** S3, P3, P4, P8 (a limit only), S2, F3 (`Report.php` links) for these files

- `Unpost.php`: `require_admin()` with `exit`, which resolves S3. Intentional change: scenario 7 as non-admin now changes nothing. Per **Q2**, hard-delete the header and its lines in one `db_tx`, reversing stock from stored detail quantities: add back sales quantities and subtract purchase quantities. Stock changes happen during line entry, so reversal applies to both posted and unposted bills. Lock the bill and affected items consistently; a repeated delete must not reverse stock twice. A failure rolls back the entire operation. No soft delete.
- `SavePrestntStock.php`:
  - `require_login()`. Every user can reach it from `Report.php` today, so that stays. Making it admin-only is S4.1's call.
  - Delete the wasted `itemPur`/`itemSales` calls (P3).
  - Write the snapshot as one `INSERT INTO canteen_stock_detail (...) SELECT ... FROM canteen_item WHERE D='0' AND Qnty > 0` inside one `db_tx` with the `canteen_stock` row, using `db_insert_id()` for `SlNo`.
- Delete `updateClosingBalance.php` and `report/updateClosingBalance.php` per **Q6**. Remove any references and mark them removed in the characterization inventory; do not port them.
- `Report.php`: fix all 19 `Report/` links. Validate the posted date and time and fall back to today when they're invalid. Legacy `date_create()` on garbage silently produced today.

**Done when (in addition to the common list):**
- Scenarios 7 and 8 match the legacy golden output except the documented authorization and hard-delete changes in scenario 7. Verify header and lines disappear, stock reverses once, repeated deletion changes nothing, and a forced failure rolls back everything. Hand off these scenario assertions to the tools owner.
- `SavePrestntStock.php` runs ≤ 5 queries (legacy: ~2,000).

## S2.8 Product and stock reports

**Depends on:** S2.1
**Owns:** `report/ListOfItem.php`, `report/ListOfItemWithQun.php`, `report/list_item_with_minimum.php`, `report/list_item_with_maximum.php`, `report/ItemLedgr.php`, `report/closingBalance.php`, `report/savedClosingBalance.php`, `report/ledger.php`, `report/index.php`
**Resolves:** S2 (report pages), F10, P-loops in these files, F2 (`split`, by deletion)

- `closingBalance.php` and `savedClosingBalance.php`: the per-group `item()` loops become one ordered query, grouped in PHP. The totals must stay byte-identical, which is where F10 and DEC4 matter.
- `ItemLedgr.php`: the opening balance and the period rows each become one query.
- Delete `report/ledger.php` (broken, D7) and `report/index.php` (age calculator) per **Q6**. Remove references and mark them removed in the characterization inventory.

**Done when:** the common list passes for every owned label, in both report ranges.

## S2.9 Sales, purchase and cash reports

**Depends on:** S2.1
**Owns:** `report/salesOfaPeriod-Bill.php`, `report/salesOfaPeriod-itemName.php`, `report/salesOfaPeriod-itemName-By-user.php`, `report/salesOfaPeriod-supplierName.php`, `report/sales_of_a_period_category_wise.php`, `report/SendToProcessOfaPeriod.php`, `report/Purchase of a period.php`, `report/Purchase-of-a-piriod-by-Supplier.php`, `report/PurchaseOfaPeriod-Bill-wise.php`, `report/UnpostedBill.php`, `report/CashCollectionOfaPeriod.php`, `report/CashExpensesOfaPeriod.php`, `report/Employee ledgr-Summary.php`, `report/list_of_members.php`
**Resolves:** F6 (all seven files), S2 (report pages), P2 (`list_of_members.php`), F10

- This is the hardest R5 work. For each `SELECT *, SUM() GROUP BY`, list in the report the columns the page prints and how each one is now derived.
- `SendToProcessOfaPeriod.php` has a baseline query failure: it filters `canteen_sales.Date` without joining that table. Repair the join/date source deliberately and record the resulting nonempty report as an intentional behavior change rather than preserving the broken empty output.
- Three file names contain spaces. Quote them in every command.

**Done when:** the common list passes for every owned label, in both report ranges.

## S2.10 Integration sweep

**Depends on:** S2.2–S2.9 merged
**Owns:** anything left over: `unsused_Backups/`, `tools/characterize/` (inventory updates), deletions agreed in Q6. Cross-file fixes reported as hand-offs by S2.2–S2.9.
**Resolves:** F1, F2, F4 repo-wide; closes the open hand-offs

Steps:
1. Apply every hand-off from the S2.2–S2.9 reports.
2. Verify S2.7/S2.8 deleted the four pages listed in Q6. Preserve any remaining fixture dumps outside git, then remove the entire `unsused_Backups/` directory and its references; S4.2 replaces its backup functionality. Update the inventory so removed pages are no longer required to render or redirect.
3. Run the full suite against modern: all pages, both report ranges, all scenarios, perf.
4. Write `docs/perf/phase2.md`: the modern perf table next to the legacy baseline and phase 1.

**Done when:**
- `grep -rnE "mysql_|split\(|get_magic_quotes|Asia/Dacca|Report/" --include='*.php' .` returns nothing.
- `tools/lint.sh $(git ls-files '*.php')` is clean.
- The full `compare.sh` (pages and scenarios) has zero diffs except the consolidated list of intentional changes. That list is written to `docs/plan/phase-2-intentional-changes.md` for the owner to sign off.
- `_errors.txt` on modern is empty at `E_ALL`.
- Every remaining `page` from the updated S0.3 inventory redirects to `login.php` without a session. Every `endpoint` returns 401.
