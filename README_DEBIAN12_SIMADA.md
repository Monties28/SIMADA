# SIMADA - Catatan Deploy Debian 12

## Diagnosis utama
Jika upload berjalan di Windows tetapi gagal di Debian 12, penyebab paling umum adalah:

1. Folder storage tidak writable oleh user Apache/PHP (`www-data`).
2. Ekstensi PHP belum lengkap: `mysqli`, `openssl`, `fileinfo`, `mbstring`, `pdo_mysql`.
3. Limit PHP lebih kecil dari file PDF: `upload_max_filesize`, `post_max_size`, `memory_limit`.
4. OCR otomatis membutuhkan `pdftotext` dari paket `poppler-utils`.
5. `APP_MASTER_KEY` belum diset permanen; enkripsi/dekripsi wajib memakai key yang sama.
6. Revisi gagal bila file lama tidak bisa dipindahkan dari folder status lama ke folder status baru karena permission storage.

## Instal paket Debian 12

```bash
sudo apt update
sudo apt install -y apache2 mariadb-server unzip php php-cli php-mysql php-mbstring php-xml php-zip php-curl php-gd poppler-utils
sudo systemctl restart apache2 mariadb
```

Catatan: pada Debian 12, `openssl` dan `fileinfo` biasanya sudah built-in di paket PHP. Validasi dengan `php -m | grep -E "openssl|fileinfo"`.

## Struktur rekomendasi

```bash
sudo mkdir -p /var/www/html/simada
sudo mkdir -p /var/www/simada-storage/{Documents,Profile_Photos,Temp}
sudo chown -R www-data:www-data /var/www/simada-storage
sudo find /var/www/simada-storage -type d -exec chmod 775 {} \;
sudo find /var/www/simada-storage -type f -exec chmod 664 {} \;
```

Upload isi project ke:

```text
/var/www/html/simada
```

Buat file `.env` dari `.env.example`, lalu sesuaikan DB.

## Setting PHP upload

Cari versi PHP:

```bash
php -v
ls /etc/php
```

Misal PHP 8.2 Apache:

```bash
sudo nano /etc/php/8.2/apache2/php.ini
```

Set minimal:

```ini
file_uploads = On
upload_max_filesize = 20M
post_max_size = 25M
memory_limit = 256M
max_execution_time = 120
max_input_time = 120
```

Restart:

```bash
sudo systemctl restart apache2
```

## Cek siap jalan

Buka:

```text
http://IP-SERVER/simada/simada_debian_check.php
```

Semua bagian penting harus OK, khususnya storage writable, openssl, mysqli, fileinfo, upload tmp, database, dan pdftotext.

Setelah selesai, hapus:

```bash
sudo rm /var/www/html/simada/simada_debian_check.php
sudo rm /var/www/html/simada/setup_check.php
```

## Catatan OCR
Fitur OCR di project ini memakai `pdftotext`, artinya efektif untuk PDF yang memiliki teks digital. Untuk PDF hasil scan/foto, perlu OCR tambahan seperti `ocrmypdf` + `tesseract-ocr-ind`, tetapi belum diaktifkan sebagai pipeline utama di kode ini.
