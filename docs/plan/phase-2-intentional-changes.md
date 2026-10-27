# Phase 2 intentional behavior changes

Owner review/sign-off list for the PHP 8.4/PDO port. Ordinary fixture page text
and workflows remain equivalent; the changes below deliberately close unsafe
behavior or fix a demonstrated bug. Slice reports hold detailed evidence.

| Area | Intentional change and reason | Evidence |
|---|---|---|
| Authentication and permissions | Every business page requires login; AJAX endpoints halt with HTTP 401. Admin user management, bill unpost/delete and payment removal halt before unauthorized writes. Escaping prevents stored/request text becoming markup; bound SQL makes input literal. Malformed array/numeric inputs fail without PHP diagnostics. | S2.1–S2.9; S2.10 complete inventory guard checks |
| Passwords | Reject blank passwords, including legacy empty-password login. Verify the historical MySQL-escaped MD5 bytes, then migrate only the verified user ID to a secure hash and regenerate its session. Duplicate usernames are checked by ID; compare-and-set handles concurrent changes. Password changes affect the authenticated identity only. NUL/over-72-byte passwords use Argon2id with an explicit capability guard; unsupported runtimes retain the old hash and explain the configuration problem. Existing bcrypt hashes reject these edge inputs rather than accepting truncated prefixes. | S2.2; scenario 9 verifies credentials before masking randomized hashes |
| New usernames | Creation rejects usernames longer than 30 Unicode characters with a visible error and an HTML maxlength. Existing usernames are neither changed nor truncated. This supplies a friendly pre-database rejection instead of a strict-mode exception: l_user, sales and expense username columns themselves are 30 characters. There is still only an add/list user interface. | S2.10 ASCII/Unicode 30/31-character HTTP/DB boundary checks |
| Sales and purchase amounts | Sales line prices/costs come from the catalogue. Both flows derive stored header amounts from stored details and entered discount rather than trusting readonly/hidden totals. Purchase rates remain entered values, and purchases still update current purchase price. Purchase's hidden Posted value now reflects actual state (legacy assignment typo corrected); its legacy visible gross-total display remains. | S2.3/S2.4; scenarios 1–3 and tampering checks |
| Line mutation and stock | Bill/detail/stock mutations are transactional and use relative stock updates with locks; sales enforce available stock atomically. Removal reads stored quantities and requires the detail to belong to the selected unposted bill; spoofed IDs/quantities, missing stock items and repeated removals cannot redirect stock changes. Posted bills reject line mutations. New bills use the inserted ID and serialize the user's open-bill selection. | S2.3/S2.4; fixture rollback/concurrency checks |
| Bill deletion | Admin deletion removes header and details together, reversing stored sales quantities into stock and stored purchase quantities out of stock. This applies to posted and unposted bills because stock changes at line entry. Repeated deletes do not reverse twice; failures roll back. Non-admin requests leave Post/header/lines/stock untouched. Historical orphans are not cleaned in Phase 2. | S2.7; scenario 7's exact 12-checkpoint difference envelope and modern assertions |
| Item edit and customer warning | An omitted/disabled stock control preserves an existing item's stock; a new item still starts at zero. Explicit stock-field submissions retain current role semantics. Customer edit keeps Nickname readonly and adds an edit-only warning that historical sales/collections use nickname text and are not rewritten by external changes. Bounded item/customer text is explicitly limited to source widths before strict-mode writes, retaining legacy lenient storage behavior. | S2.5; scenario 6 and nonzero-stock edit proof |
| Calendar inputs and reports | Accounts uses valid calendar month boundaries, including 30-day months. Report date/time inputs are validated and invalid values fall back to today. Repaired factory/process SQL joins its sales header and filters that header's date, retaining the existing no-Post-filter rule. The normal fixture has no qualifying raw-material rows, so this repair has no golden text difference; synthetic inside/outside-period rows prove the nonempty case. | S2.6/S2.7/S2.9 targeted assertions |
| Amount words | Reset the report number-to-words helper's recursive counter per call, eliminating cross-call state leakage and PHP 8 diagnostics. | S2.1 |
| Removed dead routes | Q6 removes four unused/broken pages (`updateClosingBalance.php`, its report counterpart, `report/ledger.php`, `report/index.php`) and `unsused_Backups/`. Their app links are absent. Replacement backup download remains S4.2. | Preparation; S2.7/S2.8/S2.10 absence/reference checks |
| Tool exposure | Repository fixture/profiling/characterization PHP scripts and helpers are CLI-only; direct HTTP receives an empty 404 before any fixture reset or other tool logic. Public application routes retain their paths. | S2.10 seven-script HTTP checks |

The narrow current-day closing heading normalization is a characterization clock
rule, not an application behavior change. Only `report/closingBalance.php`'s
live heading date is normalized. Historical report dates, saved snapshot dates,
quantities and totals stay literal. S2.8 preserved original private goldens and
verified all other bytes unchanged against a fresh frozen-legacy capture.

Remaining work stays in its planned phase: Phase 3 charset, numeric types,
historical cleanup and constraints; S4.1 POST/CSRF on business writes; S4.3
uniform exception HTTP 500, buffer cleanup and private logs; S4.4 browser/library,
keyboard/autocomplete and purchase Alt+P preparation checks; S4.5 Windows runtime
(including Argon2id), printing and production acceptance. The stock column is
still INT: fractional stock precision and scale validation need S3.3. This list
is prepared for owner sign-off; it does not claim production acceptance.
