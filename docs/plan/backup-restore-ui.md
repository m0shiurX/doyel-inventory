# Proposal: admin backup, restore and version visibility

Status: **implementation in progress, authorized 2026-11-07**. Each slice is
implemented by a subagent, reviewed and committed on main. Production remains
owner-run. This extends the procedure in [cutover.md](../runbooks/cutover.md).

Implementation refinement: the browser restore path consumes a versioned,
gzip JSON Lines **data-only** package (`.doyel.gz`). It never executes uploaded
SQL. The owner helper creates the trusted canonical schema and inserts validated
rows with bound parameters. Existing SQL backup/download/import remains usable;
legacy SQL takes a separate owner-assisted conversion path. Native-client SQL
safeguards below apply to that separate path, not to execution of browser uploads.
This reduces the required privilege and parser surface while preserving the
new-database, evidence-review and controlled-activation workflow.

| Slice | Status |
| --- | --- |
| R1 build/schema metadata and version view | complete |
| R2 data-only backup package | complete |
| R3 private jobs/quarantine/helper contract | complete |
| R4 candidate import and validation | complete |
| R5 admin restore/review/status UI | complete; target browser acceptance pending |
| R6 freeze, backup and activation | implemented; native Windows gate pending |
| R7 acceptance/rollback/audit | todo |
| R8 owner conversion and Windows/package integration | todo |

## Intended result

An administrator can prepare a restore into a new, uniquely named database,
review evidence, and activate it during a controlled maintenance window. The
previous database remains intact with a durable reference and a verified
pre-switch backup. Restoring never overwrites the active database by default.
Retiring an old database is a later owner action, outside this first version.

Restore preserves all supplied business history and users. It does not silently
prune historical stock snapshots, empty bills, duplicate users or other records.
Earlier explicitly approved cleanup is a separate action; its approval does not
become the default policy for future restores. A SQL file shrinking from roughly
21.6 MB to 5.7 MB alone proves neither loss nor correctness: formatting, batching,
encoding, schema and actual deletion can all change size. Show row/field and
financial evidence, with any deletion explained and separately approved.

## What exists today

- [backup.php](../../backup.php) requires admin access, streams all InnoDB tables
  from a consistent read-only snapshot, preserves numeric strings, emits table
  DROP/CREATE and INSERT statements, and ends with `-- Doyel backup complete.`.
  A partial download lacks that footer. It currently supplies **no manifest,
  checksum, schema-version field or restore UI**.
- [lib/auth.php](../../lib/auth.php) supplies `require_admin()`, current user IDs
  and password verification including legacy hashes. Login may rehash a password
  and therefore counts as a write. There is no general `require_roles()` helper;
  use the existing admin contract unless deliberately introducing one.
- [lib/csrf.php](../../lib/csrf.php) supplies POST-only CSRF validation and HTML
  confirmations suitable for the Desktop shell. Native JS dialogs are unsuitable.
- [lib/config.php](../../lib/config.php) caches configuration within a request;
  environment values override the local config file. The release builder creates
  a stub pointing to a private config outside the web root. The current loader
  itself defaults to root: deployment must use the restricted account required by
  the runbook, and the new feature must fail closed if that separation is absent.
- [lib/errors.php](../../lib/errors.php) supplies private logs and public error
  references. Restore needs explicit redaction because raw importer errors can
  contain SQL and business values.
- [build-release.php](../../tools/prod/build-release.php) packages a reviewed
  runtime allowlist; maintenance tools stay outside `www`. There is no updater
  or installed release/schema compatibility screen today.

## Proposed admin flow

1. **Backup & restore overview:** show installed application build/release,
   database engine/version, schema compatibility, last successful backup and
   restore references. Offer Download backup and Prepare restore. Restrict the
   page, downloads, job creation, status and artifacts to admins.
2. **Select backup:** upload a supported package into private quarantine, then
   show backup timestamp/timezone, source build/schema, file bytes/checksum,
   table counts and compatibility result. File name is display text only.
3. **Prepare new database:** enqueue a job and show durable stage/progress,
   elapsed time, validation results and an error reference on failure. Closing
   the browser must not cancel or restart a running import. Double submission
   must resolve to the same job, not create competing restores.
4. **Review:** compare the candidate with both its source manifest and the live
   database inventory. Distinguish expected differences between an older backup
   and today's database from unexplained import differences. Show users/roles,
   stock, bill/detail/payment counts, balances, historical coverage and proposed
   changes. Block activation on unexplained differences.
5. **Activate:** verify the current administrator's password, require a fresh
   CSRF-protected POST and an HTML confirmation naming the old and new databases,
   backup time, current time and the potential work gap. Reauthentication applies
   to the active database before maintenance. Require owner acceptance of the
   age/gap; activation of an older snapshot does not automatically merge newer
   work. Preserve that work in the old DB and the final backup.
6. **Maintenance and acceptance:** freeze all writers, complete the final backup,
   switch configuration atomically, invalidate old sessions, test candidate
   access and perform the read-only acceptance checks below. Reopen writes only
   after owner acceptance. Show the retained old database and backup references.

Suggested job states: quarantined → preflight → creating → importing → validating
→ ready → frozen → backed-up → switching → awaiting-acceptance → active.
Failure/cancellation states retain evidence and identify whether a candidate was
created, activated or eligible for cleanup. A durable job journal must resolve
crashes between configuration replacement and journal updates before proceeding.

## Privileges and execution boundary

Keep the normal web/runtime account at SELECT/INSERT/UPDATE/DELETE on the active
application database. It must not gain CREATE DATABASE, DDL, FILE, global grants
or access to the retained old database merely to support this screen.

An owner-installed CLI/local maintenance helper outside every web root creates
an unpredictable, server-generated candidate name and imports with an account
scoped only to that candidate. A separate provisioning credential creates the DB
and narrowly grants access; it is never the credential executing uploaded SQL.
The helper accepts fixed job operations and validated private job IDs, not shell
commands, SQL text, filesystem paths or connection URLs from a browser. If safe
local execution is unavailable, the screen prepares the job for owner-run CLI;
it must not fall back to privileged web SQL execution.

Provision maintenance credentials through owner-only private files/service
configuration. Never place temporary root credentials in web storage, job JSON,
HTML, session data, command arguments or logs. Host/port/client binary paths are
explicit owner configuration, with approved local targets; a browser cannot
choose an arbitrary network host. Windows CLI/native-client availability, ACLs,
process launch and correct ini/extensions are release gates, not assumptions.

## Windows installer integration

The current [Windows candidate](../../tools/windows/README.md) and
[user guide](../../README.md) describe Apache/PHP 8.4 **Thread Safe** and MySQL 8.4
services, separate from the earlier PHP Desktop/NTS cutover option. The candidate
uses `DoyelWeb` at `127.0.0.1:18080`, `DoyelDB` at `127.0.0.1:13306`, and private
state under `%ProgramData%\Doyel`. It is first-install only and has not passed a
Windows VM installation/acceptance test. It has no update/repair/restore mode.

Build the future owner helper around the private-state layout and process/service
ownership checks in [manage.php](../../tools/windows/manage.php), without running
its first-install path again or deleting retained state. That install path imports
a trusted owner export with root credentials and line-based checks; those checks
are **not** a safe upload boundary for the proposed web feature. Its existing
`verify` path also hardcodes `software`; candidate validation must become
explicitly target-aware through a separately reviewed implementation.

Retain the service-SID ACL boundary: `DoyelWeb` runs as LocalService, reads only
its required config, and modifies logs/sessions/tmp. Keep administrator/SYSTEM-only
maintenance credentials inaccessible to that service; never give LocalService
DBA rights. Add any job-inbox/status/quarantine ACLs narrowly to the dedicated
service SID, with a separate protected owner/helper area. Web requests cannot
modify the authoritative runtime connection file. An elevated local helper must
own config replacement and service coordination, with a restricted IPC/job
contract if automated execution is later chosen. Standard-user daily use does
not imply permission to control Windows services.

During a service-level switch, stop/drain `DoyelWeb` and other writers while
keeping the database service available to the owner helper; recheck service
executable ownership before control operations. UI polling may be unavailable
while Apache is stopped, so the helper needs durable status and a local owner
progress surface; the browser reconnects only after the web service restarts.
Test atomic config replacement/ACL preservation, standard-user behavior, reboot
and interrupted recovery on Windows. Do not apply PHP Desktop/NTS launch steps to
this Apache/TS deployment. Updates remain manual reviewed package work until a
separate installer upgrade/repair design exists.

## Supported input and import safety

The UI supports current-application data-only backup packages with a versioned
manifest, gzip integrity, an exact completed footer and SHA256 of the canonical
payload. Keep the existing SQL download separately usable. Record format version,
app build, canonical schema fingerprint/version, consistent snapshot time,
table columns/counts, next identity counters, reproducible row digests,
financial summaries and date coverage. Produce all rows and inventory from the
same database snapshot. A checksum proves byte integrity, not uploader trust.
No SQL or executable schema definition from the package is executed; column
names and table DDL always come from the reviewed schema shipped with the app.

Existing SQL is explicitly labelled "SQL backup: owner-assisted import/conversion
required". A current-schema owner database can be exported as a restore package
after validation. An old PHP 5 dump routes to the reviewed migration procedure,
never straight to activation. Unknown engine/schema or a newer unsupported
schema is refused. No arbitrary SQL upload execution is supported.

Do not build a homemade SQL parser and treat its keyword checks as authorization
to execute arbitrary SQL. Define the exact accepted exporter format and prove
its provenance/structure through a reviewed input contract; if safe recognition
cannot be established, stop and require owner-assisted conversion. Reject cross-
database references, CREATE/DROP DATABASE, USE, global settings/grants, users,
routines/triggers/events, DEFINER, filesystem operations (including OUTFILE),
LOAD DATA/LOCAL INFILE and client shell/source commands. Comments, quoted strings
and executable comments must not bypass this boundary. Current valid exporter
DROP TABLE statements are allowed only for the expected tables inside the empty
candidate; a blanket DROP rejection would reject today's backup format.

Run the native compatible client noninteractively with controlled options,
binary mode where supported, LOCAL INFILE disabled, client commands disabled as
supported, no `--force`, fixed charset and no shell interpolation. Validate these
options on the actual MySQL/Windows client. Database privileges and, where needed,
an isolated importer process/instance provide containment independently of format
checks. Any statement error aborts the job. Re-enabling foreign-key checks after
import is insufficient: explicitly validate every relationship. Never import with
the provisioning, active-runtime or old-database credentials.

Quarantine uploads outside the web root under random names with restrictive ACLs;
reject path traversal, symlink/reparse-point escapes, unsupported archives and
oversized/truncated files. Define upload/PHP limits, DB-volume and private-volume
space budgets for upload, candidate, validation and the final backup. Check again
before activation; do not assume a SQL file's byte count equals DB disk usage.
Set timeouts and single-job locks without tying execution to an HTTP timeout.

## Validation and data preservation

Before marking ready, require the reviewed current schema: 13 expected InnoDB
utf8mb4 tables, 22 numeric conversions/defaults, the migration-001 indexes,
username uniqueness and four RESTRICT foreign keys. Compare complete metadata
against a canonical schema fingerprint; a count alone is not validation. Use
[the runbook](../runbooks/cutover.md) and migration reports as the baseline.

Compare exact table counts and reproducible row-field digests with the source
manifest before login can rehash passwords. Preserve explicit zero IDs, NULLs,
Unicode/Bengali, fractional quantities, decimal scales and historical dates.
Check orphan references, ID uniqueness, username/role inventory, date ranges,
stock and bill/detail/payment relationships, customer dues and decimal financial
sums. Use numeric normalization only for explicitly reviewed type migrations;
never use normalization to hide removed rows or rounded fractions.

The old PHP 5 migration flow is separate: preview all transformations, explain
FK/uniqueness blockers, back up affected rows, and obtain explicit approval for
any deletions. Do not silently invoke existing cleanup/user-dedup scripts during
restore. If preserving all data requires schema/data repair, stop for a reviewed
migration rather than guess a correction.

Preserve imported accounts and password hashes. Require both current-admin
authorization on the active database and **candidate-admin credential verification
before switching**: reauthenticating against the old database proves no access to
the restored accounts. A controlled candidate verification path must use the
existing password-matching rules without login rehashing, session creation or
other candidate mutations before the digest comparison. Verify ordinary-user
access too; never trust an old session's user ID against a different database.
Record expected password rehashes only after the immutable import comparison.

If nobody knows usable candidate-admin credentials, block activation and route
to a separate owner-authorized offline-helper recovery procedure with identity
verification. The owner must explicitly review which account is added or changed,
its role, the reason, and any attribution implications; preserve the pre-recovery
backup/digests, audit the change without logging passwords/hashes, and inventory
that expected delta. Securely deliver the replacement credential and prove access
before allowing activation. Never silently copy the current admin, reset existing
passwords, or create an undocumented default account. Browser restore authority
alone does not authorize that offline account recovery.

## Activation, retention and recovery

The maintenance lock must stop **all writers**: every browser/session, Desktop
instance, AJAX route, login/password rehash, scheduled task and CLI writer. A flag
shown to one browser or an application-only check cannot stop external writers;
use service/process coordination and DB account/access enforcement as needed,
wait for in-flight transactions to drain, and prove the freeze. Revalidate the
candidate and record the final active inventory while frozen. Take and verify a
complete pre-switch backup, preserve a private/off-server copy and record its
hash before changing the active connection.

Switch the owner-managed private runtime configuration with an exclusive
filesystem lock and tested same-volume atomic replacement. Preserve its previous
version, fsync/durability policy where supported and crash-recovery journal. Do
not edit the release stub or expose credentials in a UI. Account for DOYEL_*
environment overrides: refuse activation when they would mask the chosen config.
Provision a fresh restricted runtime identity for the candidate; retain the
previous private connection reference under owner control. Never grant web
access to old data as a convenience.

`app_config()` and the PDO connection are request-cached: drain existing requests
and ensure new requests/processes load the new connection. Restart persistent
workers/services if present. Introduce a durable session/database generation so
all old sessions are invalidated; clearing only the activating browser is
insufficient. Require fresh authentication against the candidate's own users.

Keep writes frozen while checking admin/ordinary roles, Dashboard, representative
stock/bill/payment/report/closing-balance pages, Bengali text, decimals, and a
backup downloaded and restored into another disposable candidate. Use read-only
business checks by default; login rehashes are controlled and recorded. Mutating
sales/void smoke tests need a separately approved rehearsal or explicit owner
acceptance, not automatic business writes during a restore.

Before business writes resume, rollback may atomically restore the old connection
reference under the same lock/freeze after preserving candidate evidence. After
new business work begins, refuse blind rollback: freeze again, export the modern
state, inventory all intervening work and reconcile it into the selected recovery
state before reopening. Keep both databases. Cleanup may drop **only** an
unactivated, job-owned staging database after identity checks; never active,
previous, accepted or ambiguous databases. Resume/retry must not reuse a partially
imported DB: create a fresh candidate and retain failure evidence.

Record immutable audit references for uploader/admin ID, times, versions,
checksums, inventories, candidate/previous DB identities, approvals, state changes,
maintenance/activation times and error references. Store private evidence outside
the web root; no plaintext SQL, passwords, connection strings or business rows in
ordinary logs. Define retention/owner export for private artifacts; do not
silently delete retained databases or the last verified recovery backup.

## Application updates and version visibility

Add an admin About/maintenance view with installed app version/build, backup
format and schema compatibility, DB version, release notes, required migrations
and pending runtime acceptance gates. Generate build metadata from the reviewed
release packaging process; do not infer it from filenames or expose `.git`.
Current schemas have no declared restore manifest version: introduce an explicit
version/fingerprint mapping and fail closed on unknown combinations.

First update workflow is an owner-installed, versioned package plus the existing
backup/rehearsal/cutover procedure. Keep the previous code/runtime/config for
recovery. The UI may explain compatibility and link reviewed release notes; it
must not download and execute code, self-update PHP/MySQL, or run arbitrary
migrations from the web. Automatic updates are a separate future proposal.

## Implementable slices and acceptance gates

| Slice | Deliverable | Dependencies / evidence required |
| --- | --- | --- |
| 1 | Build/schema metadata and read-only admin version view | Canonical current metadata; packaging allowlist; no secrets in responses |
| 2 | Backup manifest/envelope and inventory verification | Same-snapshot export; footer/checksum/truncation tests; unchanged SQL usability |
| 3 | Private job journal, quarantine and owner helper contract | Privilege separation; ACL/path/disk/limits; concurrent-job and interruption recovery |
| 4 | Candidate-only import and validation, no activation | Native-client containment; supported-format review; exact schema/data comparisons |
| 5 | Admin review/status/reauthentication UI | Slices 1–4; admin+CSRF checks for every operation/artifact; HTML confirmation |
| 6 | Global freeze, final backup, atomic activation/session invalidation | Proven Windows process/config behavior; crash recovery; all-writer inventory |
| 7 | Acceptance/rollback and private audit/retention controls | Frozen rollback rehearsal; post-write reconciliation refusal; owner sign-off |
| 8 | Separate legacy migration review flow | Explicit transformations/deletion previews and backups; existing migration gates |

Test known current backups, absent/wrong manifests, missing footer, checksum
mismatch, unsupported/newer/PHP 5 schemas, malicious cross-DB/client commands,
missing native binaries, denied grants, full disk, oversized upload, Unicode and
fractional history. Prove no import can touch active/old DBs or filesystem data.
Test failures after DB creation, mid-import, validation, final backup and atomic
switch, duplicate requests, stale sessions, in-flight/external writers and process
restart. Rehearse counts/bytes/balances round trips and both rollback boundaries.
Test administrator lockout, wrong candidate-admin passwords, unknown credentials,
and the explicit offline recovery gate. Test admin versus ordinary/anonymous
access, CSRF and password failure, private
artifact HTTP denial and redacted errors. Windows/Desktop/MySQL acceptance is
required on the actual target; MariaDB requires its own reviewed evidence.

Implementation is complete only when a real supported backup restores into a new
DB with matching evidence, the old DB/verified backup remain recoverable, every
writer is frozen for activation, roles and data pass acceptance, and failure
recovery has been rehearsed. This document authorizes no implementation or
production operation by itself.
