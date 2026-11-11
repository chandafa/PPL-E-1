#!/usr/bin/env bash
set -eo pipefail

repository="$1"
action="$2"
repo="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
export NVM_DIR="${NVM_DIR:-$HOME/.nvm}"
if [[ ! -s "$NVM_DIR/nvm.sh" ]]; then
    echo 'nvm tidak ditemukan. Pasang nvm di akun pengguna.' >&2
    exit 1
fi
source "$NVM_DIR/nvm.sh"
if [[ -f "$repository/.nvmrc" ]]; then
    cd -- "$repository"
    nvm use || { [[ "$action" == install ]] && nvm install && nvm use; }
elif [[ -f "$repo/.nvmrc" ]]; then
    cd -- "$repo"
    nvm use || { [[ "$action" == install ]] && nvm install && nvm use; }
else
    nvm use default
fi
node "$repo/scripts/check-integration-node.cjs"
cd -- "$repository"
if [[ "$action" == install ]]; then
    npm ci --ignore-scripts
else
    shift 2
    exec "$@"
fi
