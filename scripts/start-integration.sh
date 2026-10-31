#!/usr/bin/env bash
set -euo pipefail

repo="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
php_binary="${1:-php}"
parent="$(dirname -- "$repo")"
f1_path="${F1_PATH:-$parent/_integrasi/F1}"
d2_path="${D2_PATH:-$parent/_integrasi/D2}"
if [[ -z "${F1_PATH:-}" && -f "$parent/PPL_F1/demo/local-server.js" ]]; then f1_path="$parent/PPL_F1"; fi
if [[ -z "${D2_PATH:-}" && -f "$parent/PPL-D-2/artisan" ]]; then d2_path="$parent/PPL-D-2"; fi

export NVM_DIR="${NVM_DIR:-$HOME/.nvm}"
if [[ ! -s "$NVM_DIR/nvm.sh" ]]; then
    echo 'nvm tidak ditemukan. Pasang nvm di akun pengguna, lalu jalankan ulang integrasi.' >&2
    exit 1
fi
# nvm belum sepenuhnya kompatibel dengan nounset pada semua versi.
set +u
source "$NVM_DIR/nvm.sh"
if [[ -f "$f1_path/.nvmrc" ]]; then
    cd -- "$f1_path"
    nvm use || { nvm install && nvm use; }
elif [[ -f "$repo/.nvmrc" ]]; then
    cd -- "$repo"
    nvm use || { nvm install && nvm use; }
else
    nvm use default
fi
set -u
cd -- "$repo"
exec "$php_binary" scripts/linux-integration.php start "$f1_path" "$d2_path"
