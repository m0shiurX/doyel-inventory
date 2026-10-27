#!/usr/bin/env bash
set -euo pipefail
if [ "${1:-}" = -h ] || [ "${1:-}" = --help ]; then
    cat <<'USAGE'
Usage: tools/perf.sh [--snapshot] <base-url> <legacy|modern> [dbname]

Replay the characterization manifest in the full-history report range.
Each read-only label gets five timed requests, with app SQL counted separately.
--snapshot resets the isolated local fixture and measures one stock save instead.
Private logs go beneath $DOYEL_FIXTURES/perf (default ~/.doyel-fixtures/perf).
DOYEL_PERF_LABELS optionally selects comma-separated read-only base labels.
DOYEL_PERF_OUT optionally selects a new directory under the private perf folder.
Requires exclusive use of the target test server/database during measurement.
USAGE
    exit 0
fi
exec php "$(dirname "${BASH_SOURCE[0]}")/perf.php" "$@"
