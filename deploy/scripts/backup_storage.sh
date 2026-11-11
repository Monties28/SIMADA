#!/usr/bin/env bash
set -euo pipefail
APP_DIR="${APP_DIR:-/var/www/simada}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/simada}"
ENV_FILE="${APP_DIR}/.env"
if [[ -f "${ENV_FILE}" ]]; then
  set -a
  # shellcheck disable=SC1090
  source "${ENV_FILE}"
  set +a
fi
STORAGE_DIR="${SIMADA_STORAGE_PATH:-/var/lib/simada/storage}"
mkdir -p "${BACKUP_DIR}"
OUT="${BACKUP_DIR}/simada_storage_$(date +%Y%m%d_%H%M%S).tar.gz"
tar -czf "${OUT}" -C "$(dirname "${STORAGE_DIR}")" "$(basename "${STORAGE_DIR}")"
chmod 600 "${OUT}"
echo "Backup storage dibuat: ${OUT}"
