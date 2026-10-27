# Phase 2: PHP 8.4/PDO integration performance

Measured on 2026-10-28 (Asia/Dhaka), the same macOS/OrbStack host as Phase 0/1.
Modern PHP 8.4.27 and MySQL 8.4.13/default strict mode use isolated `doyel_s210`
with source index migration 001. Phase 0/1 use frozen PHP 5.6.40/MySQL 5.7.44.
The table compares actual local medians; runtime/DB versions differ, so it does
not attribute every timing change solely to PHP or promise Windows production
speed. All use the same full-history report range and neutral request manifest.

Every one of 52 read-only labels completed five timed requests (260 samples).
App query counts are constant across all five samples. Authentication, resolver,
routing validation and observer SQL are outside the measured app count. Every
modern sample has zero PHP diagnostics under development E_ALL. Tests held the
exclusive modern DB/HTTP slot throughout profiling.

| Label | Phase 0 ms | Phase 1 ms | Phase 2 ms | Phase 0 queries | Phase 1 queries | Phase 2 queries |
|---|---:|---:|---:|---:|---:|---:|
| report-sales-bill | 39626.701 | 904.159 | 104.439 | 5166 | 5166 | 3 |
| report-sales-user | 19268.206 | 780.012 | 66.174 | 12 | 12 | 2 |
| report-purchase-bills | 6476.091 | 328.712 | 54.330 | 1872 | 1872 | 3 |
| report-cash-expenses | 237.149 | 87.108 | 47.879 | 45 | 45 | 2 |
| report-sales-category | 1013.041 | 813.285 | 45.568 | 11 | 11 | 2 |
| report-sales-supplier | 113.585 | 78.353 | 41.711 | 2 | 2 | 2 |
| report-sales-name | 773.022 | 770.725 | 39.567 | 1 | 1 | 1 |
| report-cash-collection | 86.796 | 67.291 | 31.904 | 9 | 9 | 2 |
| report-unposted | 1157.704 | 68.931 | 24.797 | 454 | 454 | 2 |
| supplier-payment | 11.557 | 6.716 | 19.252 | 5 | 5 | 1 |
| payment-customer-with-dues | 1105.976 | 91.700 | 17.375 | 702 | 702 | 5 |
| payment-list | 1018.133 | 87.400 | 17.274 | 697 | 697 | 1 |
| report-members | 1008.799 | 89.497 | 12.359 | 697 | 697 | 1 |
| accounts | 20.968 | 9.660 | 9.202 | 8 | 8 | 2 |
| report-purchases | 16.780 | 17.759 | 8.856 | 1 | 1 | 1 |
| report-accountants | 10.260 | 9.249 | 6.983 | 1 | 1 | 1 |
| report-item-ledger | 26.003 | 10.629 | 6.749 | 4 | 4 | 3 |
| report-items | 10.031 | 10.686 | 6.569 | 3 | 3 | 1 |
| item-list | 7.393 | 7.710 | 6.440 | 1 | 1 | 1 |
| customer-list | 6.801 | 7.016 | 6.347 | 1 | 1 | 1 |
| report-sales-memo | 15.703 | 6.104 | 6.322 | 6 | 6 | 4 |
| report-quantity | 13.189 | 13.225 | 6.228 | 11 | 11 | 1 |
| report-closing | 9.047 | 9.070 | 6.180 | 4 | 4 | 2 |
| item-search | 4.774 | 4.871 | 5.758 | 1 | 1 | 1 |
| admin-bills | 12.585 | 12.818 | 5.592 | 2 | 2 | 2 |
| account-heads | 5.179 | 5.161 | 5.483 | 1 | 1 | 1 |
| report-snapshot | 168.834 | 6.147 | 5.445 | 4 | 4 | 3 |
| purchase-posted | 10.595 | 5.091 | 5.130 | 4 | 4 | 3 |
| sale-posted | 17.977 | 6.701 | 4.843 | 5 | 5 | 4 |
| dictionary-supplier | 4.264 | 4.442 | 4.529 | 1 | 1 | 1 |
| dashboard | 5.211 | 6.543 | 4.473 | 2 | 2 | 2 |
| expenses | 11.498 | 5.975 | 4.394 | 2 | 2 | 2 |
| sale-unposted | 17.751 | 6.107 | 4.328 | 5 | 5 | 4 |
| supplier-list | 5.443 | 4.837 | 4.208 | 1 | 1 | 1 |
| user-list | 4.842 | 4.823 | 4.207 | 1 | 1 | 1 |
| dictionary-item | 4.896 | 3.958 | 4.184 | 1 | 1 | 1 |
| report-maximum | 8.660 | 8.649 | 4.129 | 7 | 7 | 1 |
| dictionary-purchase | 5.444 | 5.187 | 3.410 | 1 | 1 | 1 |
| dictionary-sale | 5.446 | 5.661 | 3.352 | 1 | 1 | 1 |
| cash-collection | 8.785 | 5.809 | 3.289 | 2 | 2 | 2 |
| report-purchase-memo | 8.533 | 4.684 | 3.118 | 2 | 2 | 2 |
| report-controls | 5.991 | 6.379 | 2.930 | 1 | 1 | 1 |
| password-form | 4.150 | 4.636 | 2.888 | 0 | 0 | 0 |
| item-edit | 4.542 | 4.737 | 2.673 | 1 | 1 | 1 |
| report-minimum | 5.677 | 5.488 | 2.649 | 2 | 2 | 1 |
| report-factory | 3.542 | 4.414 | 2.343 | 1 | 1 | 1 |
| report-purchase-detail | 12.339 | 4.553 | 2.240 | 1 | 1 | 1 |
| customer-form | 4.177 | 4.266 | 1.638 | 0 | 0 | 0 |
| login | 3.060 | 4.868 | 1.380 | 0 | 0 | 0 |
| item-form | 4.814 | 4.396 | 1.359 | 0 | 0 | 0 |
| report-purchase-supplier | 3.830 | 4.231 | 1.184 | 0 | 0 | 0 |
| logout | 3.264 | 3.045 | 1.037 | 0 | 0 | 0 |

Stock save is measured once, separately after a fresh reset+001; its profiler
checks exactly one new header (399 → 400) and the resulting detail summary:

| Write | Phase 0 ms | Phase 1 ms | Phase 2 ms | Phase 0 queries | Phase 1 queries | Phase 2 queries |
|---|---:|---:|---:|---:|---:|---:|
| stock-save | 5457.675 | 1332.651 | 22.824 | 1644 | 1644 | 4 |

The largest loops are gone: Payment's unselected list is **697 → 1** queries;
full sales bills **5166 → 3**, purchase bills **1872 → 3**, member dues
**697 → 1**, and stock save **1644 → 4**. Stock save's four queries include its
explicit transaction statements. Unselected supplier purchase reporting still
uses zero queries, retaining its empty default view. Login/password labels time
GET form rendering, not password verification or hashing.

Reproduction (new private output directories are required):

```bash
export DOYEL_FIXTURES=/private/tmp/doyel-fixtures
export DOYEL_DB_HOST=127.0.0.1 DOYEL_DB_PORT=33084
export DOYEL_DB_USER=root DOYEL_DB_PASS=doyel-local DOYEL_DB_NAME=doyel_s210
DOYEL_DB_MIGRATIONS=001 tools/db-reset.sh modern doyel_s210
# In a separate terminal: tools/serve-modern.sh 8130 doyel_s210
DOYEL_PERF_OUT="$DOYEL_FIXTURES/perf/s210-full" tools/perf.sh http://127.0.0.1:8130 modern doyel_s210
DOYEL_DB_MIGRATIONS=001 DOYEL_PERF_OUT="$DOYEL_FIXTURES/perf/s210-snapshot" tools/perf.sh --snapshot http://127.0.0.1:8130 modern doyel_s210
```

Actual private outputs are `perf/s210-full/` and `perf/s210-snapshot/`, each with
completion metadata. Historical comparison samples are `perf/s05-full-baseline/`,
`perf/s11-indexed-full/` and their snapshot counterparts. SQL events, fixture
records and cookies stay outside git. The completed profiler restores its logger;
an independent post-run query returned `general_log=0`, `log_output=FILE`.
PDO emulated prepares and stringified fetches both remain enabled. The final
isolated fixture has 400 stock headers because the measured stock write is real.
