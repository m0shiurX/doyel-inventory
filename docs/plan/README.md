# Modernization plan: Doyel Eng. inventory and sales app

This plan takes a ~10-year-old procedural PHP 5.x / MySQL app to a supported stack (PHP 8.4, MySQL 8.4 LTS), fixes the slow pages, and closes the security holes. The app keeps its pages, URLs and workflows. This is a port, not a rewrite.

The work is split into **phases**. Each phase is split into **slices**, and one agent implements one slice. The audit behind the plan is in [`../findings.md`](../findings.md). Slices cite findings by ID.

| Phase | File | Outcome | Can ship to the client alone? |
|-------|------|---------|-------------------------------|
| 0 | [phase-0-safety-net.md](phase-0-safety-net.md) | Local legacy and modern stacks, characterization suite, perf baseline | n/a (tooling) |
| 1 | [phase-1-db-quick-wins.md](phase-1-db-quick-wins.md) | Indexes on the **current** server, no code change | **Yes**: the fastest relief for the client |
| 2 | [phase-2-php8-port.md](phase-2-php8-port.md) | Every page on PHP 8.4 + PDO: injection-safe, guarded, no N+1 | Yes, together with a server upgrade |
| 3 | [phase-3-schema.md](phase-3-schema.md) | utf8mb4, DECIMAL money, defaults, constraints, data clean-up | Yes |
| 4 | [phase-4-hardening.md](phase-4-hardening.md) | CSRF/POST, backups, logging, front-end libraries, cut-over runbook | Yes |

## Slice protocol (every agent follows this)

1. **Read** this file, then your phase file's *Shared rules* section and your slice, then the `findings.md` rows your slice cites. Skip the other slices. They belong to other agents.
2. **Check dependencies.** Every slice in your **Depends on** line must show `merged` in the status table below. If one doesn't, stop and report that you're blocked.
3. **Work on `main` in the current checkout.** The owner explicitly requested one branch on 2026-10-27. The orchestrator delegates one implementation agent per slice and reviews it before committing; do not create branches or worktrees.
4. **Own your files.** Edit only the files and paths your slice lists under **Owns**. If the work needs a change anywhere else, leave that file untouched and write the needed change into your report as a hand-off.
5. **Verify** every **Done when** item. Paste the actual command output into your report. A check you couldn't run is reported as *not run*, with the reason.
6. **Commit** each reviewed slice on `main` with the message `S<id>: <summary>`. The orchestrator serializes commits and owns the status table. In this single-branch workflow, `merged` means implemented, verified and committed on `main`; a blocked verification is recorded separately and does not count as merged.
7. **Report** in this shape:

```
Slice: S2.3   Branch: main   Commit: <sha>
Changed files: ...
Done-when results: <each item → pass/fail + evidence>
Findings resolved: S1 (Sales.php), P6, ...
Intentional behaviour changes: <each one, with the reason; "none" if none>
Hand-offs: <changes needed outside owned files>
Open questions: ...
```

## Ground rules

- **Client data stays out of git.** That covers SQL dumps, golden outputs and screenshots of real records. Fixtures live outside the repo in `$DOYEL_FIXTURES` (default `~/.doyel-fixtures/`), so every worktree shares them. Docs and reports refer to records by ID, never by customer name or phone number.
- **Behaviour-preserving by default.** A slice changes what users see or what lands in the DB only when it lists the change under *Intentional behaviour changes*. A characterization diff with no such entry is a bug.
- **Match the house style.** Procedural PHP, one file per page, helpers in `lib/`. No framework and no runtime Composer dependencies.
- **Destructive data operations** (deleting rows and production changes) are written as scripts and runbooks. The owner runs them; agents execute data cleanup only against local fixture databases. The local git history purge is explicitly authorized by Q5, after preserving fixtures and a private recovery copy; never force-push as part of that local purge.

## Decisions (settled; change only through the owner)

| ID | Decision | Why |
|----|----------|-----|
| DEC1 | Keep procedural PHP. No framework, no runtime Composer packages | Smallest diff to review; the client's host only needs PHP |
| DEC2 | Run on PHP 8.4; write code compatible with PHP 8.2+ | 8.4 has security support to end of 2028; 8.2+ compatibility widens the hosting choice |
| DEC3 | Target DB: MySQL 8.4 LTS (MariaDB 10.11/11.4 LTS acceptable). Phase 2+ code must run under MySQL 8.4's **default** `sql_mode`. Phase 1 SQL must also run on MySQL/MariaDB 5.5+ | MySQL 8.0 reached end of life in April 2026; strict mode catches silent data corruption; Phase 1 has to run on the old production server |
| DEC4 | PDO with `ATTR_EMULATE_PREPARES=true` and `ATTR_STRINGIFY_FETCHES=true` | Values come back as server-formatted strings, exactly as `mysql_*` returned them. Native fetching turns FLOAT columns into values like `12.300000190734863` and would change every printed total until Phase 3 converts to DECIMAL |
| DEC5 | Characterization compares **extracted visible text** and **DB table dumps**, not raw HTML | Phase 2 legitimately changes markup (escaping, link case); the data must not change |
| DEC6 | Modern dev stack: one shared MySQL 8.4 container; each agent serves its own worktree with `php -S` and uses its own database | Parallel agents never fight over ports, code mounts or data |

## Owner answers (2026-10-27)

| ID | Decision | Implementation |
|----|----------|----------------|
| Q1 | Production is Windows, currently PHP Desktop 5.6 with XAMPP MySQL. Target PHP 8.4 + MySQL 8.4 LTS; MariaDB is an alternative with its own version numbering, not “MariaDB 8”. | S1.2 supplies Windows/XAMPP commands. S0.2 and S4.5 must verify a Windows runtime that actually embeds or serves PHP 8.4; replacing the PHP Desktop executable alone is not assumed to work. Record the installed DB version and upgrade access during preflight. |
| Q2 | Hard-delete bills and their detail lines; clean up existing orphan lines. No soft delete. Stock reversal is included in the planned D3 fix. | S2.7 reverses the bill's stock effect and deletes lines and header in one transaction. S3.2 deletes historical orphans without guessing a stock correction from missing headers. S3.4 uses RESTRICT with explicit child deletion. |
| Q3 | Keep the latest duplicate user: IDs **27** and **29** survive; remove IDs **26** and **28**. | S3.4 reports references before deletion, preserves historical bill attribution, then adds the unique username constraint. “Latest” means the higher ID in each identified pair. |
| Q4 | Keep two years of stock snapshots; delete snapshots strictly older than the cutoff. | S3.2 uses a fixed Asia/Dhaka cutoff recorded for the run; deletes snapshot detail rows and headers together. No monthly archive of older snapshots. Transaction history is unaffected. |
| Q5 | History purge approved, including the newly made commit. **Executed and verified in S0.1**, before any remote push. | S0.1 preserves local fixtures outside git, prepares a private recovery copy, then purges SQL dumps from all relevant refs and verifies the result. Rewriting history changes commit IDs. |
| Q6 | Delete the dead pages and unused backup directory. | S2.7 deletes the unlinked `updateClosingBalance.php` pair; S2.8 deletes `report/ledger.php` and `report/index.php`; S2.10 removes `unsused_Backups/` after preserving fixture dumps outside git and verifies references. S4.2 provides the replacement backup download. |

Preparation requested by the owner removes the Q6 dead files before Phase 0. Later slices verify their absence and references rather than porting or deleting them again. The legacy baseline therefore excludes those dead pages.

These answers settle the six policy questions; they do not mark any slice complete. Production data changes still use reviewed reports and an owner-run cut-over. Implementation status remains below.

## Dependency graph and status

`→` means "must be merged before". Slices on one line separated by `∥` run in parallel.

```
P0:  S0.1 → S0.2 → S0.3 → (S0.4 ∥ S0.5)
P1:  S1.1 → S1.2 (needs S0.4, S0.5)
P2:  S2.1 → (S2.2 ∥ S2.3 ∥ S2.4 ∥ S2.5 ∥ S2.6 ∥ S2.7 ∥ S2.8 ∥ S2.9) → S2.10
P3:  S3.1 → S3.2 → S3.3 → S3.4 (each ALTERs the same tables: strictly serial)
P4:  (S4.1 ∥ S4.2 ∥ S4.3 ∥ S4.4) → S4.5
```

Phase 2 can start as soon as Phase 0 is merged. It doesn't wait for Phase 1.

| Slice | Title | Status |
|-------|-------|--------|
| S0.1 | Repo hygiene | merged |
| S0.2 | Local stacks + fixture DB | merged |
| S0.3 | Read-only characterization | merged |
| S0.4 | Write-flow scenarios | todo |
| S0.5 | Perf and query-count baseline | todo |
| S1.1 | Index migration | todo |
| S1.2 | Production runbook for Phase 1 | todo |
| S2.1 | Foundation (`lib/`) + exemplar page | todo |
| S2.2 | Auth and users | todo |
| S2.3 | Sales flow | todo |
| S2.4 | Purchase and suppliers | todo |
| S2.5 | Items and customers | todo |
| S2.6 | Cash, payments, accounts | todo |
| S2.7 | Admin and stock snapshots | todo |
| S2.8 | Product and stock reports | todo |
| S2.9 | Sales, purchase and cash reports | todo |
| S2.10 | Integration sweep | todo |
| S3.1 | utf8mb4 everywhere | todo |
| S3.2 | Data clean-up | todo |
| S3.3 | Column types and defaults | todo |
| S3.4 | Constraints | todo |
| S4.1 | POST + CSRF for destructive actions | todo |
| S4.2 | Admin backup download | todo |
| S4.3 | Error handling and logging | todo |
| S4.4 | Front-end library refresh | todo |
| S4.5 | Production cut-over runbook | todo |
