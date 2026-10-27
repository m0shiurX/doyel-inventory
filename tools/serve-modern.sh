#!/usr/bin/env bash
set -euo pipefail
usage() {
    cat <<'USAGE'
Usage: tools/serve-modern.sh <port> [dbname]

Serve this checkout on 127.0.0.1 using PHP 8.4 (8.2+ compatible code).
Connects to local modern MySQL on 127.0.0.1:33084. Default DB: software.
Defaults DOYEL_ENV=development so E_ALL diagnostics remain visible in tests.
Example: tools/serve-modern.sh 8123 doyel_s23
This legacy app needs the Phase 2 port before it runs on modern PHP.
USAGE
}
[ "${1:-}" != -h ] && [ "${1:-}" != --help ] || { usage; exit 0; }
[ "$#" -ge 1 ] && [ "$#" -le 2 ] || { usage >&2; exit 1; }
port=$1
dbname=${2:-software}
[[ "$port" =~ ^[0-9]{4,5}$ ]] && [ "$port" -ge 1024 ] && [ "$port" -le 65535 ] || { printf 'Port must be 1024..65535\n' >&2; exit 1; }
[[ "$dbname" =~ ^(software|doyel_[A-Za-z0-9_]+)$ ]] || { printf 'Invalid local database name\n' >&2; exit 1; }
command -v php >/dev/null || { printf 'PHP CLI is required\n' >&2; exit 1; }
php -r 'if (PHP_VERSION_ID < 80200 || !extension_loaded("pdo_mysql") || !extension_loaded("dom")) {fwrite(STDERR,"PHP 8.2+ with pdo_mysql and dom is required\n"); exit(1);}'
repo=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
export DOYEL_ENV=${DOYEL_ENV:-development}
export DOYEL_DB_HOST=127.0.0.1 DOYEL_DB_PORT=33084 DOYEL_DB_USER=root DOYEL_DB_PASS=doyel-local DOYEL_DB_NAME="$dbname"
exec php -S "127.0.0.1:$port" -t "$repo"
