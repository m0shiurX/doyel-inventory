#!/usr/bin/env bash
# Owner-run Phase 1 DDL. This never creates a backup or auto-recovers partial DDL.
set -euo pipefail
usage() {
    cat <<'USAGE'
Usage: tools/prod/apply-migration.sh <--check|--apply|--rollback>

Uses MYSQL_CLIENT (default mysql), DOYEL_DB_HOST/PORT/USER/NAME
(default localhost/3306/root/software), MYSQL_PWD or DOYEL_MYSQL_DEFAULTS_FILE.
Apply/rollback additionally require DOYEL_DB_FREE_BYTES measured on the DB volume
and DOYEL_PHASE1_BACKUP pointing to a nonempty, completed dump outside this repo.
--check only reads metadata. Stop application writes and follow the runbook first.
USAGE
}
fail() { printf 'Error: %s\n' "$*" >&2; exit 1; }
[ "${1:-}" != -h ] && [ "${1:-}" != --help ] || { usage; exit 0; }
[ "$#" -eq 1 ] || { usage >&2; exit 1; }
case "$1" in --check|--apply|--rollback) action=$1 ;; *) usage >&2; exit 1 ;; esac
script_dir=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
repo=$(cd "$script_dir/../.." && pwd)
host=${DOYEL_DB_HOST:-localhost}
port=${DOYEL_DB_PORT:-3306}
user=${DOYEL_DB_USER:-root}
database=${DOYEL_DB_NAME:-software}
[[ "$host" =~ ^[A-Za-z0-9_.:-]+$ ]] || fail 'Invalid host'
[[ "$port" =~ ^[0-9]{1,5}$ ]] && [ "$((10#$port))" -ge 1 ] && [ "$((10#$port))" -le 65535 ] || fail 'Invalid port'
[[ "$user" =~ ^[A-Za-z0-9_.-]+$ ]] || fail 'Invalid migration username'
[[ "$database" =~ ^[A-Za-z0-9_]+$ ]] || fail 'Invalid database name'
client=("${MYSQL_CLIENT:-mysql}")
command -v "${client[0]}" >/dev/null || fail 'mysql client executable not found'
if [ -n "${DOYEL_MYSQL_DEFAULTS_FILE:-}" ]; then
    [ -r "$DOYEL_MYSQL_DEFAULTS_FILE" ] || fail 'Client defaults file is unreadable'
    client+=("--defaults-extra-file=$DOYEL_MYSQL_DEFAULTS_FILE")
fi
client+=(--protocol=TCP "--host=$host" "--port=$port" "--user=$user" --batch --raw --skip-column-names "$database")
state_file="$script_dir/phase1-state.sql"
[ -r "$state_file" ] || fail 'Canonical metadata preflight is missing'
metadata=$("${client[@]}" < "$state_file") || fail 'Metadata preflight failed; no migration executed'
IFS=$'\t' read -r state version db_bytes data_directory <<< "$metadata"
[[ "$version" =~ ^([0-9]+)\.([0-9]+) ]] || fail 'Unrecognized server version'
major=${BASH_REMATCH[1]}; minor=${BASH_REMATCH[2]}
[ "$((10#$major))" -gt 5 ] || { [ "$((10#$major))" -eq 5 ] && [ "$((10#$minor))" -ge 5 ]; } || fail 'MySQL/MariaDB 5.5+ required'
printf 'Target %s:%s/%s; version %s; state %s; database bytes %s; datadir %s\n' "$host" "$port" "$database" "$version" "$state" "$db_bytes" "$data_directory"
[ "$state" != PARTIAL_OR_CONFLICT ] || fail 'Partial/conflicting indexes or unexpected stock ID schema; review SHOW INDEX and restore/recover manually'
show_indexes() {
    for table in canteen_sales canteen_sales_detail canteen_purchase canteen_purchase_detail canteen_collection canteen_expenses canteen_stock_detail; do
        printf 'Indexes: %s\n' "$table"
        "${client[@]}" -e "SHOW INDEX FROM \`$table\`;"
    done
}
if [ "$action" = --check ]; then show_indexes; exit 0; fi
if [ "$action" = --apply ]; then
    [ "$state" = BASELINE ] || fail '001 already applied; no ALTER executed'
    source_sql="$repo/db/migrations/001_indexes.up.sql"
    expected=APPLIED
else
    [ "$state" = APPLIED ] || fail '001 is not fully applied; no rollback executed'
    source_sql="$repo/db/migrations/001_indexes.down.sql"
    expected=BASELINE
fi
[ -r "$source_sql" ] || fail 'Reviewed migration source is missing'
free_bytes=${DOYEL_DB_FREE_BYTES:-}
[[ "$db_bytes" =~ ^[0-9]{1,18}$ ]] && [[ "$free_bytes" =~ ^[0-9]{1,18}$ ]] || fail 'Provide measured DOYEL_DB_FREE_BYTES as integer bytes (up to 18 digits)'
[ "$((10#$free_bytes))" -ge "$((2 * 10#$db_bytes))" ] || fail 'Free DB-volume space must be at least twice the database size'
backup=${DOYEL_PHASE1_BACKUP:-}
[ -r "$backup" ] && [ -s "$backup" ] || fail 'A nonempty, readable pre-Phase-1 backup is required'
backup_path="$(cd "$(dirname "$backup")" && pwd -P)/$(basename "$backup")"
case "$backup_path" in "$repo"/*) fail 'Keep client backups outside this repository' ;; esac
[[ "$(tail -n 1 "$backup")" = '-- Dump completed'* ]] || fail 'Backup lacks its completed-dump footer'
printf 'Disk and completed-backup prechecks passed. Executing %s.\n' "$(basename "$source_sql")"
if ! "${client[@]}" < "$source_sql"; then
    fail 'DDL stopped on an error; earlier ALTERs may be committed. Keep writes frozen and recover manually from the runbook'
fi
metadata=$("${client[@]}" < "$state_file") || fail 'Post-migration metadata check failed; keep writes frozen'
IFS=$'\t' read -r state version db_bytes data_directory <<< "$metadata"
[ "$state" = "$expected" ] || fail 'Unexpected post-migration state; keep writes frozen and inspect schema'
show_indexes
printf 'Verified migration state: %s\n' "$state"
