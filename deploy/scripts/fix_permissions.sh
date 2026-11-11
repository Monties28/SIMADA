#!/usr/bin/env bash
set -euo pipefail
APP_DIR="${APP_DIR:-/var/www/simada}"
STORAGE_DIR="${STORAGE_DIR:-/var/lib/simada/storage}"
if [[ "${EUID}" -ne 0 ]]; then
  echo "Jalankan: sudo APP_DIR=${APP_DIR} STORAGE_DIR=${STORAGE_DIR} bash deploy/scripts/fix_permissions.sh"
  exit 1
fi
chown -R root:www-data "${APP_DIR}"
find "${APP_DIR}" -type d -exec chmod 750 {} \;
find "${APP_DIR}" -type f -exec chmod 640 {} \;
[[ -f "${APP_DIR}/.env" ]] && chmod 640 "${APP_DIR}/.env"
mkdir -p "${STORAGE_DIR}"
chown -R www-data:www-data "${STORAGE_DIR}"
find "${STORAGE_DIR}" -type d -exec chmod 770 {} \;
find "${STORAGE_DIR}" -type f -exec chmod 660 {} \;
echo "Permission SIMADA selesai diperbaiki."
