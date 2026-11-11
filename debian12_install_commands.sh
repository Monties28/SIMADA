#!/usr/bin/env bash
set -euo pipefail

PROJECT_DIR="/var/www/html/simada"
STORAGE_DIR="/var/www/simada-storage"
PHP_VERSION="${PHP_VERSION:-8.2}"

echo "[1/6] Install paket dasar"
sudo apt update
sudo apt install -y apache2 mariadb-server unzip php php-cli php-mysql php-mbstring php-xml php-zip php-curl php-gd poppler-utils

echo "[2/6] Siapkan folder storage"
sudo mkdir -p "$STORAGE_DIR/Documents" "$STORAGE_DIR/Profile_Photos" "$STORAGE_DIR/Temp"
sudo chown -R www-data:www-data "$STORAGE_DIR"
sudo find "$STORAGE_DIR" -type d -exec chmod 775 {} \;
sudo find "$STORAGE_DIR" -type f -exec chmod 664 {} \;

echo "[3/6] Permission project"
if [ -d "$PROJECT_DIR" ]; then
  sudo chown -R www-data:www-data "$PROJECT_DIR"
  sudo find "$PROJECT_DIR" -type d -exec chmod 755 {} \;
  sudo find "$PROJECT_DIR" -type f -exec chmod 644 {} \;
fi

echo "[4/6] Setting PHP upload untuk Apache jika php.ini tersedia"
PHP_INI="/etc/php/$PHP_VERSION/apache2/php.ini"
if [ -f "$PHP_INI" ]; then
  sudo sed -i 's/^\s*file_uploads\s*=.*/file_uploads = On/i' "$PHP_INI"
  sudo sed -i 's/^\s*upload_max_filesize\s*=.*/upload_max_filesize = 20M/i' "$PHP_INI"
  sudo sed -i 's/^\s*post_max_size\s*=.*/post_max_size = 25M/i' "$PHP_INI"
  sudo sed -i 's/^\s*memory_limit\s*=.*/memory_limit = 256M/i' "$PHP_INI"
  sudo sed -i 's/^\s*max_execution_time\s*=.*/max_execution_time = 120/i' "$PHP_INI"
  sudo sed -i 's/^\s*max_input_time\s*=.*/max_input_time = 120/i' "$PHP_INI"
else
  echo "php.ini tidak ditemukan di $PHP_INI. Cek versi dengan: ls /etc/php"
fi

echo "[5/6] Restart service"
sudo systemctl restart apache2
sudo systemctl restart mariadb || true

echo "[6/6] Cek dependency"
php -m | grep -E 'mysqli|openssl|fileinfo|mbstring|pdo_mysql' || true
command -v pdftotext || true

echo "Selesai. Buka: http://IP-SERVER/simada/simada_debian_check.php"
