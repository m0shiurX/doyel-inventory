# Release integration verification

Orchestrator verification on 2026-10-28, against disposable local fixtures only.
Runtime: PHP 8.4.27, MySQL 8.4.13 with its default strict SQL mode. Physical
Windows launch and printing remain owner-run deployment acceptance checks.

## Combined write regression

The final combined application, including CSRF, private error handling and the
frontend refresh, passed all nine real HTTP scenarios (89 requests). Each
scenario independently reset `doyel_release` through the complete sequence:
001 → 002 → reviewed cleanup → 003 → duplicate cleanup → 004.

```sh
DOYEL_LOG_DIR=/private/tmp/doyel-release-logs tools/serve-modern.sh 8146 doyel_release
DOYEL_FIXTURES=/private/tmp/doyel-fixtures \
DOYEL_DB_HOST=127.0.0.1 DOYEL_DB_PORT=33084 \
DOYEL_DB_USER=root DOYEL_DB_PASS=doyel-local DOYEL_DB_NAME=doyel_release \
DOYEL_DB_MIGRATIONS=001,002,003,004 \
DOYEL_DB_CLEANUP_AS_OF=2026-10-28 DOYEL_DB_DEDUP_USERS=1 \
tools/scenarios/run-all.sh http://127.0.0.1:8146 modern /private/tmp/doyel-fixtures/release-scenarios
tools/characterize/compare.sh /private/tmp/doyel-fixtures/s33-final-scenarios /private/tmp/doyel-fixtures/release-scenarios
```

```text
01-sale completed (11 HTTP steps)
02-oversell completed (5 HTTP steps)
03-purchase completed (6 HTTP steps)
04-customer-collection completed (13 HTTP steps)
05-cash completed (9 HTTP steps)
06-master-data completed (13 HTTP steps)
07-admin-bills completed (21 HTTP steps)
08-stock-snapshot completed (4 HTTP steps)
09-password completed (7 HTTP steps)
Recorded 0 PHP diagnostics in private _errors.txt
9 same, 0 differ, 0 missing
Normal-request error log entries: 0
```

All 84 PHP files passed `php -l`. A source audit found no application `mysql_*`
calls or `eval()`. The published test password above is restricted to the local
Docker fixture; production credentials and all raw data remain outside git.

## Combined read-only capture

After a fresh full reset with the same flags, the orchestrator captured all 76
page labels (77 comparison files including statuses):

```sh
DOYEL_FIXTURES=/private/tmp/doyel-fixtures \
DOYEL_DB_HOST=127.0.0.1 DOYEL_DB_PORT=33084 \
DOYEL_DB_USER=root DOYEL_DB_PASS=doyel-local DOYEL_DB_NAME=doyel_release \
tools/characterize/capture.sh http://127.0.0.1:8146 /private/tmp/doyel-fixtures/release-pages
tools/characterize/compare.sh /private/tmp/doyel-fixtures/s41-release-pages /private/tmp/doyel-fixtures/release-pages
```

```text
Captured 76 labels; 0 PHP diagnostics.
77 same, 0 differ, 0 missing
```

The S4.1 baseline already includes all approved Phase 3 data/schema changes;
earlier intentional differences are documented by their slice reports. No new
comparison normalization was introduced for release verification.

## Continuation and final performance

The owner requested a pause after S4.4, then explicitly resumed on 2026-10-28.
The final 52-label/five-sample run (260 requests) and separate one-shot stock
snapshot have now passed with zero diagnostics. Snapshot creation took
21.290 ms and four queries; exactly one new snapshot was created. Read query
counts remained constant and matched Phase 2. The independent post-run check
confirmed `general_log=0`, `log_output=FILE` and unchanged default strict SQL
mode. See [final performance](../perf/phase4.md) for all measurements and commands.

S4.5 implementation and its full local cut-over/rollback rehearsal passed;
see [S4.5 evidence](S4.5.md) and the [owner runbook](../runbooks/cutover.md).
The sanitized release and its restored backup each match all 77 page comparison
files with zero modern diagnostics. Exact data comparisons cover all 13 tables;
both roles, a posted sale/void with stock reversal, restricted-account backup,
38 static assets, migration order and legacy fallback were verified locally.
Final lint covers all 89 PHP files; settings JSON and relative document links
are valid. All planned repository slices are implemented and reviewed.

Actual Windows CGI/launch, loopback and HTTP file denial, printing, service/path/
ACL acceptance, a real off-machine backup and live-data sign-off remain owner-run
production gates. The local legacy fallback retained its existing diagnostics,
with business output matching the frozen baseline. MariaDB has not been tested.
Production has not been changed.

The orchestrator stopped its release verification HTTP server on port 8146
after both the original scenario/page checks and the final performance run.
Private evidence remains in the paths above and `perf/release-full` /
`perf/release-snapshot` under the external fixture directory.
