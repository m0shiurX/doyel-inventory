# Read-only characterization

Run after resetting the isolated fixture. All outputs contain private records and
must stay under the external fixture directory, never in git:

```bash
export DOYEL_FIXTURES=/private/tmp/doyel-fixtures
tools/db-reset.sh legacy
tools/characterize/capture.sh http://127.0.0.1:8056 "$DOYEL_FIXTURES/golden/legacy/pages"
tools/db-reset.sh legacy
tools/characterize/capture.sh http://127.0.0.1:8056 "$DOYEL_FIXTURES/checks/legacy-pages"
tools/characterize/compare.sh "$DOYEL_FIXTURES/golden/legacy/pages" "$DOYEL_FIXTURES/checks/legacy-pages"
```

Output directories must be new/empty; use a fresh comparison directory each run.
Set `DOYEL_DB_HOST`, `DOYEL_DB_PORT`, `DOYEL_DB_USER`, `DOYEL_DB_PASS`, and
`DOYEL_DB_NAME` to match the server under test. Defaults are the local legacy
fixture (`127.0.0.1:33057`, database `software`, root/local-only password).
For a modern slice, set port `33084` and its `doyel_sNN` database. Resolver reads
that database through PDO and rejects remote or non-fixture DB names. The chosen
item has real sales, the chosen customer has sales and collections, the purchase
has detail rows, and the selected unposted sale and saved snapshot exist. Source
contains symbolic placeholders; resolved name-bearing requests live only in the
private `_requests.private` file. Do not print that manifest or extracted records
in reports. Test login defaults to the Phase 0 admin fixture; `DOYEL_TEST_USER`
and `DOYEL_TEST_PASS` can override it.

`urls.txt` gives neutral label, method, encoded path and optional encoded form
body. All page/endpoint files are covered; parameterized variants exercise posted
sale 7559, an unposted sale, a purchase, customer dues, item edit, item ledger and
saved stock. `Report.php` receives `start_date`, `end_date`, `start_time` and
`end_time` before **each** report range: full history 2016-01-01 through
2026-10-25, and September 2026. Every report page is captured in both ranges.
The historical `PurchaseMemo.php` actually reads sales details; its fixture uses
sale 7559 to preserve that legacy behavior. `logout.php` runs last.

The capture runner uses local curl without following redirects, checks login's
302, records every HTTP status, and fails on HTTP errors or transport failures.
It writes `_complete.json` only after all labels succeed. The comparer verifies
that completion count and HTTP label set, rejects empty runs, and compares the
union of labels (including `_status.txt`) so missing/extra pages cannot pass.
Scenario directories can use the same comparer without a page completion marker.
For an intermediate Phase 2 slice, explicitly set `DOYEL_CAPTURE_LABELS` to a
comma-separated list of owned **base** labels, e.g. `dashboard,logout`. Unknown
or duplicate labels fail. Reports still get both range suffixes; authentication
and range setup occur even when their own labels are not selected. Default runs
always cover the entire manifest. To compare a subset, copy only matching label
`.txt` files from the private golden/candidate captures into fresh private
comparison directories, omitting metadata; this gives the same comparer exactly
the owned set and still detects missing/extra labels. Never describe a subset
check as full-suite verification. Full integration uses unfiltered captures.
PHP diagnostics stay in private `_errors.txt` and are counted separately; they
are excluded from business-text comparison so Phase 2 can remove legacy warnings.
A modern run still requires an empty error file. Raw diffs may contain private
records: redirect them inside `$DOYEL_FIXTURES` when they differ.

## Extraction and normalization

`extract.php [page-path]` reads HTML on stdin with DOMDocument. It emits titles,
block text, each table row with cells joined by ` | `, visible input values,
textarea values and the selected option's text. Scripts, styles, comments,
inline-hidden elements, hidden inputs and password inputs are not displayed text.
Hidden protocol fields are covered through write-flow DB results rather than
visible output. CSS stylesheets and JavaScript are not executed; browser keyboard
and visual behavior belongs to S4.4.

The single normalization table names each rule: the menu's signed-in title,
Report's two default date inputs, and Accounts' six wall-clock period summaries.
Historical bill/report dates, saved snapshots, item quantities and cash-in-hand
are preserved. Dictionary array endpoints compare ordered suggestion strings,
ignoring the legacy trailing-comma/whitespace syntax that the planned JSON port
changes; slash-delimited item lookup remains unchanged text.

## Application PHP inventory

Tooling PHP under `tools/` is not an application route. Mixed read/write pages are
classified as pages for their safe GET rendering; S0.4 exercises their writes.

| File | Classification | Coverage/reason |
|---|---|---|
| `Accounts.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `AddSupplierName.php` | write | S0.4; intentionally excluded from read-only capture |
| `CashCollection.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `Expenses.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `HeadOfAccount.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `Payment.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `Report.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `Sales.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `SavePrestntStock.php` | write | S0.4; intentionally excluded from read-only capture |
| `Supplier.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `Unpost.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `add_item.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `add_member.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `connect.php` | include | Shared helper; not directly requested |
| `dictionary/PurItem.php` | endpoint | Captured with fixture-resolved input |
| `dictionary/SalesItem.php` | endpoint | Captured with fixture-resolved input |
| `dictionary/select.php` | endpoint | Captured with fixture-resolved input |
| `dictionary/supplier.php` | endpoint | Captured with fixture-resolved input |
| `footer.php` | include | Shared helper; not directly requested |
| `index.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `listOfSupplier.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `list_of_item.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `list_of_member.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `list_of_user.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `login.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `logout.php` | page | Captured last; ends only the test HTTP session |
| `menu.php` | include | Shared helper; not directly requested |
| `password.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `purchase.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `remove_payment.php` | write | S0.4; intentionally excluded from read-only capture |
| `report/AutoSuggestDropDown/search.php` | endpoint | Captured with fixture-resolved input |
| `report/CashCollectionOfaPeriod.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `report/CashExpensesOfaPeriod.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `report/Employee ledgr-Summary.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `report/ItemLedgr.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `report/ListOfItem.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `report/ListOfItemWithQun.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `report/PurMemo.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `report/Purchase of a period.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `report/Purchase-of-a-piriod-by-Supplier.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `report/PurchaseMemo.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `report/PurchaseOfaPeriod-Bill-wise.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `report/SalesMemo.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `report/SendToProcessOfaPeriod.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `report/UnpostedBill.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `report/closingBalance.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `report/list_item_with_maximum.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `report/list_item_with_minimum.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `report/list_of_members.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `report/menu.php` | include | Shared helper; not directly requested |
| `report/salesOfaPeriod-Bill.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `report/salesOfaPeriod-itemName-By-user.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `report/salesOfaPeriod-itemName.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `report/salesOfaPeriod-supplierName.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `report/sales_of_a_period_category_wise.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |
| `report/savedClosingBalance.php` | page | Read-only GET/form rendering; writes covered separately by S0.4 |

The approved dead pages were removed before `baseline-legacy`:
`report/ledger.php`, `report/index.php`, `updateClosingBalance.php`,
`report/updateClosingBalance.php`, and `unsused_Backups/backup.php`. They are not
rendering requirements or write-scenario requirements.

## Known legacy error baseline

Two fresh-reset captures each produced **76 labels** and **79 diagnostics**.
The 76 labels plus HTTP status contract compare as `77 same, 0 differ, 0 missing`.
Their raw diagnostic files are byte-identical. Requests returned 75 HTTP 200s and
one HTTP 302 (logout); no transport, HTTP error or fatal PHP failure occurred.

| Category | Relative source | Count across both report ranges in one capture | Cause |
|---|---|---:|---|
| Deprecated | `connect.php:10` | 75 | Legacy mysql extension connection |
| Notice | `report/PurchaseMemo.php:37` | 2 | Uninitialized alternating-row bgcolor |
| Warning | `report/SendToProcessOfaPeriod.php:40` | 2 | mysql_num_rows receives failed query |

The factory report's query references `canteen_sales.Date` without joining that
table, so its historical report is empty in both ranges. S2.9 must document the
intentional repair rather than conceal the difference. The committed summary
contains only categories and relative source locations; private `_errors.txt`
retains complete diagnostic text. Removing the legacy diagnostic text from the
business-text extraction is explicit; modern acceptance still requires zero
PHP diagnostics. Fixtures and captures remain outside git.

## PHP lint and intermediate foundation session

`tools/lint.sh <file.php> [...]` runs PHP syntax checks and stops on failure.
During S2.1, login is not ported yet. A private CLI harness can create a fixture
session and Netscape cookie jar under `DOYEL_FIXTURES`; set
`DOYEL_CAPTURE_COOKIE_JAR` to that private file with an explicit
`DOYEL_CAPTURE_LABELS=dashboard,logout`. This opt-in works only for local modern
ports 8100–8199 and excludes the login label. It copies the jar to the private
capture directory and never adds a test login HTTP endpoint. Normal capture
continues authenticating through the actual login form; use it after S2.2.
