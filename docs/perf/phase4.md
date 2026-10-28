# Final release performance

Measured 2026-10-28 (Asia/Dhaka) on local macOS/OrbStack, PHP 8.4.27 and
MySQL 8.4.13/default strict SQL mode, application commit `8f68801`. The isolated
`doyel_release` fixture includes migrations 001–004, reviewed cleanup as-of
2026-10-28 and duplicate-user cleanup. These are local measurements, not Windows
production estimates. Earlier phase comparisons use different runtime/schema
and retained data, so timings do not isolate the effect of one change.

All **52 read-only labels × five samples (260 requests)** completed with zero
PHP diagnostics. Each label's SQL count remained constant across five samples.
Authentication, report-range setup, CSRF token discovery, routing checks and
observer queries stay outside each measured request. No concurrent app/database
traffic ran during profiling. Raw SQL/cookies/results remain private.

| Label | Median ms | Queries | Query range |
|---|---:|---:|---:|
| report-sales-bill | 129.823 | 3 | 3–3 |
| report-sales-user | 71.239 | 2 | 2–2 |
| report-purchase-bills | 60.824 | 3 | 3–3 |
| report-sales-name | 49.982 | 1 | 1–1 |
| report-cash-expenses | 48.008 | 2 | 2–2 |
| report-sales-category | 46.946 | 2 | 2–2 |
| report-sales-supplier | 44.873 | 2 | 2–2 |
| report-cash-collection | 34.358 | 2 | 2–2 |
| report-unposted | 25.284 | 2 | 2–2 |
| supplier-payment | 20.434 | 1 | 1–1 |
| payment-customer-with-dues | 19.154 | 5 | 5–5 |
| payment-list | 17.466 | 1 | 1–1 |
| report-members | 12.559 | 1 | 1–1 |
| accounts | 11.911 | 2 | 2–2 |
| admin-bills | 10.423 | 2 | 2–2 |
| report-purchases | 9.641 | 1 | 1–1 |
| dashboard | 9.384 | 2 | 2–2 |
| report-items | 9.228 | 1 | 1–1 |
| report-item-ledger | 8.000 | 3 | 3–3 |
| report-accountants | 7.750 | 1 | 1–1 |
| report-quantity | 7.658 | 1 | 1–1 |
| sale-posted | 7.063 | 4 | 4–4 |
| report-snapshot | 6.698 | 3 | 3–3 |
| report-closing | 6.569 | 2 | 2–2 |
| purchase-posted | 6.266 | 3 | 3–3 |
| item-list | 5.830 | 1 | 1–1 |
| report-maximum | 5.361 | 1 | 1–1 |
| account-heads | 4.844 | 1 | 1–1 |
| sale-unposted | 4.833 | 4 | 4–4 |
| dictionary-purchase | 4.570 | 1 | 1–1 |
| item-search | 4.425 | 1 | 1–1 |
| report-sales-memo | 4.290 | 4 | 4–4 |
| expenses | 4.241 | 2 | 2–2 |
| cash-collection | 4.136 | 2 | 2–2 |
| dictionary-supplier | 4.013 | 1 | 1–1 |
| dictionary-sale | 3.998 | 1 | 1–1 |
| customer-list | 3.947 | 1 | 1–1 |
| report-controls | 3.729 | 1 | 1–1 |
| dictionary-item | 3.727 | 1 | 1–1 |
| report-minimum | 3.693 | 1 | 1–1 |
| item-edit | 3.608 | 1 | 1–1 |
| report-purchase-memo | 3.584 | 2 | 2–2 |
| user-list | 3.411 | 1 | 1–1 |
| report-purchase-detail | 3.229 | 1 | 1–1 |
| supplier-list | 2.938 | 1 | 1–1 |
| report-factory | 2.346 | 1 | 1–1 |
| login | 1.679 | 0 | 0–0 |
| password-form | 1.524 | 0 | 0–0 |
| customer-form | 1.415 | 0 | 0–0 |
| item-form | 1.239 | 0 | 0–0 |
| report-purchase-supplier | 1.137 | 0 | 0–0 |
| logout | 1.101 | 0 | 0–0 |

## Separate stock snapshot

The one-shot profiler freshly reset the fixture through
001 → 002 → cleanup → 003 → duplicate cleanup → 004, then submitted one
valid CSRF POST. It verified exactly one new snapshot (8 → 9). Form/token and
login setup were outside the measured window.

| Label | Median ms | Queries | Query range |
|---|---:|---:|---:|
| stock-save | 21.290 | 4 | 4–4 |

The largest original loops remain removed: full sales bills use **3 queries**
(original 5166), purchase bills **3** (1872), the payment list **1** (697), member
dues **1** (697), and stock save **4** (1644). See [Phase 2](phase2.md) for the
historical full comparison and its measurement limits.

After both runs an independent query confirmed `general_log=0`, `log_output=FILE`
and the unchanged default strict SQL mode. The normal-request private error log
contains no application errors. The test HTTP server was stopped afterward.

## Reproduction

The outputs must be new direct children of the external fixture perf directory.
`tools/serve-modern.sh 8146 doyel_release` runs in a separate terminal. A fresh
fixture uses the full reset flags below; never use these fixture commands on
production.

```sh
export DOYEL_FIXTURES=/private/tmp/doyel-fixtures
export DOYEL_DB_MIGRATIONS=001,002,003,004
export DOYEL_DB_CLEANUP_AS_OF=2026-10-28 DOYEL_DB_DEDUP_USERS=1
tools/db-reset.sh modern doyel_release
DOYEL_PERF_OUT="$DOYEL_FIXTURES/perf/release-full" tools/perf.sh http://127.0.0.1:8146 modern doyel_release
DOYEL_PERF_OUT="$DOYEL_FIXTURES/perf/release-snapshot" tools/perf.sh --snapshot http://127.0.0.1:8146 modern doyel_release
```

Private `metadata.json`, `complete.json` and per-request samples in those two
output directories substantiate label/sample counts and query ranges. All final
read counts match the Phase 2 query counts. No new normalization was introduced.
