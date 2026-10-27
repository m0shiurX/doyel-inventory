#!/usr/bin/env bash
set -euo pipefail
if [ "${1:-}" = -h ] || [ "${1:-}" = --help ]; then
    printf 'Usage: tools/lint.sh <file.php> [...]\n'
    exit 0
fi
[ "$#" -gt 0 ] || { printf 'Usage: tools/lint.sh <file.php> [...]\n' >&2; exit 1; }
for file in "$@"; do php -l "$file"; done
