#!/usr/bin/env bash
set -euo pipefail

sudo apt update
sudo apt install -y \
  apache2 mariadb-server \
  php libapache2-mod-php php-mysql php-cli php-curl php-xml php-mbstring php-zip php-gd \
  poppler-utils tesseract-ocr tesseract-ocr-ind tesseract-ocr-eng libreoffice-writer libreoffice-calc

sudo mkdir -p /srv/documents
sudo chown -R www-data:www-data /srv/documents
sudo chmod -R 750 /srv/documents

echo "Dependency SIMADA selesai."
echo "Cek dengan: which pdftotext; which pdftoppm; which tesseract; which libreoffice; tesseract --list-langs"
echo "Selanjutnya salin .env.ubuntu.example menjadi .env dan isi database/APP_URL/APP_MASTER_KEY."
