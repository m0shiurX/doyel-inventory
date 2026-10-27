# Phase 1: Index migration results (S1.1)

Measured on 2026-10-27, same macOS/OrbStack host, PHP 5.6.40 and MySQL 5.7.44
linux/amd64 legacy stack as the Phase 0 baseline. Application source is the frozen
`baseline-legacy` archive; this slice changes no application PHP, HTML or JS.
These local measurements do not predict Windows production timing.

## Migration and verification

`001_indexes.up.sql` adds **19 secondary indexes across seven tables** and an
`INT AUTO_INCREMENT` primary key on the 190,207-row stock detail table. The first
complete application took **2.297 seconds** on the local fixture. There is one
ALTER per table, no conditional/invisible/functional indexes, no online-DDL
clauses and no charset change. Syntax and key widths meet the MySQL/MariaDB 5.5
floor; actual execution here is MySQL 5.7.44, not a claimed 5.5/MariaDB test.
The widest declared secondary key is collection Description (100 UTF-8 characters,
300 bytes), below the old 767-byte limit. All 19 new secondary indexes appear in
actual EXPLAIN plans; no FORCE INDEX was used.

| Table | Added secondary key columns | Serves |
|---|---|---|
| canteen_sales | CustomerName; UserName/Post/Date; Post/Date; Date | Customer dues, open/per-user bills, posted periods, selective date-only reports |
| canteen_sales_detail | SalesID; ItemID/Date; Date | Bill totals, item ledger/stock sums, selective detail periods |
| canteen_purchase | UserName/Post/Date; Post/Date; Supplier/Date | Open/per-user/period bills, supplier totals and recent purchases |
| canteen_purchase_detail | BillNo; ItemID/Date | Bill totals and item ledger/stock sums |
| canteen_collection | Description; Date; HeadOfAccount/Date | Customer payments, recent/period entries and head ledgers |
| canteen_expenses | Date; HeadOfAccount/Date; Description/Date | Recent/period expenses, head and supplier ledgers |
| canteen_stock_detail | SlNo/SupplierName, plus new ID primary key | Saved-stock grouping, rows and summary |

Supplier/Date and expense Description/Date extend the proposed single-name keys
because the actual corresponding ledger also orders/filters Date. Date on sales
serves the real date-only grouped report under a selective range. No indexes were
added to the small lookup tables or to already indexed primary-key/name lookups.

An initial `up → down → up` rehearsal compared SHOW CREATE TABLE for all 13
tables: down exactly matched the original JSON, and re-up exactly matched first
up. Fixture reset without the migration variable also reproduced the exact
original schema. The fully indexed characterization has **77 same, 0 differ,
0 missing**, covering 76 visible outputs plus HTTP statuses. All nine HTTP write
scenarios match their golden DB results: **9 same, 0 differ, 0 missing**, with
85 HTTP steps and unchanged legacy diagnostics (79 page, 72 scenario diagnostics).

The scenario runner resets before each flow. The narrow, owner-authorized
`DOYEL_DB_MIGRATIONS=001` hook reapplies the source migration after **every**
reset using the server's normal SQL mode. All nine private reset logs confirm
001 applied; a failed migration aborts the runner. Unset keeps the original reset.
Malformed, duplicate, missing or ambiguous IDs are rejected before DROP; paths
are resolved only under repository db/migrations and symlinks are rejected.
No private SQL dump is admitted by this source-migration exception.

## EXPLAIN inventory

The read-only `tools/phase1-check.php` uses PHP tokens to collect **130 distinct
SELECT/UPDATE/DELETE source templates**, skipping comments and form-option text.
Six selective period variants supplement the full-history examples. It resolves
representative values from the fixed local fixture, completes the two concatenated
login/password query fragments, and writes all SQL/parameters/plans **privately**.
Unknown non-key write payload fields use harmless representative literals for
EXPLAIN only; no source UPDATE/DELETE is executed. The helper pins both its
observer and parameter resolver to local legacy `software`, rejects multi-
statements, and writes only beneath the external fixture directory.

Of 136 evidence entries, 134 explain successfully. Two existing invalid source
shapes remain invalid before/after: the live factory report's unjoined sales Date
and the unused purchase-detail `GROUP BY ItemName` helper (that column is absent).
The former is already a documented S2.9 repair. Indexes cannot repair these queries.

Below is every source shape whose baseline plan scanned a table above 1,000
estimated rows, plus the selective variants. Each plan cell is
`table: access/key/estimated rows`; estimates are optimizer statistics, not exact
row counts. IDs are reproducible in the private before/after evidence.

| Shape / first source | Before | After |
|---|---|---|
| Q012 `CashCollection.php:81` | canteen_collection: ALL/—/6880 | canteen_collection: index/idx_collection_date/30 |
| Q014 `report/CashCollectionOfaPeriod.php:65` | canteen_collection: ALL/—/6880 | canteen_collection: range/idx_collection_head_date/3440 |
| Q015 `CashCollection.php:85` | canteen_collection: ALL/—/6880 | canteen_collection: index/idx_collection_date/30 |
| Q020 `Expenses.php:85` | canteen_expenses: ALL/—/10457 | canteen_expenses: index/idx_expenses_date/30 |
| Q021 `Supplier.php:184` | canteen_expenses: ALL/—/10457 | canteen_expenses: ref/idx_expenses_description_date/1 |
| Q022 `report/CashExpensesOfaPeriod.php:65` | canteen_expenses: ALL/—/10457 | canteen_expenses: range/idx_expenses_head_date/1870 |
| Q023 `Expenses.php:88` | canteen_expenses: ALL/—/10457 | canteen_expenses: index/idx_expenses_date/30 |
| Q037 `report/PurchaseOfaPeriod-Bill-wise.php:65` | canteen_purchase: ALL/—/2218 | canteen_purchase: range/idx_purchase_post_date/1870 |
| Q038 `report/UnpostedBill.php:52` | canteen_purchase: ALL/—/2218 | canteen_purchase: ref/idx_purchase_post_date/348 |
| Q039 `report/Purchase of a period.php:34` | canteen_purchase: ALL/—/2218 | canteen_purchase: range/idx_purchase_post_date/1870 |
| Q040 `report/Purchase-of-a-piriod-by-Supplier.php:35` | canteen_purchase: ALL/—/2218 | canteen_purchase: range/idx_purchase_supplier_date/5 |
| Q041 `Supplier.php:141` | canteen_purchase: ALL/—/2218 | canteen_purchase: ref/idx_purchase_supplier_date/5 |
| Q042 `purchase.php:139` | canteen_purchase: ALL/—/2218 | canteen_purchase: ref/idx_purchase_user_post_date/73 |
| Q043 `report/PurchaseOfaPeriod-Bill-wise.php:61` | canteen_purchase: ALL/—/2218 | canteen_purchase: range/idx_purchase_user_post_date/429 |
| Q047 `report/salesOfaPeriod-Bill.php:61` | canteen_sales: ALL/—/5346 | canteen_sales: ALL/—/5346 |
| Q048 `report/UnpostedBill.php:27` | canteen_sales: ALL/—/5346 | canteen_sales: ref/idx_sales_post_date/104 |
| Q051 `Sales.php:175` | canteen_sales: ALL/—/5346 | canteen_sales: ref/idx_sales_user_post_date/50 |
| Q052 `report/salesOfaPeriod-Bill.php:58` | canteen_sales: ALL/—/5346 | canteen_sales: range/idx_sales_user_post_date/1156 |
| Q057 `report/savedClosingBalance.php:88` | canteen_stock_detail: ALL/—/190207 | canteen_stock_detail: ref/idx_stock_snapshot_supplier/224 |
| Q061 `report/sales_of_a_period_category_wise.php:53` | canteen_sales_detail: ALL/—/26486; canteen_item: eq_ref/PRIMARY/1; canteen_sales: eq_ref/PRIMARY/1 | canteen_item: ALL/—/868; canteen_sales_detail: ref/idx_sales_detail_item_date/43; canteen_sales: eq_ref/PRIMARY/1 |
| Q062 `report/salesOfaPeriod-itemName-By-user.php:76` | canteen_sales: ALL/—/5346; canteen_sales_detail: ALL/—/26486; canteen_item: eq_ref/PRIMARY/1 | canteen_sales: ref/idx_sales_user_post_date/1156; canteen_sales_detail: ref/idx_sales_detail_bill/5; canteen_item: eq_ref/PRIMARY/1 |
| Q064 `report/salesOfaPeriod-supplierName.php:71` | canteen_sales_detail: ALL/—/26486; canteen_item: eq_ref/PRIMARY/1 | canteen_item: ALL/—/868; canteen_sales_detail: ref/idx_sales_detail_item_date/43 |
| Q065 `report/salesOfaPeriod-itemName.php:33` | canteen_sales_detail: ALL/—/26486; canteen_item: eq_ref/PRIMARY/1; canteen_sales: eq_ref/PRIMARY/1 | canteen_sales_detail: ALL/—/26486; canteen_item: eq_ref/PRIMARY/1; canteen_sales: eq_ref/PRIMARY/1 |
| Q067 `report/ItemLedgr.php:80` | canteen_purchase_detail: ALL/—/12230; canteen_sales_detail: ALL/—/26486; <union1,2>: ALL/—/— | canteen_purchase_detail: range/idx_purchase_detail_item_date/182; canteen_sales_detail: range/idx_sales_detail_item_date/560; <union1,2>: ALL/—/— |
| Q072 `report/CashCollectionOfaPeriod.php:33` | canteen_collection: ALL/—/6880 | canteen_collection: index/idx_collection_head_date/6880 |
| Q073 `report/CashExpensesOfaPeriod.php:33` | canteen_expenses: ALL/—/10457 | canteen_expenses: index/idx_expenses_head_date/10457 |
| Q076 `report/ItemLedgr.php:107` | canteen_purchase_detail: ALL/—/12230 | canteen_purchase_detail: range/idx_purchase_detail_item_date/1 |
| Q077 `SavePrestntStock.php:47` | canteen_purchase_detail: ALL/—/12230 | canteen_purchase_detail: ref/idx_purchase_detail_item_date/182 |
| Q078 `report/ItemLedgr.php:120` | canteen_sales_detail: ALL/—/26486 | canteen_sales_detail: range/idx_sales_detail_item_date/1 |
| Q079 `SavePrestntStock.php:58` | canteen_sales_detail: ALL/—/26486 | canteen_sales_detail: ref/idx_sales_detail_item_date/560 |
| Q092 `purchase.php:308` | canteen_purchase_detail: ALL/—/12230 | canteen_purchase_detail: ref/idx_purchase_detail_bill/2 |
| Q093 `Sales.php:377` | canteen_sales_detail: ALL/—/26486 | canteen_sales_detail: ref/idx_sales_detail_bill/9 |
| Q094 `report/UnpostedBill.php:71` | canteen_purchase_detail: ALL/—/12230 | canteen_purchase_detail: ref/idx_purchase_detail_bill/2 |
| Q095 `report/UnpostedBill.php:80` | canteen_sales_detail: ALL/—/26486 | canteen_sales_detail: ref/idx_sales_detail_bill/7 |
| Q096 `Payment.php:86` | canteen_sales: ALL/—/5346 | canteen_sales: ref/idx_sales_customer/117 |
| Q097 `report/SalesMemo.php:114` | canteen_sales: ALL/—/5346 | canteen_sales: ref/idx_sales_customer/117 |
| Q098 `Supplier.php:100` | canteen_purchase: ALL/—/2218 | canteen_purchase: ref/idx_purchase_supplier_date/5 |
| Q099 `Accounts.php:95` | canteen_collection: ALL/—/6880 | canteen_collection: ALL/—/6880 |
| Q100 `Accounts.php:73` | canteen_collection: ALL/—/6880 | canteen_collection: ALL/—/6880 |
| Q101 `Accounts.php:82` | canteen_expenses: ALL/—/10457 | canteen_expenses: ALL/—/10457 |
| Q102 `Accounts.php:91` | canteen_expenses: ALL/—/10457 | canteen_expenses: ALL/—/10457 |
| Q103 `Payment.php:90` | canteen_collection: ALL/—/6880 | canteen_collection: ref/idx_collection_description/102 |
| Q104 `report/SalesMemo.php:118` | canteen_collection: ALL/—/6880 | canteen_collection: ref/idx_collection_description/102 |
| Q105 `Supplier.php:107` | canteen_expenses: ALL/—/10457 | canteen_expenses: ref/idx_expenses_description_date/1 |
| Q107 `report/savedClosingBalance.php:41` | canteen_stock_detail: ALL/—/190207 | canteen_stock_detail: ref/idx_stock_snapshot_supplier/224 |
| Q110 `report/PurchaseOfaPeriod-Bill-wise.php:31` | canteen_purchase: ALL/—/2218 | canteen_purchase: ref/idx_purchase_post_date/1870 |
| Q111 `report/salesOfaPeriod-Bill.php:29` | canteen_sales: ALL/—/5346 | canteen_sales: ref/idx_sales_post_date/5164 |
| Q112 `report/Employee ledgr-Summary.php:37` | canteen_sales: ALL/—/5346 | canteen_sales: index/idx_sales_user_post_date/5346 |
| Q113 `report/salesOfaPeriod-itemName-By-user.php:37` | canteen_sales: ALL/—/5346 | canteen_sales: index/idx_sales_user_post_date/5346 |
| Q115 `purchase.php:290` | canteen_purchase_detail: ALL/—/12230; canteen_item: eq_ref/PRIMARY/1 | canteen_purchase_detail: ref/idx_purchase_detail_bill/2; canteen_item: eq_ref/PRIMARY/1 |
| Q116 `Sales.php:360` | canteen_sales_detail: ALL/—/26486; canteen_item: eq_ref/PRIMARY/1 | canteen_sales_detail: ref/idx_sales_detail_bill/9; canteen_item: eq_ref/PRIMARY/1 |
| Q118 `report/savedClosingBalance.php:54` | canteen_stock_detail: ALL/—/190207 | canteen_stock_detail: ref/idx_stock_snapshot_supplier/224 |
| sales-date-month `selective variant of period-query shapes` | canteen_sales: ALL/—/5346 | canteen_sales: range/idx_sales_date/6 |
| sales-post-month `selective variant of period-query shapes` | canteen_sales: ALL/—/5346 | canteen_sales: range/idx_sales_post_date/6 |
| purchase-post-month `selective variant of period-query shapes` | canteen_purchase: ALL/—/2218 | canteen_purchase: range/idx_purchase_post_date/2 |
| sales-detail-month `selective variant of period-query shapes` | canteen_sales_detail: ALL/—/26486 | canteen_sales_detail: range/idx_sales_detail_date/16 |
| collection-month `selective variant of period-query shapes` | canteen_collection: ALL/—/6880 | canteen_collection: range/idx_collection_date/1 |
| expenses-month `selective variant of period-query shapes` | canteen_expenses: ALL/—/10457 | canteen_expenses: range/idx_expenses_date/1 |

Remaining large ALL scans are deliberate broad workloads, not missing selective
indexes: Q047 covers 5,164 posted sales out of 5,268; Q065 includes 26,835 of
26,851 sales details. Q099/Q102 are whole-table cash-in-hand aggregates;
Q100/Q101 are those cash aggregates over the full-history range, which contains
all 6,888 collection and 10,520 expense rows. All five profiled table date ranges
cover 100% of their rows in this fixture. The selective September variants choose
range access. Grouped all-history scans can also correctly walk a complete index.
The item/lookup tables have fewer than 1,000 rows, so small scans remain appropriate.

## Performance against the baseline

Both runs use the same full-history range and profiler protocol: median of five
HTTP requests with app SQL logging enabled, excluding setup/observer SQL. All 52
labels are present; query counts remain constant across samples and unchanged
from Phase 0. Indexes lower SQL cost; the per-customer/per-bill loops remain for
Phase 2. Ratios compare measured medians, not a guaranteed client speed-up.

| Label | Before ms | Indexed ms | Before queries | Indexed queries | Speed ratio |
|---|---:|---:|---:|---:|---:|
| report-sales-bill | 39626.701 | 904.159 | 5166 | 5166 | 43.83× |
| report-sales-user | 19268.206 | 780.012 | 12 | 12 | 24.70× |
| report-purchase-bills | 6476.091 | 328.712 | 1872 | 1872 | 19.70× |
| report-unposted | 1157.704 | 68.931 | 454 | 454 | 16.80× |
| payment-customer-with-dues | 1105.976 | 91.700 | 702 | 702 | 12.06× |
| payment-list | 1018.133 | 87.400 | 697 | 697 | 11.65× |
| report-sales-category | 1013.041 | 813.285 | 11 | 11 | 1.25× |
| report-members | 1008.799 | 89.497 | 697 | 697 | 11.27× |
| report-sales-name | 773.022 | 770.725 | 1 | 1 | 1.00× |
| report-cash-expenses | 237.149 | 87.108 | 45 | 45 | 2.72× |
| report-snapshot | 168.834 | 6.147 | 4 | 4 | 27.46× |
| report-sales-supplier | 113.585 | 78.353 | 2 | 2 | 1.45× |
| report-cash-collection | 86.796 | 67.291 | 9 | 9 | 1.29× |
| report-item-ledger | 26.003 | 10.629 | 4 | 4 | 2.45× |
| accounts | 20.968 | 9.660 | 8 | 8 | 2.17× |
| sale-posted | 17.977 | 6.701 | 5 | 5 | 2.68× |
| sale-unposted | 17.751 | 6.107 | 5 | 5 | 2.91× |
| report-purchases | 16.780 | 17.759 | 1 | 1 | 0.94× |
| report-sales-memo | 15.703 | 6.104 | 6 | 6 | 2.57× |
| report-quantity | 13.189 | 13.225 | 11 | 11 | 1.00× |
| admin-bills | 12.585 | 12.818 | 2 | 2 | 0.98× |
| report-purchase-detail | 12.339 | 4.553 | 1 | 1 | 2.71× |
| supplier-payment | 11.557 | 6.716 | 5 | 5 | 1.72× |
| expenses | 11.498 | 5.975 | 2 | 2 | 1.92× |
| purchase-posted | 10.595 | 5.091 | 4 | 4 | 2.08× |
| report-accountants | 10.260 | 9.249 | 1 | 1 | 1.11× |
| report-items | 10.031 | 10.686 | 3 | 3 | 0.94× |
| report-closing | 9.047 | 9.070 | 4 | 4 | 1.00× |
| cash-collection | 8.785 | 5.809 | 2 | 2 | 1.51× |
| report-maximum | 8.660 | 8.649 | 7 | 7 | 1.00× |
| report-purchase-memo | 8.533 | 4.684 | 2 | 2 | 1.82× |
| item-list | 7.393 | 7.710 | 1 | 1 | 0.96× |
| customer-list | 6.801 | 7.016 | 1 | 1 | 0.97× |
| report-controls | 5.991 | 6.379 | 1 | 1 | 0.94× |
| report-minimum | 5.677 | 5.488 | 2 | 2 | 1.03× |
| dictionary-sale | 5.446 | 5.661 | 1 | 1 | 0.96× |
| dictionary-purchase | 5.444 | 5.187 | 1 | 1 | 1.05× |
| supplier-list | 5.443 | 4.837 | 1 | 1 | 1.13× |
| dashboard | 5.211 | 6.543 | 2 | 2 | 0.80× |
| account-heads | 5.179 | 5.161 | 1 | 1 | 1.00× |
| dictionary-item | 4.896 | 3.958 | 1 | 1 | 1.24× |
| user-list | 4.842 | 4.823 | 1 | 1 | 1.00× |
| item-form | 4.814 | 4.396 | 0 | 0 | 1.10× |
| item-search | 4.774 | 4.871 | 1 | 1 | 0.98× |
| item-edit | 4.542 | 4.737 | 1 | 1 | 0.96× |
| dictionary-supplier | 4.264 | 4.442 | 1 | 1 | 0.96× |
| customer-form | 4.177 | 4.266 | 0 | 0 | 0.98× |
| password-form | 4.150 | 4.636 | 0 | 0 | 0.90× |
| report-purchase-supplier | 3.830 | 4.231 | 0 | 0 | 0.91× |
| report-factory | 3.542 | 4.414 | 1 | 1 | 0.80× |
| logout | 3.264 | 3.045 | 0 | 0 | 1.07× |
| login | 3.060 | 4.868 | 0 | 0 | 0.63× |

Stock save is measured **once**, separately after a fresh reset with migration
001, rather than five writes: **5457.675 → 1332.651 ms**,
**1644 → 1644 queries** (4.10×).
The profiler verified exactly one new snapshot (399 → 400 headers). Original
logging settings were restored: general_log 0, log_output FILE.

## Reproduction and evidence

```bash
export DOYEL_FIXTURES=/private/tmp/doyel-fixtures
DOYEL_DB_MIGRATIONS=001 tools/db-reset.sh legacy
tools/characterize/capture.sh http://127.0.0.1:8056 "$DOYEL_FIXTURES/checks/s11-pages"
tools/characterize/compare.sh "$DOYEL_FIXTURES/golden/legacy/pages" "$DOYEL_FIXTURES/checks/s11-pages"
DOYEL_DB_MIGRATIONS=001 tools/scenarios/run-all.sh http://127.0.0.1:8056 legacy "$DOYEL_FIXTURES/checks/s11-scenarios"
tools/characterize/compare.sh "$DOYEL_FIXTURES/golden/legacy/scenarios" "$DOYEL_FIXTURES/checks/s11-scenarios"
DOYEL_DB_MIGRATIONS=001 tools/db-reset.sh legacy
DOYEL_PERF_OUT="$DOYEL_FIXTURES/perf/s11-indexed-full" tools/perf.sh http://127.0.0.1:8056 legacy
DOYEL_DB_MIGRATIONS=001 DOYEL_PERF_OUT="$DOYEL_FIXTURES/perf/s11-indexed-snapshot" tools/perf.sh --snapshot http://127.0.0.1:8056 legacy
```

The shown output paths are the completed runs; use new/empty paths to repeat.
Reset after scenario 9 before further authenticated checks, because that flow
intentionally changes the admin fixture password. Schema/EXPLAIN JSON lives at
`$DOYEL_FIXTURES/s11-*.json`; performance samples/logs under
`perf/s11-indexed-full` and `perf/s11-indexed-snapshot`. Raw diffs, resolved names,
cookies and SQL events stay private and are never reproduced here. The final
fixture is fresh with migration 001 applied (399 stock headers and original test
credentials), ready for S1.2. No production database or remote was accessed.
