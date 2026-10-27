# Phase 3: Schema modernization

**Goal:** a schema that is consistent and correct:
- utf8mb4 everywhere;
- exact money types;
- defaults on every NOT NULL column;
- constraints that prevent junk;
- the junk that already exists, cleaned up.

The Phase 2 code keeps working throughout.

## Shared rules

- Migrations continue the numbering in `db/migrations/` (`002_...`), each with `up` and `down`. From this phase on, the floor is **MySQL 8.0 / MariaDB 10.6**: Phase 3 ships with or after the server upgrade (Q1). Write a one-line comment wherever syntax differs between MySQL and MariaDB.
- Slices are **strictly serial**. Each one ALTERs the same tables.
- Every migration is rehearsed on a fresh fixture: `db-reset` → `001` → (earlier Phase 3 migrations) → yours. Then the full characterization and scenario suites run against the Phase 2 code. Expected result: zero diffs beyond the intentional changes listed in the slice.
- Every data-changing step first writes a **report query** (what it would change, as counts and IDs) to `docs/data/<slice>.md`, with counts and IDs only, no names. Deletions require the owner's sign-off on that report. The agent never deletes production data, only fixture data.

---

## S3.1 utf8mb4 everywhere

**Depends on:** Phase 2 merged
**Owns:** `db/migrations/002_utf8mb4.*`, `lib/db.php` (charset line only, if it needs a change)
**Resolves:** P7

- `ALTER TABLE ... CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci` for all 13 tables, plus `ALTER DATABASE`. Report non-ASCII counts/IDs before conversion. The initial ASCII audit missed valid UTF-8 Bengali text in sales Note ID 6390; S3.1 verified its utf8mb3 → utf8mb4 → utf8mb3 byte-exact round trip. Preserve that text. Original UTF-8 columns may contain Unicode only when a byte-exact round trip proves conversion lossless; original latin1 text remains ASCII-required to avoid guessing its encoding. Stop on any unproven conversion. Down migrations must similarly refuse characters not representable in the original charset, rather than replacing or repairing data.
- Index key length: the `UNIQUE` keys on `VARCHAR(80)` stay well under 3072 bytes. Confirm for every index.
- Collation change from `utf8_unicode_ci` to `utf8mb4_unicode_ci`: equality and ordering stay the same for ASCII. Characterization confirms it.

**Done when:**
- `information_schema` shows no `latin1`/`utf8mb3` columns.
- The suites show zero diffs.
- `EXPLAIN` on the dues and cash queries still uses their indexes.

## S3.2 Data clean-up

**Depends on:** S3.1
**Owns:** `db/cleanup/` (scripts), `docs/data/S3.2.md`
**Resolves:** D6, P5 (two-year retention approved in Q4), orphan rows from D3

Report, then (after sign-off) delete:
1. Empty purchase bills: `Amount=0 AND Post=0` with no detail rows (348 at baseline).
2. Unposted sales bills older than 30 days with no detail rows (part of the 104).
3. Orphan detail rows: `canteen_sales_detail` with no `canteen_sales` parent, and the same for purchases.
4. Prune stock snapshots strictly older than two years (Q4). Record one Asia/Dhaka cutoff for preview and execution. Identify snapshots by header date; delete their detail rows by `SlNo` and then their headers in one transaction. Keep snapshots exactly at the cutoff; keep all newer snapshots. Do not prune sales, purchases, collections or expenses. Report malformed dates and orphan snapshot details separately rather than guessing their age.
5. List out-of-range values and existing precision that S3.3 must preserve, including fractional detail quantities and three-decimal unit rates. Fix nothing in this report step.

**Done when:**
- `docs/data/S3.2.md` lists counts for each category, before and after, on the fixture.
- A re-run reports 0 for every applied category.
- The suites show diffs only where removed junk bills or pruned snapshots disappear from listings/reports; list every expected difference. Sales/purchase/collection transaction history stays intact.

## S3.3 Column types and defaults

**Depends on:** S3.2
**Owns:** `db/migrations/003_types_defaults.*`, `lib/` (formatting helper only, if needed)
**Resolves:** D1, F7 (schema side)

- Money totals (`Amount`, `Discount`, `Total`, `amount`, `Amt`, `PurAmt`) become `DECIMAL(14,2)`. Preserve unit-price precision: `Rate` uses at least `DECIMAL(14,3)` because existing sale/purchase rates include real three-decimal values (detail IDs 4487/2832); profile `PurPrice` and `SalesPrice` before selecting their scale and preserve meaningful existing precision. Do not label actual half-cent rounding as float noise. Quantities in detail tables and `canteen_item.Qnty` become `DECIMAL(12,3)` unconditionally: fractional sale details exist even though the old stock column was INT, so integer current-stock values do not justify keeping INT.
- **Output stability:** DECIMAL columns come back as `"15710.00"` where FLOAT gave `"15710"`. Pick one of two approaches:
  - (a) add `fmt_money()`/`fmt_qty()` in `lib/html.php` that reproduce the legacy rendering (no trailing `.00`, as PHP printed FLOAT), and apply them where pages print these columns. This is a hand-off list for S3.3 to execute across pages, which is allowed in this slice and has to be listed;
  - (b) accept the `.00` rendering as an intentional change.

  Default to (a) and ask the owner whether they prefer (b).
- Run a reconciliation query before and after: `SUM()` of each money column per table, and per-customer dues. Differences are only float noise below 0.01, and they're reported.
- Give every NOT NULL column without a default an explicit `DEFAULT` (`0`, `''`, or none for required business fields).
- Datetime columns stay `DATETIME`.

**Done when:**
- Reconciliation shows total and per-customer dues equal to the cent.
- The suites show zero diffs under option (a), or only `.00`-type diffs under option (b).

## S3.4 Constraints

**Depends on:** S3.3, S2.7 merged (transactional deletion per settled Q2); Q3 is settled
**Owns:** `db/migrations/004_constraints.*`, `docs/data/S3.4.md`
**Resolves:** D5, part of D3

- Per Q3, keep IDs 27 and 29; remove 26 and 28. Inventory references first, including case-insensitive name-based historical attribution. Reassign actual ID references if any; preserve bill history and the surviving credentials and permissions. Report counts/IDs before deletion, then add `UNIQUE (UserName)`.
- Foreign keys `canteen_sales_detail.SalesID → canteen_sales`, `canteen_purchase_detail.BillNo → canteen_purchase`, and `ItemID → canteen_item` on both detail tables. Use `ON DELETE RESTRICT`: S2.7 explicitly reverses stock and deletes children before deleting the header in one transaction. Cascades alone cannot reverse stock, so do not rely on them for the bill-delete flow.
- Name-based references (D4) stay as they are. Converting them to IDs is optional work after Phase 4.

**Done when:**
- The migration applies cleanly on a fixture with S3.2's clean-up applied.
- Scenarios pass, including the delete-bill path in scenario 7, with hard deletion, stock reversal exactly once, and no orphan lines (Q2).
