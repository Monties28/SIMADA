#!/usr/bin/env bash
set -euo pipefail
APP_DIR="${APP_DIR:-/var/www/simada}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/simada}"
ENV_FILE="${APP_DIR}/.env"
if [[ ! -f "${ENV_FILE}" ]]; then
  echo "File .env tidak ditemukan: ${ENV_FILE}"
  exit 1
fi
set -a
# shellcheck disable=SC1090
source "${ENV_FILE}"
set +a
mkdir -p "${BACKUP_DIR}"
OUT="${BACKUP_DIR}/simada_$(date +%Y%m%d_%H%M%S).sql.gz"
mysqldump -h "${DB_HOST:-127.0.0.1}" -P "${DB_PORT:-3306}" -u "${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" | gzip > "${OUT}"
chmod 600 "${OUT}"
echo "Backup database dibuat: ${OUT}"
