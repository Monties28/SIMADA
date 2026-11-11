#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
  echo "Jalankan sebagai root: sudo bash deploy/scripts/install_debian12.sh"
  exit 1
fi

SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
APP_DIR="${APP_DIR:-/var/www/simada}"
STORAGE_DIR="${STORAGE_DIR:-/var/lib/simada/storage}"
DB_NAME="${DB_NAME:-simada}"
DB_USER="${DB_USER:-simada_user}"
SERVER_NAME="${SERVER_NAME:-_}"

if [[ -z "${DB_PASS:-}" ]]; then
  DB_PASS="$(openssl rand -base64 24 | tr -d '=+/ ' | cut -c1-20)"
fi
if [[ -z "${APP_MASTER_KEY:-}" ]]; then
  APP_MASTER_KEY="$(openssl rand -hex 32)"
fi

echo "== SIMADA Debian 12 Installer =="
echo "Source      : ${SOURCE_DIR}"
echo "App dir     : ${APP_DIR}"
echo "Storage dir : ${STORAGE_DIR}"
echo "DB name     : ${DB_NAME}"
echo "DB user     : ${DB_USER}"
echo
read -r -p "Lanjut install/update SIMADA? [y/N] " CONFIRM
if [[ ! "${CONFIRM}" =~ ^[Yy]$ ]]; then
  echo "Dibatalkan."
  exit 0
fi

export DEBIAN_FRONTEND=noninteractive
apt update
apt install -y apache2 mariadb-server unzip rsync openssl \
  php libapache2-mod-php php-cli php-mysql php-mbstring php-xml php-gd php-curl php-zip php-intl \
  poppler-utils

a2enmod rewrite headers >/dev/null
systemctl enable --now apache2 mariadb

mkdir -p "${APP_DIR}" "${STORAGE_DIR}" "${STORAGE_DIR}/Documents" "${STORAGE_DIR}/Profile_Photos" "${STORAGE_DIR}/Temp"

rsync -a --delete \
  --exclude='.git' \
  --exclude='.env' \
  --exclude='deploy' \
  --exclude='docs' \
  --exclude='README_DEBIAN12.md' \
  --exclude='setup_check.php' \
  --exclude='simada.sql' \
  --exclude='simada_final.sql' \
  --exclude='*.zip' \
  --exclude='storage_simada/Documents/*' \
  --exclude='storage_simada/Profile_Photos/*' \
  --exclude='storage_simada/Temp/*' \
  "${SOURCE_DIR}/" "${APP_DIR}/"

cat > "${APP_DIR}/.env" <<ENV
APP_ENV=production
APP_DEBUG=false
APP_URL=http://${SERVER_NAME}
APP_TIMEZONE=Asia/Makassar
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASS}
APP_MASTER_KEY=${APP_MASTER_KEY}
SIMADA_STORAGE_PATH=${STORAGE_DIR}
SIMADA_MAX_UPLOAD_MB=20
ENV
chmod 640 "${APP_DIR}/.env"

mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

if [[ -f "${SOURCE_DIR}/database/simada_infinityfree_clean.sql" ]]; then
  TABLE_COUNT="$(mysql -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}'" 2>/dev/null || echo 0)"
  if [[ "${TABLE_COUNT}" == "0" ]]; then
    echo "Import database awal..."
    mysql "${DB_NAME}" < "${SOURCE_DIR}/database/simada_infinityfree_clean.sql"
  else
    echo "Database sudah berisi tabel, import awal dilewati."
  fi
fi

PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
for SAPIDIR in "/etc/php/${PHP_VER}/apache2/conf.d" "/etc/php/${PHP_VER}/cli/conf.d"; do
  if [[ -d "${SAPIDIR}" ]]; then
    cat > "${SAPIDIR}/99-simada.ini" <<INI
file_uploads = On
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 256M
max_execution_time = 120
max_input_time = 120
expose_php = Off
session.cookie_httponly = 1
session.cookie_samesite = Lax
INI
  fi
done

cat > /etc/apache2/sites-available/simada.conf <<CONF
<VirtualHost *:80>
    ServerName ${SERVER_NAME}
    ServerAdmin admin@localhost
    DocumentRoot ${APP_DIR}

    <Directory ${APP_DIR}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    <Directory ${APP_DIR}/config>
        Require all denied
    </Directory>

    <Directory ${APP_DIR}/database>
        Require all denied
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/simada_error.log
    CustomLog \${APACHE_LOG_DIR}/simada_access.log combined
</VirtualHost>
CONF

a2dissite 000-default.conf >/dev/null 2>&1 || true
a2ensite simada.conf >/dev/null

chown -R root:www-data "${APP_DIR}"
find "${APP_DIR}" -type d -exec chmod 750 {} \;
find "${APP_DIR}" -type f -exec chmod 640 {} \;
chmod 750 "${APP_DIR}"
chown -R www-data:www-data "${STORAGE_DIR}"
find "${STORAGE_DIR}" -type d -exec chmod 770 {} \;
find "${STORAGE_DIR}" -type f -exec chmod 660 {} \;

apache2ctl configtest
systemctl restart apache2

echo
echo "Selesai."
echo "URL       : http://${SERVER_NAME}/"
echo "App dir   : ${APP_DIR}"
echo "Storage   : ${STORAGE_DIR}"
echo "Database  : ${DB_NAME}"
echo "DB user   : ${DB_USER}"
echo "DB pass   : ${DB_PASS}"
echo "Simpan password database di tempat aman."
