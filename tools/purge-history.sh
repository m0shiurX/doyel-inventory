#!/usr/bin/env bash
# Q5 authorizes a LOCAL rewrite only. This script never pushes.
set -euo pipefail
umask 077

usage() {
    cat <<'USAGE'
Usage: tools/purge-history.sh --execute --fixtures DIR --recovery NEW_DIR

Remove .sql, .sql.zip and .sql.gz files from all reachable local git history.
First commit all intended source changes. The working tree must be clean.
DIR must already contain the privately preserved dump.sql fixture.
NEW_DIR must be a new, private directory outside this repository.
The script creates a recovery bundle and source archive, checks their integrity,
then rewrites history and verifies tracked source checksums and fixture checksum.
It does not delete ignored working-tree files or contact any remote.
USAGE
}
fail() { printf 'Error: %s\n' "$*" >&2; exit 1; }
execute=false
fixtures=''
recovery=''
while [ "$#" -gt 0 ]; do
    case "$1" in
        -h|--help) usage; exit 0 ;;
        --execute) execute=true; shift ;;
        --fixtures|--recovery)
            [ "$#" -ge 2 ] || fail "$1 requires a directory"
            case "$1" in
                --fixtures) fixtures=$2 ;;
                --recovery) recovery=$2 ;;
            esac
            shift 2 ;;
        *) usage >&2; fail "Unknown argument: $1" ;;
    esac
done
[ "$execute" = true ] || { usage >&2; fail 'Use --execute only after the preparation commit'; }
[ -n "$fixtures" ] && [ -n "$recovery" ] || fail 'Both directory arguments are required'
for dependency in git tar shasum; do
    command -v "$dependency" >/dev/null || fail "Missing dependency: $dependency"
done
git filter-repo -h >/dev/null || fail 'git-filter-repo is required'
root=$(git rev-parse --show-toplevel)
cd "$root"
[ -z "$(git ls-files 'db/migrations/*.sql' 'db/migrations/**/*.sql' 'db/cleanup/*.sql' 'db/cleanup/**/*.sql')" ] || fail 'One-time pre-migration tool: tracked migration or cleanup SQL exists; do not purge source SQL'
[ -z "$(git status --porcelain=v1 --untracked-files=all)" ] || fail 'Commit intended changes; working tree is not clean'
[ -z "$(git worktree list --porcelain | sed -n 's/^worktree //p' | sed -n '2p')" ] || fail 'Remove additional worktrees before rewriting shared refs'
[ -f "$fixtures/dump.sql" ] || fail 'Fixture dump.sql is missing'
fixtures=$(cd "$fixtures" && pwd -P)
case "$fixtures/" in "$root/"*) fail 'Fixtures must be outside the repository' ;; esac
[ ! -e "$recovery" ] || fail 'Recovery directory must not already exist'
recovery_parent=$(dirname "$recovery")
[ -d "$recovery_parent" ] || fail 'Recovery parent must already exist'
recovery="$(cd "$recovery_parent" && pwd -P)/$(basename "$recovery")"
case "$recovery/" in "$root/"*) fail 'Recovery directory must be outside the repository' ;; esac
mkdir -m 700 "$recovery"
git rev-parse HEAD > "$recovery/head-before.txt"
git show-ref > "$recovery/refs-before.txt"
git log --all --format= --name-only -- '*.sql' '*.sql.zip' '*.sql.gz' > "$recovery/dump-paths-before.txt"
git remote -v > "$recovery/remotes-before.txt"
shasum -a 256 "$fixtures/dump.sql" > "$recovery/fixture-before.sha256"
cp .git/config "$recovery/git-config-before"
# A bundle skips direct tree refs used by Codex's local diff snapshots. Preserve
# the object database too, so those app refs remain privately recoverable.
tar -czf "$recovery/git-before.tar.gz" .git
tar -tzf "$recovery/git-before.tar.gz" > "$recovery/git-archive-files.txt"
git bundle create "$recovery/history-before.bundle" --all
git bundle verify "$recovery/history-before.bundle" > "$recovery/bundle-verification.txt" 2>&1
# Preserve ignored/untracked local data too; graph cache is regenerable.
tar --exclude='./.git' --exclude='./graft' -czf "$recovery/worktree-before.tar.gz" .
tar -tzf "$recovery/worktree-before.tar.gz" > "$recovery/archive-files.txt"
manifest() {
    git ls-files -z | while IFS= read -r -d '' path; do
        case "$path" in *.sql|*.sql.zip|*.sql.gz) continue ;; esac
        shasum -a 256 "$path"
    done
}
manifest > "$recovery/source-before.sha256"
# Sanitize direct tree refs too: filter-repo skips non-commit app snapshot refs.
# Use a separate temporary index; do not disturb the real working index.
git for-each-ref --format='%(refname) %(objectname) %(objecttype)' > "$recovery/ref-types-before.txt"
while IFS=' ' read -r ref oid kind; do
    [ "$kind" = tree ] || continue
    tree_index="$recovery/tree-index"
    GIT_INDEX_FILE="$tree_index" git read-tree "$oid"
    git ls-tree -r -z --name-only "$oid" | while IFS= read -r -d '' path; do
        case "$path" in
            *.sql|*.sql.zip|*.sql.gz)
                GIT_INDEX_FILE="$tree_index" git update-index --force-remove -- "$path" ;;
        esac
    done
    new_tree=$(GIT_INDEX_FILE="$tree_index" git write-tree)
    git update-ref "$ref" "$new_tree" "$oid"
    rm "$tree_index"
done < "$recovery/ref-types-before.txt"
# This checkout is intentionally non-fresh. --force is used only AFTER the clean
# tree, single-worktree, verified bundle/archive and source-manifest safeguards.
git filter-repo --force --path-glob '*.sql' --path-glob '*.sql.zip' --path-glob '*.sql.gz' --invert-paths
manifest > "$recovery/source-after.sha256"
cmp "$recovery/source-before.sha256" "$recovery/source-after.sha256" || fail 'Source checksums changed; inspect the private recovery copy'
shasum -a 256 -c "$recovery/fixture-before.sha256"
[ -z "$(git rev-list --objects --all | sed -n '/\.sql$/p; /\.sql\.zip$/p; /\.sql\.gz$/p')" ] || fail 'Dump paths remain reachable, including non-commit refs'
[ -z "$(git ls-files '*.sql' '*.sql.zip' '*.sql.gz')" ] || fail 'Dump paths remain tracked'
[ -z "$(git status --porcelain=v1 --untracked-files=all)" ] || fail 'Working tree changed unexpectedly'
git rev-parse HEAD > "$recovery/head-after.txt"
cp .git/filter-repo/commit-map "$recovery/commit-map.txt"
printf 'History purge verified. Private recovery: %s\n' "$recovery"
printf 'Before: %s\nAfter:  %s\n' "$(cat "$recovery/head-before.txt")" "$(cat "$recovery/head-after.txt")"
printf 'No push performed. Preserve the fixture and recovery directories privately.\n'
