#!/usr/bin/env bash
set -euo pipefail
repo="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
php_binary="${1:-php}"
d2_driver="${2:-http}"
cd -- "$repo"
f1_path="$("$php_binary" scripts/integration-runtime.php "$d2_driver" f1-path)"
exec bash scripts/integration-node.sh "$f1_path" run "$php_binary" "$repo/scripts/linux-integration.php" start "$d2_driver"
