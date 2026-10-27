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

- S4.3: forced PDO exceptions in S2.7/S2.3 currently produce a development fatal body with HTTP 200; the global handler must explicitly set 500 and clear buffered partial output. Normal requests are diagnostic-free.
- S2.10: inspect username length consistency before accepting new users. Business tables use 30-character usernames; user creation must not create identities that strict-mode bill/cash writes cannot store.
- S4.3/S4.5: verify exception logs cannot be downloaded through the web server; prefer a private configurable location outside the public document root if server-specific rules cannot reliably protect `logs/`.
- S3.3: after stock becomes DECIMAL, exercise fractional sale/add/remove and bill reversal; Phase 2 intentionally retains the old integer stock column until that migration.
