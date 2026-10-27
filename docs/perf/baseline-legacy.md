# Legacy performance baseline (S0.5)

Measured on 2026-10-27 against the frozen `baseline-legacy` source archive,
with a fresh fixture import. Host: Darwin 27.0.0 arm64; observer CLI PHP
8.4.27. Local Docker/OrbStack runs PHP 5.6.40/Apache and MySQL
5.7.44 with linux/amd64 emulation, as established in S0.2. Modern
MySQL was not used for this baseline. These are local development measurements,
not estimates of the client's Windows hardware.

## Method

`tools/perf.sh <base-url> <legacy|modern> [dbname]` replays every tracked manifest
label five times and reports the median HTTP wall time. Full report range:
2016-01-01 00:00:00 through 2026-10-25 23:59:59. Report setup, authentication,
read-only routing verification and observer SQL are outside each measured window.
Each report sample gets the range explicitly; logout is reauthenticated between
samples. No cache flush is performed. SQL logging overhead is included in all
page timings consistently, so repeat this protocol for before/after comparisons.

MySQL general-log table events identify the app connection by its selected
fixture database. Counts include `Query` and `Execute` events on that connection;
`Connect`, `Init DB`, `Quit`, observer queries and setup requests are excluded.
Unexpected SQL connections or concurrent app connections fail the run. Zero-query
form/logout requests are legitimate. A logged dashboard request must prove the
HTTP server uses the selected database before any reported sample or stock save.
Original logging settings are restored on success and handled failure. The tool
never truncates the log table. Private SQL events remain in the local disposable
DB and external fixture logs; do not copy them into git or reports.

All **52 manifest labels** have exactly five samples. Every query count was
constant across its five samples. Tables are sorted by median duration.

| Label | Median ms | Queries | Query range |
|---|---:|---:|---:|
| report-sales-bill | 39626.701 | 5166 | 5166–5166 |
| report-sales-user | 19268.206 | 12 | 12–12 |
| report-purchase-bills | 6476.091 | 1872 | 1872–1872 |
| report-unposted | 1157.704 | 454 | 454–454 |
| payment-customer-with-dues | 1105.976 | 702 | 702–702 |
| payment-list | 1018.133 | 697 | 697–697 |
| report-sales-category | 1013.041 | 11 | 11–11 |
| report-members | 1008.799 | 697 | 697–697 |
| report-sales-name | 773.022 | 1 | 1–1 |
| report-cash-expenses | 237.149 | 45 | 45–45 |
| report-snapshot | 168.834 | 4 | 4–4 |
| report-sales-supplier | 113.585 | 2 | 2–2 |
| report-cash-collection | 86.796 | 9 | 9–9 |
| report-item-ledger | 26.003 | 4 | 4–4 |
| accounts | 20.968 | 8 | 8–8 |
| sale-posted | 17.977 | 5 | 5–5 |
| sale-unposted | 17.751 | 5 | 5–5 |
| report-purchases | 16.780 | 1 | 1–1 |
| report-sales-memo | 15.703 | 6 | 6–6 |
| report-quantity | 13.189 | 11 | 11–11 |
| admin-bills | 12.585 | 2 | 2–2 |
| report-purchase-detail | 12.339 | 1 | 1–1 |
| supplier-payment | 11.557 | 5 | 5–5 |
| expenses | 11.498 | 2 | 2–2 |
| purchase-posted | 10.595 | 4 | 4–4 |
| report-accountants | 10.260 | 1 | 1–1 |
| report-items | 10.031 | 3 | 3–3 |
| report-closing | 9.047 | 4 | 4–4 |
| cash-collection | 8.785 | 2 | 2–2 |
| report-maximum | 8.660 | 7 | 7–7 |
| report-purchase-memo | 8.533 | 2 | 2–2 |
| item-list | 7.393 | 1 | 1–1 |
| customer-list | 6.801 | 1 | 1–1 |
| report-controls | 5.991 | 1 | 1–1 |
| report-minimum | 5.677 | 2 | 2–2 |
| dictionary-sale | 5.446 | 1 | 1–1 |
| dictionary-purchase | 5.444 | 1 | 1–1 |
| supplier-list | 5.443 | 1 | 1–1 |
| dashboard | 5.211 | 2 | 2–2 |
| account-heads | 5.179 | 1 | 1–1 |
| dictionary-item | 4.896 | 1 | 1–1 |
| user-list | 4.842 | 1 | 1–1 |
| item-form | 4.814 | 0 | 0–0 |
| item-search | 4.774 | 1 | 1–1 |
| item-edit | 4.542 | 1 | 1–1 |
| dictionary-supplier | 4.264 | 1 | 1–1 |
| customer-form | 4.177 | 0 | 0–0 |
| password-form | 4.150 | 0 | 0–0 |
| report-purchase-supplier | 3.830 | 0 | 0–0 |
| report-factory | 3.542 | 1 | 1–1 |
| logout | 3.264 | 0 | 0–0 |
| login | 3.060 | 0 | 0–0 |


## Stock snapshot (one write)

Run separately with `--snapshot`, which resets the local fixture first. It creates
one snapshot, not five. The header count increased from 399 to 400. The following
single duration is displayed in the same table format but is not a five-run median.

| Label | Median ms | Queries | Query range |
|---|---:|---:|---:|
| stock-save | 5457.675 | 1644 | 1644–1644 |


## Interpretation and reproduction

Payment's **697** queries match one customer-list query plus two aggregates for
each of 348 customers. The selected-customer variant adds five queries. Other
important baselines are sales bills (5,166 queries), purchase bills (1,872),
unposted bills (454), and member dues (697). The user-grouped sales report takes
about 19 seconds despite only 12 queries, so indexes and query shape matter as
well as query count. The factory report's one failed SQL query still counts;
its known broken empty output is recorded by S0.3 and repaired in S2.9.

```bash
export DOYEL_FIXTURES=/private/tmp/doyel-fixtures
tools/db-reset.sh legacy
tools/perf.sh http://127.0.0.1:8056 legacy
tools/perf.sh --snapshot http://127.0.0.1:8056 legacy
```

The complete raw run is privately stored under `$DOYEL_FIXTURES/perf/` in
`s05-full-baseline/`; snapshot data is in `s05-snapshot/`. Only neutral labels,
aggregate metrics and environment details are committed here. `DOYEL_PERF_OUT`
can select a new direct child of the private perf directory. `DOYEL_PERF_LABELS`
selects explicit comma-separated manifest labels for intermediate slice checks;
subset checks must not be described as a complete baseline. The profiler locks
against other profilers but requires orchestration to exclude other test traffic.

For modern profiling use the modern HTTP port and its database name. The normal
ported login must be available (S2.2); this is not a workaround for S2.1's temporary
legacy-login prerequisite. S4.1 must update snapshot profiling to its POST/CSRF
contract when stock creation stops accepting GET. The current snapshot mode uses
the legacy GET write and verifies exactly one new snapshot, so it fails rather
than claiming a successful modern POST conversion.
