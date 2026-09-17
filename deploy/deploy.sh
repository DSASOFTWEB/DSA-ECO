#!/usr/bin/env bash
# Compatibilidade: o fluxo dia a dia é o atualize.sh na raiz (igual Zeusweb).
#
# Uso:
#   bash deploy/deploy.sh
#   ssh vps-dsa-eco "bash /var/www/dsa-eco/deploy/deploy.sh"
#
# Preferível:
#   ./atualize.sh
#   ssh vps-dsa-eco "bash /var/www/dsa-eco/atualize.sh"
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"

export REPO_DIR
exec bash "${REPO_DIR}/atualize.sh" "$@"
