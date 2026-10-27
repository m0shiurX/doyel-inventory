# Write-flow characterization

These scenarios exercise the real forms over HTTP and read their database effects.
They reset an isolated local fixture before **each** scenario, so they erase that
disposable database. Never point application test servers at production data.
The legacy source remains the frozen `baseline-legacy` archive on port 8056.

```bash
export DOYEL_FIXTURES=/private/tmp/doyel-fixtures
tools/scenarios/run-all.sh http://127.0.0.1:8056 legacy "$DOYEL_FIXTURES/golden/legacy/scenarios"
tools/scenarios/run-all.sh http://127.0.0.1:8056 legacy "$DOYEL_FIXTURES/checks/legacy-scenarios"
tools/characterize/compare.sh "$DOYEL_FIXTURES/golden/legacy/scenarios" "$DOYEL_FIXTURES/checks/legacy-scenarios"
```

Output directories must be new or empty and lie within `DOYEL_FIXTURES`, outside
git. Each shell script supports `-h`. For a single flow, use its numbered script:

```bash
tools/scenarios/01-sale.sh http://127.0.0.1:8056 legacy "$DOYEL_FIXTURES/checks/sale-only"
DOYEL_DB_NAME=doyel_s23 tools/scenarios/01-sale.sh http://127.0.0.1:8123 modern "$DOYEL_FIXTURES/checks/modern-sale"
```

Legacy HTTP is restricted to loopback port 8056; modern HTTP to loopback ports
8100–8199. DB connections use the corresponding local Compose MySQL port and
local-only test credentials. Database names must be `software` or `doyel_*`.
`DOYEL_DB_NAME` must match the server's configured fixture database. Modern servers
start with `tools/serve-modern.sh`. Reset goes through `tools/db-reset.sh` and
never accepts a remote DB host. Inputs are validated before reset. Every HTTP
request rejects transport errors, HTTP errors and fatal PHP errors; modern runs
also reject warnings/notices/deprecations. Login requires both its redirect and
an authenticated dashboard response. Do not run scenarios concurrently against
the same database, or while performance logging/capture uses that database.

The PHP runner uses SQL **only for reads/assertions**; `db-reset.sh` performs the
specified fixture import and test-password setup. Business writes are requests
with the actual form field names/methods. New-bill and new-record assertions read
the selected database and reject absent mutations, preventing a wrong DB/server
pair from silently passing. Later POST/CSRF conversion must update these requests
and retain the DB contracts; no compatibility endpoint is introduced for tests.

## Scenarios and coverage

| Script | HTTP flow and DB contract |
|---|---|
| `01-sale.sh` | New sale, dictionary suggestions and slash lookup, two lines, remove/re-add, discount/note, post; header/detail rows plus exact decrements on touched stock. |
| `02-oversell.sh` | Quantity above available stock; zero detail rows and unchanged stock. |
| `03-purchase.sh` | New bill, line with a changed purchase price, discount/post; header/detail rows, stock increment and current purchase price. |
| `04-customer-collection.sh` | Negative admin collection stays negative; non-admin becomes absolute; non-admin payment removal leaves row, admin removal deletes it. Before/after scoped collections retained. |
| `05-cash.sh` | Add/edit account head, cash receipt and expense, then each cash-page delete; snapshots before/after deletes prove entries were created and removed. |
| `06-master-data.sh` | Add/edit item and customer; add supplier through AJAX endpoint, supplier payment, add user and verify its credentials by HTTP login; scoped rows in all six affected tables. |
| `07-admin-bills.sh` | Existing posted sale and purchase: unpost/delete as admin, then another of each as non-admin; before/after headers, lines and touched stock. Legacy unauthorized deletion and orphan lines are recorded deliberately. |
| `08-stock-snapshot.sh` | Save Present Stock; new stock headers and per-snapshot count/sum quantity/sum value, avoiding a huge row-by-row dump. |
| `09-password.sh` | Change password and log in with the new password; changed hash asserted and scoped user record captured. |

Every write-only route from the S0.3 inventory is covered: `AddSupplierName.php`
(scenario 6), `remove_payment.php` (4), and `SavePrestntStock.php` (8). Mixed CRUD
pages covered are `Sales.php` (1/2), `purchase.php` (3), `Payment.php` (4),
`CashCollection.php`, `Expenses.php`, `HeadOfAccount.php` (5), `add_item.php`,
`add_member.php`, `Supplier.php`, `list_of_user.php` (6), `Unpost.php` (7), and
`password.php` (9). Login/logout occurs during role changes and credential checks.
`Report.php`'s session-only date-range POST is covered by S0.3; it changes no
business table. Read-only report, list and dictionary pages have no business
write to exercise. The four Q6 dead pages were removed in preparation.

## Output and privacy

Each flow writes one numbered `.txt` file with labelled JSON row lists. SELECTs
name columns explicitly and order by the primary key, selecting only new records
or the IDs actually touched. Scenario 7 includes before/after snapshots of those
existing IDs; scenario 4/5 includes checkpoints so deleted entries are not hidden
by an empty final result. Numeric columns are identified from schema metadata; only fractional trailing
zeroes and the resulting decimal point are trimmed (`10.00` → `10`) using string
operations. This losslessly normalizes FLOAT/DECIMAL representation without float
casts, rounding or removing meaningful precision. Numeric-looking names stay
untouched. Snapshot summary numeric fields use the same explicit normalization.
PDO stringifies server values to match the legacy API. `Date` and creation `m_date`
values become `<ts>`; historical timestamps are not compared in these write-only
contracts. Hashes in scenarios 6/9 become `<password-verified>` **only after** an
HTTP login verifies the new credentials; this permits Phase 2's randomized secure
hashes without losing credential verification. Every other field stays intact apart from the lossless numeric representation
normalization above. Page-rendered formatting is still checked separately by S0.3.

Resolved client names are read from the local fixture at runtime. New records use
obviously synthetic `Scenario ...` labels. Real client values occur only in the
private results; neither client names nor captured hashes belong in source or
reports. Private step HTML and reset logs use `.private` extensions. The runner extracts
all PHP diagnostics from those bodies into private `_errors.txt`, separately from
business output; the legacy baseline has 72 diagnostics on each complete run.
The comparer reports them separately, and modern HTTP requests fail on any PHP
diagnostic. Cookies are
removed after each successful flow, and files/directories are created under umask
077. Raw comparison diffs can contain client records: redirect a failing diff to
the fixture directory rather than pasting it into a report.

## Known legacy behavior and intentional future changes

The legacy app rejects the tested oversell. It removes bills for non-admins despite
sending a redirect and leaves orphan detail rows without reversing stock. S2.7
must intentionally change those results. Its fixture-specific orphan assertions
apply only to the legacy target; the modern slice must add atomic-delete/reversal
and repeated-request assertions. Removing cash-page rows currently uses GET;
S4.1 changes those and stock/new-bill actions to POST/CSRF. Scenario 6 omits the
admin item's disabled `totalQnty` form control as a browser does: legacy PHP warns
about the missing key but stores zero; Phase 2 must remove the warning without
inventing a stock adjustment. All legacy step HTML is preserved privately.
