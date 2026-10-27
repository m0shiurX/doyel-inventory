# Phase 1: Database quick wins (no code change)

**Goal:** make the slow pages fast on the client's **current** server today, by adding indexes and nothing else. This phase ships on its own, before the PHP port.

## Shared rules

- Migrations live in `db/migrations/NNN_name.up.sql` and `NNN_name.down.sql`. The `down` file restores the exact prior schema.
- **Compatibility floor: MySQL 5.5 / MariaDB 5.5.** That means: plain `ALTER TABLE ... ADD INDEX`; no `IF NOT EXISTS` on indexes; no `ALGORITHM=`/`LOCK=` clauses; no invisible or functional indexes; no `utf8mb4`. Index key length on `utf8` columns stays ≤ 767 bytes (`VARCHAR(255)` at most; all candidate columns are ≤ `VARCHAR(100)`).
- Verify everything on the **legacy** stack (`mysql:5.7`, lenient mode). That's the closest match to production.
- App code is untouched. A slice in this phase that edits a `.php` file is out of scope.

---

## S1.1 Index migration

**Depends on:** S0.4, S0.5
**Owns:** `db/migrations/001_indexes.up.sql`, `db/migrations/001_indexes.down.sql`, `docs/perf/phase1.md`
**Resolves:** P1, P5 (primary key), part of P2/P6 (the N+1 loops remain, but each query becomes an index lookup)

Candidate indexes, a starting point. Keep an index only when a real query uses it, and add any the EXPLAIN pass shows are missing:

| Table | Index | Serves |
|-------|-------|--------|
| `canteen_sales` | `(CustomerName)` | `Payment.php` dues, `SalesMemo.php`, `list_of_members.php` |
| `canteen_sales` | `(UserName, Post, Date)` | open-bill lookup in `Sales.php`, per-user reports |
| `canteen_sales` | `(Post, Date)` | period reports |
| `canteen_sales_detail` | `(SalesID)` | every bill view and bill total |
| `canteen_sales_detail` | `(ItemID, Date)` | item ledger, stock sums |
| `canteen_sales_detail` | `(Date)` | period reports that join on the detail date |
| `canteen_purchase` | `(UserName, Post, Date)`, `(Post, Date)`, `(Supplier)` | same pattern as sales |
| `canteen_purchase_detail` | `(BillNo)`, `(ItemID, Date)` | bill view, item ledger |
| `canteen_collection` | `(Description)`, `(Date)`, `(HeadOfAccount, Date)` | dues, dashboard sums, cash reports |
| `canteen_expenses` | `(Date)`, `(HeadOfAccount, Date)`, `(Description)` | same |
| `canteen_stock_detail` | add `ID INT NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST`; index `(SlNo, SupplierName)` | P5; saved closing balance |

Steps:
1. Collect every distinct query shape from `grep -rhoE "(SELECT|UPDATE|DELETE)[^\"]*" --include='*.php' .`. Run `EXPLAIN` on each with real values from the fixture, **before** the migration. Record the shapes with `type=ALL` on a table over 1,000 rows.
2. Write the up and down migrations. Order the statements one `ALTER TABLE` per table, all of that table's indexes in one statement, so each table is rebuilt only once.
3. Check that adding `canteen_stock_detail.ID` doesn't change any page. `savedClosingBalance.php` uses `SELECT *` and `SavePrestntStock.php` INSERTs with an explicit column list. Confirm with characterization.
4. Apply the migration to legacy, then re-run EXPLAIN, characterization, scenarios and perf.
5. Apply `down`, confirm the schema equals the baseline (`SHOW CREATE TABLE` diff), then re-apply `up`.

**Done when:**
- Against legacy with the migration applied, `compare.sh` exits 0 for both pages and scenarios.
- `docs/perf/phase1.md` holds:
  - the before/after EXPLAIN summary for each hot shape (table, `type`, `key`, `rows`);
  - the perf table next to the baseline (ms and queries);
  - the total migration run time on the fixture.
- No hot shape remains `type=ALL` on a table over 1,000 rows. The exceptions are full-table aggregates by design (`Accounts.php` cash-in-hand `SUM` over the whole table); list them by name.
- `down` then `up` leaves `SHOW CREATE TABLE` identical to the first `up`.

## S1.2 Production runbook for Phase 1

**Depends on:** S1.1
**Owns:** `docs/runbooks/phase1-indexes.md`, `tools/prod/apply-migration.sh`
**Resolves:** none (delivery)

The owner runs this on the client's server. The agent writes it and dry-runs it against the legacy stack.

The runbook covers:
1. **Pre-checks:** the MySQL version (`SELECT VERSION()`); free disk space ≥ 2× the DB size; `SHOW INDEX` for each table, so a partially applied run is detected.
2. **Backup:** `mysqldump --single-transaction --routines software > software-pre-phase1-<date>.sql`. Copy it off the server and verify the file is non-empty and ends with `-- Dump completed`. Include the Windows/XAMPP form of each command (`C:\xampp\mysql\bin\mysqldump.exe`), because Q1 confirms Windows with XAMPP MySQL.
3. **Apply:** `tools/prod/apply-migration.sh` (bash) and a matching `.bat` for XAMPP. Each runs the up file, stops on the first error, and prints `SHOW INDEX` afterwards. Expected duration comes from S1.1's measurement. Note that each table is locked briefly while its ALTER runs, so schedule it outside business hours.
4. **Verify:** five pages to click (Dashboard, Sales bill, Payment, a sales period report, Closing Balance) and the expected speed-up from `phase1.md`.
5. **Rollback:** the down file, or a restore from the backup.

**Done when:**
- The bash script has been dry-run end to end against the legacy stack (fresh `db-reset`, then backup, apply, verify, rollback, apply) with its output in the report.
- Every command in the runbook was copied from a run that succeeded. The Windows commands are marked *untested* until actually rehearsed on Windows; knowing the OS does not validate commands.
