# Local history purge (S0.1)

Q5 authorizes removing client SQL dumps from local git history before any remote
push. The rewrite changes commit IDs, including the owner's recent commit.
This is a one-time **pre-migration** operation: it refuses to run once migration
or cleanup SQL is tracked. Future history cleanup needs path-specific filtering
that preserves source SQL. This procedure never pushes. It leaves the app's tracked source bytes unchanged.

## Preservation and preparation

On 2026-10-27, all three local dumps were preserved with matching SHA-256 checksums
in the private `/private/tmp/doyel-fixtures/` directory:

- `dump.sql`: the 2026-10-25 fixture.
- `source-backups/`: the older SQL and zipped SQL backups.

Directories have mode 700 and dump files mode 600. Set
`DOYEL_FIXTURES=/private/tmp/doyel-fixtures` for subsequent local tooling. This is
a temporary workspace, not durable storage; retain a private durable copy before
OS temporary-file cleanup. No dump contents belong in git, screenshots or reports.

The historical inventory found the root path
`software-sql-backup-25-10-2026--11-01-41.sql` in both pre-cleanup commits. The
working copies under `docs/` and `unsused_Backups/myBackups/` were untracked.
After checksum verification, repository preparation removed those working copies
and the approved unused backup directory.

Commit all intended preparation changes before running the script. `.gitignore`
excludes raw/compressed SQL, fixtures, logs, local config, graph cache and host
metadata. Narrow exceptions keep `db/migrations/**/*.sql` and
`db/cleanup/**/*.sql` eligible for commits; those files are source code.
Verify deleted paths using literal examples even when files no longer
exist:

```bash
git check-ignore --no-index -v docs/example.sql unsused_Backups/myBackups/example.sql.zip
git status --porcelain=v1 --untracked-files=all
```

The attributes use `text=auto eol=lf` for PHP, JavaScript and CSS. Git's automatic
text handling preserves existing committed CRLF blobs, avoiding the immediate
mass modification caused by unconditional `text eol=lf`. New text files use LF.
Do not run `git add --renormalize`; line-ending conversion needs its own review.

## Execution

From the repository root, choose a new recovery directory outside the repo:

```bash
tools/purge-history.sh --execute \
  --fixtures /private/tmp/doyel-fixtures \
  --recovery /private/tmp/doyel-recovery/pre-purge
```

The recovery parent must already exist. The target must not exist. The script
refuses a dirty worktree, extra linked worktrees, or a fixture/recovery path within
the repository. Before rewriting it creates and verifies a git bundle, archives
the working tree including ignored local files (excluding `.git` and regenerable
`graft`), preserves the git config, and records refs, source and fixture checksums.
The recovery data contains sensitive client history; keep it private.

This checkout is non-fresh by design. Only after those checks and recovery copies
succeed does the script execute:

```bash
git filter-repo --force --path-glob '*.sql' --path-glob '*.sql.zip' \
  --path-glob '*.sql.gz' --invert-paths
```

`--force` is justified by the verified recovery copies and known local checkout;
it is not a general instruction to bypass safety checks. If an inventory finds a
dump under another suffix, extend both the filtering and verification paths before
execution. `git-filter-repo` may remove the origin remote as a safety measure;
its prior configuration is preserved privately. Do not restore a remote and push
without a separate review of the now rewritten history.

## Verification and recovery

The script verifies that every tracked non-dump source checksum is unchanged,
`dump.sql` still matches, the working tree is clean, and no SQL/compressed SQL
paths occur in any reachable local ref. It saves the before/after HEADs and the
commit map under the recovery directory. Report those results in the slice log;
never report success merely because filter-repo returned zero.

For recovery, clone the private bundle into a separate directory and inspect it:

```bash
git clone /private/tmp/doyel-recovery/pre-purge/history-before.bundle /private/tmp/doyel-recovered
```

The archive additionally contains local working files. Extract it into a new
private directory if needed. Never blindly overwrite the active checkout or push
the sensitive recovered history. Keep the original private recovery copy until
the owner is satisfied with the sanitized repository.

## Execution record

Pending the orchestrator's preparation commit. Record the actual command,
before/after HEADs and all verification results here after execution.
