# Implementation progress

The owner authorized sequential slice implementation with subagents and direct commits on `main` on 2026-10-27. The orchestrator reviews and commits each slice. Production changes remain owner-run.

## Preparation

- Record owner answers Q1–Q6 in the plan and align the workflow with one branch.
- Remove four approved dead pages, their commented links, the unused backup directory (after preserving dumps), and Windows thumbnail caches.
- Keep the graft graph cache ignored; it is local tooling, not application code.
- S0.1 preserves private fixtures and recovery data outside git before purging SQL from local history. No remote push.

## Slice reports

Reports under `docs/reports/` contain verification evidence and limitations. The plan status table is authoritative for dependencies. Windows runtime acceptance is deferred to the Windows cut-over and must not block building the local regression stack.

## Read-only implementation preflight

Before Phase 2, supplementary source/schema inspection confirmed all 13 tables use InnoDB and password storage is VARCHAR(255). The plan now covers omitted GET mutations, intermediate S2.1 fixture-session verification before the login port, fractional stock quantities and three-decimal unit rates, and cleanup before constraints during cut-over. These amendments preserve data precision and close actual inventory gaps; they do not represent completed implementation slices.

## Durable private preservation

Private source dumps, Phase 0 golden outputs and history recovery now also exist in `/Users/m0shiur/.doyel-fixtures/` and `/Users/m0shiur/.doyel-recovery/`; source checksums verified. Active test agents continue using `/private/tmp/doyel-fixtures` to avoid disrupting in-flight runs. No client data is committed.

## Integration follow-ups

Historical handoffs below are now resolved by S2.10, S3.3 and S4.3; their slice
reports contain the final verification. They are retained as implementation context.

- S4.3: forced PDO exceptions in S2.7/S2.3 currently produce a development fatal body with HTTP 200; the global handler must explicitly set 500 and clear buffered partial output. Normal requests are diagnostic-free.
- S2.10: inspect username length consistency before accepting new users. Business tables use 30-character usernames; user creation must not create identities that strict-mode bill/cash writes cannot store.
- S4.3/S4.5: verify exception logs cannot be downloaded through the web server; prefer a private configurable location outside the public document root if server-specific rules cannot reliably protect `logs/`.
- S3.3: after stock becomes DECIMAL, exercise fractional sale/add/remove and bill reversal; Phase 2 intentionally retains the old integer stock column until that migration.

- S3.3: enforce the selected numeric scale before related writes (quantity before stock/detail arithmetic; money discounts before header totals), so schema rounding cannot make stored detail quantities disagree with stock or stored discounts disagree with totals. Include boundary precision checks as well as ordinary fractional quantities.

## Windows runtime sources (checked 2026-10-28)

The owner confirmed Windows 10/11. The [PHP Desktop Windows 130.1 release](https://github.com/cztomczak/phpdesktop/releases/tag/chrome-v130.1) bundles PHP 8.3 and records broken native JS dialogs. S4.1 must use an HTML confirmation path for deletion. The [official interpreter upgrade instructions](https://github.com/cztomczak/phpdesktop/wiki/Knowledge-Base#php-interpreter--extensions) describe replacing the PHP directory with official x64 NTS binaries; S4.5 must verify PHP 8.4 CGI/extensions/Argon2id and actual Windows launch/printing. These source facts do not constitute a Windows runtime rehearsal.

## Requested pause after S4.4 (2026-10-28)

The owner requested finishing the current work, then pausing. S4.1–S4.4 are
implemented and reviewed for direct commits on main. The combined release
verification passed all nine write scenarios (89 HTTP steps) and all 77 page
comparison files with zero differences or diagnostics. See
`docs/reports/release-verification.md` and the individual S4 reports.

S4.4 keyboard workflows passed in actual Chrome and Firefox; both complete
frontend inventories had zero errors. Temporary Migrate reported only the
existing Quirks Mode warning, recorded in `docs/reports/S4.4.md`. The owner
explicitly authorized Firefox's first-run terms acceptance during testing.
Temporary S4.4 servers/harnesses/browser window/mount and the orchestrator's
port-8146 release server were cleaned up. Private evidence is retained.

Do not start S4.5 or further measurements until the owner requests continuation.
Remaining work:

1. Implement S4.5: sanitized runtime packaging/private configuration, Windows
   preflight and owner-run cut-over runbook, followed by a full local migration
   and rollback rehearsal. The `s45_cutover` agent completed read-only research
   only; no S4.5 files or tests were produced.
2. Run final full performance verification: 52 labels × five requests plus one
   separately measured snapshot POST, with exclusive database traffic. Inherit
   all migration/cleanup/dedup flags on every reset. Earlier phase/S4.1 timings
   remain historical, not final release measurements.
3. Owner-run Windows 10/11 runtime/CGI/extensions, launch, keyboard, printing and
   backup/restore acceptance, then production deployment. Production remains
   untouched. MySQL 8.4 is locally verified; MariaDB remains an alternative that
   requires separate verification.

For continuation, isolated `doyel_release` contains the full 001–004 fixture
after the successful page capture; its test server is stopped. Private outputs
are `/private/tmp/doyel-fixtures/release-scenarios` and `release-pages`. Preserve
the durable fixture/recovery copies described above. No branch/worktree exists
for this work; continue on main with a reviewed commit after each slice.

## Resumed for S4.5 (2026-10-28)

The owner explicitly resumed work. The final 52-label/five-sample performance
run and one-shot snapshot passed with zero diagnostics and constant query
counts; see `docs/perf/phase4.md`. MySQL logging settings were restored and the
port-8146 server stopped. The isolated release DB now contains nine snapshots
because the single measured save was real.

The S4.5 implementation agent completed the runbook, sanitized release and CLI
preflight/verification tools and rehearsed the exact local migration and rollback
sequence. The orchestrator reviewed the slice and final verification. All planned
repository slices are complete; no further implementation is currently pending.

See `docs/runbooks/cutover.md` for the owner procedure and `docs/reports/S4.5.md`
for commands and results. Restricted-account original/restored releases each
matched all 77 comparison files, with zero modern diagnostics; all 13 tables
matched exact row bytes. Legacy fallback and both roles were verified, preserving
known legacy diagnostics. Final lint passed all 89 PHP files. Temporary S4.5
servers are stopped; private evidence/dumps remain outside git.

Remaining owner gates: actual Windows PHP 8.4 CGI/launch/extensions, HTTP file
denial and loopback binding, keyboard/printing/download/restore, service/path/ACL
inventory, a real off-machine backup, live cleanup-preview sign-off and production
deployment. MySQL 8.4 is locally verified; MariaDB requires separate acceptance.
No production changes were performed. Follow these gates rather than treating
local PHP CLI/container tests as Windows acceptance.

## Backup, restore and update (2026-11-07)

R1–R8b of `docs/plan/backup-restore-ui.md` are implemented; see `docs/reports/R8b.md`
for the final verification. Repository work is complete. Remaining gates are
owner-run on Windows: compile the 1.1.0 installer, test first install, restore and
application-only update on a test machine, then install on the production PC with
a freshly exported and migrated database. Production remains untouched.
