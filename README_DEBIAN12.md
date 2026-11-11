# SIMADA - Paket Deploy Debian 12 / Proxmox

Paket ini disiapkan untuk menjalankan SIMADA di VM/LXC Debian 12 pada Proxmox memakai Apache, PHP 8.2, dan MariaDB.

## Cara cepat install

Upload ZIP ini ke server Debian 12, lalu jalankan:

```bash
sudo apt update
sudo apt install -y unzip
unzip SIMADA_DEBIAN12_PROXMOX_READY.zip
cd SIMADA
sudo SERVER_NAME=IP-ATAU-DOMAIN-ANDA bash deploy/scripts/install_debian12.sh
```

Contoh:

```bash
sudo SERVER_NAME=192.168.1.10 bash deploy/scripts/install_debian12.sh
```

Default lokasi aplikasi:

```text
/var/www/simada
```

Default lokasi storage dokumen:

```text
/var/lib/simada/storage
```

Storage disimpan di luar document root agar file instansi tidak bisa diakses langsung lewat URL.

## Kebutuhan server

Script akan memasang:

- Apache 2
- MariaDB Server
- PHP dan ekstensi umum: mysql, mbstring, xml, gd, curl, zip, intl
- poppler-utils untuk fitur ekstraksi teks PDF / OCR ringan via `pdftotext`

## Konfigurasi database

Installer otomatis membuat database dan user:

```text
Database: simada
User    : simada_user
Password: dibuat otomatis jika DB_PASS tidak diberikan
```

Jika ingin menentukan sendiri:

```bash
sudo DB_NAME=simada DB_USER=simada_user DB_PASS='PasswordKuatAnda' SERVER_NAME=192.168.1.10 bash deploy/scripts/install_debian12.sh
```

## File .env production

Installer membuat:

```text
/var/www/simada/.env
```

Contoh tersedia di:

```text
.env.example
```

Catatan penting: `APP_MASTER_KEY` jangan diganti setelah dokumen terenkripsi, karena dokumen lama bisa gagal dibuka.

## Perbaiki permission

Jika upload gagal karena permission, jalankan:

```bash
sudo bash /path/ke/SIMADA/deploy/scripts/fix_permissions.sh
```

Atau jika lokasi berbeda:

```bash
sudo APP_DIR=/var/www/simada STORAGE_DIR=/var/lib/simada/storage bash deploy/scripts/fix_permissions.sh
```

## Backup

Backup database:

```bash
sudo bash deploy/scripts/backup_database.sh
```

Backup storage dokumen:

```bash
sudo bash deploy/scripts/backup_storage.sh
```

## HTTPS

Untuk instansi, aktifkan HTTPS setelah domain mengarah ke server:

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d domain-anda.go.id
```

## Keamanan yang sudah disiapkan

- `.git`, `setup_check.php`, dan SQL root tidak disertakan di public package.
- `.env`, file SQL, folder config, database, dan storage dilindungi.
- Apache `Options -Indexes` aktif.
- Security headers dasar aktif via `.htaccess`.
- Storage diarahkan ke `/var/lib/simada/storage`.
- Permission Linux dipisah antara source code dan storage upload.

## Troubleshooting

Cek status Apache:

```bash
sudo systemctl status apache2
```

Cek log error:

```bash
sudo tail -f /var/log/apache2/simada_error.log
```

Cek PHP module:

```bash
php -m
```

Cek koneksi database:

```bash
mysql -u simada_user -p simada
```
