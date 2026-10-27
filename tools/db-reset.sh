#!/usr/bin/env bash
# Reset ONLY the isolated local Compose service. Never points at production.
set -euo pipefail
usage() {
    cat <<'USAGE'
Usage: tools/db-reset.sh <legacy|modern> [dbname]

Drop/recreate a local fixture database and import $DOYEL_FIXTURES/dump.sql.
Default fixture directory: ~/.doyel-fixtures. Default database: software.
Other database names must start with doyel_. This erases that local database.
Requires Docker Compose; start its matching profile first.
DOYEL_DB_MIGRATIONS optionally lists comma-separated migration IDs (e.g. 001).
Each ID must resolve to exactly one repository db/migrations/NNN_*.up.sql file.
USAGE
}
[ "${1:-}" != -h ] && [ "${1:-}" != --help ] || { usage; exit 0; }
[ "$#" -ge 1 ] && [ "$#" -le 2 ] || { usage >&2; exit 1; }
case "$1" in legacy|modern) target=$1 ;; *) usage >&2; exit 1 ;; esac
dbname=${2:-software}
[[ "$dbname" =~ ^(software|doyel_[A-Za-z0-9_]+)$ ]] || { printf 'Invalid local database name\n' >&2; exit 1; }
fixture_dir=${DOYEL_FIXTURES:-$HOME/.doyel-fixtures}
[ -r "$fixture_dir/dump.sql" ] || { printf 'Missing fixture: %s/dump.sql\n' "$fixture_dir" >&2; exit 1; }
command -v docker >/dev/null || { printf 'Docker is required\n' >&2; exit 1; }
repo=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
# Resolve all requested source migrations before any destructive operation.
# No arbitrary path or SQL input is accepted; unset keeps the original reset.
migration_files=()
if [ -n "${DOYEL_DB_MIGRATIONS:-}" ]; then
    [[ "$DOYEL_DB_MIGRATIONS" =~ ^[0-9]{3}(,[0-9]{3})*$ ]] || { printf 'Invalid migration ID list\n' >&2; exit 1; }
    IFS=',' read -r -a migration_ids <<< "$DOYEL_DB_MIGRATIONS"
    seen_ids=','
    for migration_id in "${migration_ids[@]}"; do
        case "$seen_ids" in *",$migration_id,"*) printf 'Duplicate migration ID\n' >&2; exit 1 ;; esac
        seen_ids="$seen_ids$migration_id,"
        shopt -s nullglob
        matches=("$repo/db/migrations/${migration_id}_"*.up.sql)
        shopt -u nullglob
        [ "${#matches[@]}" -eq 1 ] && [ -f "${matches[0]}" ] && [ ! -L "${matches[0]}" ] || { printf 'Migration ID must resolve to one regular source file: %s\n' "$migration_id" >&2; exit 1; }
        migration_files+=("${matches[0]}")
    done
fi
compose=(docker compose -f "$repo/docker/compose.yaml" --profile "$target")
mysql_local() {
    "${compose[@]}" exec -T -e MYSQL_PWD=doyel-local "$target-db" \
        mysql -uroot --default-character-set=utf8 --batch --skip-column-names "$@"
}
# Exact identifier validation above allows safe backtick quoting here.
mysql_local -e "DROP DATABASE IF EXISTS \`$dbname\`; CREATE DATABASE \`$dbname\` CHARACTER SET utf8 COLLATE utf8_unicode_ci;"
# Restrict lenient mode to this import connection. MySQL 8 keeps its default mode
# for every subsequent app/CLI connection; the original dump stays unchanged.
{
    printf "SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION'; SET NAMES utf8; SET autocommit=0;\n"
    cat "$fixture_dir/dump.sql"
    printf "\nUPDATE l_user SET password=MD5('admin-test') WHERE user_id=17;\n"
    printf "UPDATE l_user SET password=MD5('user-test') WHERE user_id=24; COMMIT;\n"
} | mysql_local "$dbname"
# Each migration uses a fresh connection with the server's normal SQL mode.
if [ -n "${DOYEL_DB_MIGRATIONS:-}" ]; then
    for migration_file in "${migration_files[@]}"; do
        mysql_local "$dbname" < "$migration_file"
        printf 'Applied local source migration %s\n' "$(basename "$migration_file")"
    done
fi
printf 'Reset local %s database %s. Row counts:\n' "$target" "$dbname"
mysql_local "$dbname" -e "SELECT 'canteen_ac_head',COUNT(*) FROM canteen_ac_head
UNION ALL SELECT 'canteen_collection',COUNT(*) FROM canteen_collection
UNION ALL SELECT 'canteen_customar',COUNT(*) FROM canteen_customar
UNION ALL SELECT 'canteen_expenses',COUNT(*) FROM canteen_expenses
UNION ALL SELECT 'canteen_item',COUNT(*) FROM canteen_item
UNION ALL SELECT 'canteen_purchase',COUNT(*) FROM canteen_purchase
UNION ALL SELECT 'canteen_purchase_detail',COUNT(*) FROM canteen_purchase_detail
UNION ALL SELECT 'canteen_sales',COUNT(*) FROM canteen_sales
UNION ALL SELECT 'canteen_sales_detail',COUNT(*) FROM canteen_sales_detail
UNION ALL SELECT 'canteen_stock',COUNT(*) FROM canteen_stock
UNION ALL SELECT 'canteen_stock_detail',COUNT(*) FROM canteen_stock_detail
UNION ALL SELECT 'canteen_supplier',COUNT(*) FROM canteen_supplier
UNION ALL SELECT 'l_user',COUNT(*) FROM l_user;"
