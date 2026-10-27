#!/usr/bin/env bash
set -euo pipefail
if [ "${1:-}" = -h ] || [ "${1:-}" = --help ]; then
    printf 'Usage: tools/characterize/compare.sh <golden-dir> <candidate-dir>\nCompares all extracted labels and HTTP status; errors are reported separately.\n'
    exit 0
fi
[ "$#" -eq 2 ] || { printf 'Usage: tools/characterize/compare.sh <golden-dir> <candidate-dir>\n' >&2; exit 1; }
exec php "$(dirname "${BASH_SOURCE[0]}")/compare.php" "$@"
