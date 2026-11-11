# SIMADA di Ubuntu Server 24.04 LTS

Project ini memakai **satu codebase untuk Windows/XAMPP dan Ubuntu Server**. Kode akan mendeteksi sistem operasi untuk OCR dan LibreOffice. Yang perlu diganti saat pindah ke Ubuntu terutama `.env`, permission, Apache/PHP, dan dependency server.

## 1. Paket server

```bash
sudo apt update
sudo apt install -y apache2 mariadb-server \
  php libapache2-mod-php php-mysql php-cli php-curl php-xml php-mbstring php-zip php-gd \
  poppler-utils tesseract-ocr tesseract-ocr-ind tesseract-ocr-eng libreoffice-writer libreoffice-calc
```

Cek:

```bash
php -v
which pdftotext
which pdftoppm
which tesseract
which libreoffice
tesseract --list-langs
```

Target bahasa Tesseract minimal `ind` dan `eng`.

## 2. Source code

Contoh lokasi:

```bash
sudo mkdir -p /var/www/simada
sudo chown -R admin:www-data /var/www/simada
```

Copy isi project ke `/var/www/simada`, lalu:

```bash
sudo find /var/www/simada -type d -exec chmod 750 {} \;
sudo find /var/www/simada -type f -exec chmod 640 {} \;
```

## 3. Storage

```bash
sudo mkdir -p /srv/documents
sudo chown -R www-data:www-data /srv/documents
sudo chmod -R 750 /srv/documents
```

SIMADA akan membuat subfolder `Documents`, `Profile_Photos`, dan `Temp` di bawah path tersebut.

## 4. `.env`

```bash
cd /var/www/simada
cp .env.ubuntu.example .env
nano .env
```

Ganti database password, `APP_URL`, dan `APP_MASTER_KEY`. Jika migrasi dari server lama dan key tersebut sudah dipakai, **pertahankan APP_MASTER_KEY yang sama**.

## 5. Database

Jangan pakai root MariaDB untuk aplikasi production.

```sql
CREATE DATABASE simada CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'simada_user'@'localhost' IDENTIFIED BY 'PASSWORD_KUAT';
GRANT ALL PRIVILEGES ON simada.* TO 'simada_user'@'localhost';
FLUSH PRIVILEGES;
```

Import SQL sesuai file backup/project Anda.

## 6. Apache

Contoh `/etc/apache2/sites-available/simada.conf`:

```apache
<VirtualHost *:80>
    ServerName simada-server
    DocumentRoot /var/www/simada

    <Directory /var/www/simada>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/simada_error.log
    CustomLog ${APACHE_LOG_DIR}/simada_access.log combined
</VirtualHost>
```

Aktifkan:

```bash
sudo a2enmod rewrite
sudo a2ensite simada.conf
sudo a2dissite 000-default.conf
sudo apache2ctl configtest
sudo systemctl restart apache2
```

## 7. PHP upload/OCR

Sesuaikan `php.ini` Apache, misalnya:

```ini
upload_max_filesize = 20M
post_max_size = 24M
memory_limit = 256M
max_execution_time = 120
```

Cari file konfigurasi aktif:

```bash
php --ini
```

Setelah mengubah konfigurasi PHP:

```bash
sudo systemctl restart apache2
```

## 8. Cek instalasi

Buka:

`http://IP-SERVER/setup_check.php`

Target: database, storage, `pdftotext`, `pdftoppm`, Tesseract `ind+eng`, dan LibreOffice semuanya **OK**. Setelah deployment selesai, hapus atau batasi akses `setup_check.php`.

## 9. Cara kerja dokumen

- PDF digital → `pdftotext`.
- PDF scan → `pdftoppm` → Tesseract OCR.
- DOC/DOCX/XLS/XLSX → tidak menjalankan OCR; preview menggunakan LibreOffice headless.
- Jika OCR gagal, upload dokumen tetap disimpan dan teks dapat diisi manual.
