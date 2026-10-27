#!/usr/bin/env bash
set -euo pipefail
if [ "${1:-}" = -h ] || [ "${1:-}" = --help ]; then
    printf 'Usage: tools/scenarios/07-admin-bills.sh <base-url> <legacy|modern> <out-dir>\n'
    exit 0
fi
[ "$#" -eq 3 ] || { printf 'Expected base URL, DB target and output directory\n' >&2; exit 1; }
exec php "$(dirname "${BASH_SOURCE[0]}")/run.php" 7 "$@"
