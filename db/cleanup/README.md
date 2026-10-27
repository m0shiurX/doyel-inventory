# S3.2 maintenance cleanup

`cleanup.php` requires PHP 8.2+ with PDO MySQL and MySQL 8.0+/MariaDB 10.6+.
Run after 001 and 002, before 003/004. No schema changes are made.
The local rehearsal uses PHP 8.4.27/MySQL 8.4.13. Windows/MariaDB execution
has not been tested.

Default action is preview, and **every invocation requires `--as-of=YYYY-MM-DD`**.
The midnight clock and both strict cutoffs use Asia/Dhaka, independent of host
or session timezone. Local fixture rehearsal used `2026-10-28`: snapshot cutoff
`2024-10-28 00:00:00`, sales cutoff `2026-09-28 00:00:00`.
For a future production run choose and review a fresh explicit date.

Stop all writers before preview and keep them stopped through backup, apply and
verification. Application bill writes lock parent before children; cleanup uses
that order too, with full ordered InnoDB parent/child locking scans and gap locks.
Deletion, including snapshot children followed by headers, is one transaction.
No current stock values are changed to compensate for old orphan details.
Invalid snapshot dates and orphan snapshot details are reported and retained.

Before production, the owner must review the actual production counts/IDs,
confirm the retention date, take a full external database dump with the host's
existing tested backup procedure, and rehearse restoration into a separate
instance. The script additionally requires a new private JSON backup path:
it captures all 13 tables, all original fields, the exact preview and private
integrity hashes before the first delete. Keep both backup forms outside the
web root and repository. The JSON is evidence/recovery data; use the tested SQL
dump restoration procedure for disaster recovery. Never publish backups or hashes.
On Windows create a private directory with owner-only ACLs first; Unix uses
mode 0600 for new files. POSIX, drive-qualified and UNC absolute paths are
accepted; only path syntax/case normalization was tested for Windows on macOS.
Existing output files and symlinks are refused.

Set `DOYEL_DB_HOST`, `DOYEL_DB_PORT`, `DOYEL_DB_NAME`, `DOYEL_DB_USER` and
`DOYEL_DB_PASS` in the owner's environment. Outside isolated `doyel_*` databases
on `127.0.0.1:33084`, the explicit `--allow-production` switch is required for
both preview and apply. The following are **owner-run templates**, not commands
executed against production in this slice. Replace the private absolute paths
and explicit date before use (Windows example paths can use `C:/private/...`).

```bash
php -d memory_limit=512M db/cleanup/cleanup.php --allow-production \
  --as-of=2026-10-28 --report=/private/new-preview.json
# Review report counts/IDs; preserve tested external SQL backup; writers stay stopped.
php -d memory_limit=512M db/cleanup/cleanup.php --allow-production \
  --as-of=2026-10-28 --apply=/private/new-preview.json --backup=/private/new-backup.json
php -d memory_limit=512M db/cleanup/cleanup.php --allow-production \
  --as-of=2026-10-28 --report=/private/after-preview.json
```

Apply recomputes the entire preview and all table fingerprints under locks and
refuses any stale/different report before backup or deletion. Re-preview and
review again if data changed. After deletion, every surviving row/field in all
13 tables must exactly match its pre-delete value before COMMIT; otherwise
rollback. Output contains counts/IDs and peak memory only. The very large snapshot
detail ID set is kept in the private report; console output identifies it by
parent SlNo. Read-only numeric profiling reports fractional quantities,
meaningful unit-price precision and out-of-target-range IDs for S3.3, changing none.

This CLI loads the fixture and serializes the full backup in memory. The actual
original 255,770-row fixture used **249,790,464 bytes** peak for preview and
**272,269,312 bytes** for apply. Use at least `memory_limit=512M` for this fixture;
allow more for a larger production database. The rehearsal CLI default was `-1`.
Default 128M or 256M settings are insufficient for this apply. No Windows memory
claim is made. Never increase the web runtime limit merely for this CLI task.

For local regression, use the opt-in `tools/db-reset.sh` hook documented in
`docs/runbooks/local-development.md`; each scenario gets a fresh cleaned fixture.
Synthetic safety verification creates only `doyel_s32_guard` on local port 33084:

```bash
DOYEL_DB_NAME=doyel_s32 php db/cleanup/verify.php /private/new-guard-evidence
```

The fault injection `--fail-after-delete=N` exists only for isolated local
fixtures and is refused before connection in production mode. Verification tests
actual mid-delete rollback, stale refusal, both cutoff boundaries to one second,
nonempty/posted bill protection, orphan/invalid-date snapshot retention and rerun
idempotence. Every PHP file in this directory returns empty HTTP 404.
