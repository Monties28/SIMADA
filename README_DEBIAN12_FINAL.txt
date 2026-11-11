SIMADA Debian 12 - Final Checked

Isi paket ini sudah dicek untuk Debian 12:
- PHP syntax check OK.
- Default database: simada / simada_user / 12345678.
- File .env sudah disertakan, jadi tidak perlu edit koneksi manual.
- Upload revisi otomatis update dokumen lama, mengubah status kembali ke Pending, dan menaikkan revision_count.
- Storage default: /var/www/simada-storage.

Langkah cepat:
1. Copy isi folder ini ke /var/www/html/simada
2. Jalankan:
   chown -R www-data:www-data /var/www/html/simada
   chmod -R 755 /var/www/html/simada
   mkdir -p /var/www/simada-storage/Documents /var/www/simada-storage/Profile_Photos /var/www/simada-storage/Temp
   chown -R www-data:www-data /var/www/simada-storage
   chmod -R 775 /var/www/simada-storage
3. Buat DB:
   mysql
   DROP DATABASE IF EXISTS simada;
   CREATE DATABASE simada;
   DROP USER IF EXISTS 'simada_user'@'localhost';
   CREATE USER 'simada_user'@'localhost' IDENTIFIED BY '12345678';
   GRANT ALL PRIVILEGES ON simada.* TO 'simada_user'@'localhost';
   FLUSH PRIVILEGES;
   EXIT;
4. Import:
   mysql -u simada_user -p simada < /var/www/html/simada/simada_final.sql
5. Restart:
   systemctl restart apache2
6. Cek:
   http://IP-DEBIAN/simada/simada_debian_check.php
