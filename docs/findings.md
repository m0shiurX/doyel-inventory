# Audit findings (baseline, 2026-10-27)

The audit of the legacy code and of the dump taken 2026-10-25. Slices cite findings by ID (`F3`, `P2`, `S1`, ...). A slice that resolves a finding says so in its **Done when**.

Data size, for scale: 13 tables, ~260k rows, 22 MB. Largest: `canteen_stock_detail` 190k, `canteen_sales_detail` 27k, `canteen_purchase_detail` 12k, `canteen_expenses` 10.5k, `canteen_collection` 6.9k, `canteen_sales` 5.3k, `canteen_purchase` 2.2k, `canteen_item` 868, `canteen_customar` 348. Text data is ASCII (Banglish), so charset conversion carries no double-encoding risk.

## Platform (F)

| ID | Finding | Where |
|----|---------|-------|
| F1 | `mysql_*` extension (removed in PHP 7.0): 174 `mysql_query`, 126 `mysql_fetch_object`, 59 `mysql_num_rows`, 48 `mysql_error`, plus a few others | 55 of 61 PHP files |
| F2 | Other removed functions: `split()` (PHP 7.0), `get_magic_quotes_gpc()` (PHP 8.0) | `report/index.php:96-97`, `login.php:41` |
| F3 | Links say `Report/...` but the folder is `report/`. This only works on a case-insensitive file system (Windows/macOS) and breaks on Linux | `Report.php` (19), `Sales.php`, `purchase.php` (2 each), `Supplier.php`, `listOfSupplier.php` |
| F4 | `date_default_timezone_set('Asia/Dacca')` copied into 11 files. It's a deprecated alias for `Asia/Dhaka` | 11 files |
| F5 | Datetimes are built as `date("Y-m-d, H-i-s")` and depend on MySQL's lenient parsing | `Sales.php`, `purchase.php`, `Payment.php`, `SavePrestntStock.php`, ... |
| F6 | `SELECT *, SUM(...) ... GROUP BY <col>` is rejected by `ONLY_FULL_GROUP_BY` (default since MySQL 5.7) | `report/UnpostedBill.php`, `report/Purchase of a period.php`, `report/Purchase-of-a-piriod-by-Supplier.php`, `report/sales_of_a_period_category_wise.php`, `report/SendToProcessOfaPeriod.php`, `report/salesOfaPeriod-itemName-By-user.php`, `report/salesOfaPeriod-supplierName.php` |
| F7 | INSERTs leave out NOT NULL columns that have no default, e.g. `INSERT INTO canteen_sales (UserName, Date)`. `STRICT_TRANS_TABLES` rejects these | `Sales.php`, `purchase.php`, `SavePrestntStock.php`, others |
| F8 | DB credentials are hard-coded (`root`, empty password) | `connect.php` |
| F9 | Front-end libraries from 2006–2012: jQuery 1.5 (`codes/`), 1.7.1 and 1.7.2 (`report/AutoSuggestDropDown/`), Prototype.js 1.x. `codes/bnspell.js` (item autocomplete) depends on Prototype's `Ajax.Request` and `eval()`s the dictionary responses | `codes/`, `report/AutoSuggestDropDown/` |
| F10 | PHP 8 throws `TypeError` on arithmetic with `""` or with a non-numeric string, and on `round("")`. The legacy code starts accumulators as `$Total=""` and then adds to them. There are 48 `=""` initialisations | most report pages, `Payment.php`, `report/closingBalance.php` |

## Performance (P)

| ID | Finding | Where |
|----|---------|-------|
| P1 | No indexes except primary keys and three UNIQUE keys. Every filter on `Date`, `ItemID`, `SalesID`, `BillNo`, `CustomerName`, `Description`, `HeadOfAccount`, `UserName` or `Post` does a full table scan | whole schema |
| P2 | N+1 queries: for each of the 348 customers, two `SUM()` full scans (`canteen_sales` by `CustomerName`, `canteen_collection` by `Description`). That's ~700 queries per page load | `Payment.php` (`netTotal()`), `report/list_of_members.php` |
| P3 | For each of the 868 items, two `SUM()` full scans of the detail tables. The result is then overwritten (`$Qnty=$rowc->Qnty;`), so all ~1,736 scans are wasted. Then about 225 single-row INSERTs with no transaction | `SavePrestntStock.php:28-31` |
| P4 | Same N+1 pattern as P3, and the result is written into `canteen_item.Qnty` for every item | `updateClosingBalance.php`, `report/updateClosingBalance.php` (not linked from the UI) |
| P5 | `canteen_stock_detail` holds 73% of all rows and has no primary key. Each "Save Present Stock" click copies ~225 rows; 2016–2018 alone added 167k | `SavePrestntStock.php` |
| P6 | The Sales page loads all customers into a `<select>` and all 868 item names (dictionary) on every load. It finds today's open bill with `Date LIKE 'YYYY-MM-DD%'`, which can't use an index | `Sales.php`, `purchase.php` |
| P7 | Tables mix charsets (`latin1`: `canteen_expenses`, `canteen_stock`, `canteen_stock_detail`; the others are `utf8`). Name-string joins across them can't use indexes | schema |
| P8 | The admin page lists 500 purchase and 500 sales bills with no pagination | `Unpost.php` |

## Security (S)

| ID | Finding | Where |
|----|---------|-------|
| S1 | SQL injection everywhere: request, session and DB values are interpolated into SQL. **Unauthenticated** injection in `dictionary/select.php?q=` and `report/AutoSuggestDropDown/search.php` (`search_term`) | ~all files |
| S2 | Pages with no login guard: `SavePrestntStock.php`, `updateClosingBalance.php`, `AddSupplierName.php`, `password.php`, `remove_payment.php` (it does have an admin check), all of `dictionary/*`, `report/AutoSuggestDropDown/search.php`, and **every `report/*.php` page**. Their `include("menu.php")` resolves to `report/menu.php`, which only holds a number-to-words function and no session check | listed |
| S3 | `Unpost.php:3` sends a redirect header without `exit`, so the page keeps running for non-admins. `Unpost.php?d=<id>` deletes a sale for any logged-in user | `Unpost.php` |
| S4 | Passwords are unsalted MD5. The stored hash is `md5(mysql_real_escape_string($pw))`, i.e. a hash of the **escaped** password | `login.php`, `password.php`, `list_of_user.php` |
| S5 | No output escaping (stored and reflected XSS) | ~all files |
| S6 | Destructive actions (delete or unpost a bill, delete a payment or a sale line, post a bill) are GET links or GET forms with no CSRF protection | `Unpost.php`, `Sales.php`, `purchase.php`, `remove_payment.php` |
| S7 | The server trusts client-supplied `rate`, `amount` and `PurAmt` (hidden or read-only fields) when it adds sale lines | `Sales.php` (and check `purchase.php`) |
| S8 | The client's data dump (customer names, phone numbers, password hashes) is in git history, commit `a8ac898` | repo |
| S9 | `list_of_user.php` (add or edit users, set passwords) has no admin check. Any logged-in user can create users, and it's linked from `Accounts.php` for everyone | `list_of_user.php` |

## Data integrity (D)

| ID | Finding | Where |
|----|---------|-------|
| D1 | Money and quantities are stored as `FLOAT`. Only 2 sale lines have fractional quantities, and sale amounts are whole numbers | schema |
| D2 | Stock is changed by read-modify-write in PHP (`SELECT Qnty` → compute → `UPDATE Qnty='$x'`) with no transaction, so concurrent sales lose updates. The detail INSERT and the stock UPDATE aren't atomic either | `Sales.php`, `purchase.php` |
| D3 | Deleting a sale or purchase bill leaves its detail rows behind and doesn't reverse the stock change | `Unpost.php` |
| D4 | Customers, suppliers and payment links use **name strings** (`canteen_sales.CustomerName` = `canteen_customar.Nickname` = `canteen_collection.Description`) instead of IDs | schema |
| D5 | `l_user.UserName` is not unique. There are duplicates: `mehedi`/`Mehedi` (ids 26, 27) and `shanto` (ids 28, 29). Id 28's password hash is `md5('')` | `l_user` |
| D6 | Junk rows: 104 sales bills never posted, 348 empty purchase bills (Amount 0, Post 0) | data |
| D7 | Dead or broken code: `report/ledger.php` queries `canteen_sales.Nickname`, which doesn't exist. `report/index.php` is an unrelated age calculator. The "Data Backup" link points to `Backup/new_backup.php`, which is missing. `unsused_Backups/backup.php` is unused | listed |
