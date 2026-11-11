SIMADA Debian 12 - Revisi Fix

Perbaikan revisi:
- Upload revisi sekarang otomatis update dokumen lama.
- Tidak gagal jika revision_count bernilai NULL.
- Status dokumen dikembalikan ke Pending setelah user upload revisi.
- Kategori, jenis, tanggal, dan folder lama tetap mengikuti dokumen revisi.

Install ulang singkat:
1. Copy folder project ini ke /var/www/html/simada
2. chown -R www-data:www-data /var/www/html/simada
3. chmod -R 755 /var/www/html/simada
4. Import simada_final.sql bila perlu.
5. Restart Apache: systemctl restart apache2
