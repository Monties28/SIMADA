SIMADA Debian 12 Clean Checked

Perubahan:
1. Menghapus config/storage_helper.php karena seluruh fungsinya sudah ada di config/helpers.php dan file ini tidak direquire oleh aplikasi. Jika suatu saat ikut ter-load, file lama ini berisiko fatal error karena fungsi double tanpa function_exists.
2. Menghapus simada.sql dan folder database/ dari paket clean agar instalasi Debian 12 tidak bingung memakai SQL lama/upgrade. Untuk fresh install gunakan hanya simada_final.sql.
3. Memperbaiki normalisasi status dokumen di config/helpers.php agar format 'Perlu Revisi', 'perlu_revisi', 'needs_revision', dll tetap terbaca sebagai revision.
4. Memperbaiki user/pages/upload.php:
   - revision_count NULL dianggap 0.
   - batas revisi memakai appMaxDocumentRevisions().
   - dokumen revisi valid otomatis bisa diganti file dan status kembali Pending.
5. PHP lint semua file: OK.

Instal Debian 12:
- Copy project ke /var/www/html/simada
- Import database dengan:
  mysql -u simada_user -p simada < simada_final.sql
- Default DB:
  DB_DATABASE=simada
  DB_USERNAME=simada_user
  DB_PASSWORD=12345678
