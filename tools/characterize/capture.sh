#!/usr/bin/env bash
set -euo pipefail
if [ "${1:-}" = -h ] || [ "${1:-}" = --help ]; then
    printf 'Usage: tools/characterize/capture.sh <base-url> <out-dir>\nSee tools/characterize/README.md for fixture DB environment settings.\n'
    exit 0
fi
[ "$#" -eq 2 ] || { printf 'Usage: tools/characterize/capture.sh <base-url> <out-dir>\n' >&2; exit 1; }
exec php "$(dirname "${BASH_SOURCE[0]}")/capture.php" "$@"
