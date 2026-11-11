# SIMADA - Windows / XAMPP

Paket ini khusus untuk pengujian SIMADA di Windows menggunakan XAMPP.
Konfigurasi aktif tidak memakai Debian, Ubuntu, Proxmox, /var/www, /var/lib, apt, sudo, atau command Linux.

## Jalankan
1. Extract folder `simada` ke `C:\xampp\htdocs\simada`.
2. Start `Apache` dan `MySQL` dari XAMPP Control Panel.
3. Buka `http://localhost/phpmyadmin`.
4. Buat database `simada`.
5. Import `database\install_windows_xampp.sql`.
6. Cek `http://localhost/simada/setup_check.php`.
7. Buka `http://localhost/simada/`.

## Database lokal
- Host: `127.0.0.1`
- Port: `3306`
- Database: `simada`
- User: `root`
- Password: kosong (default XAMPP lokal)

## Storage Windows
Storage default berada di:
`C:\xampp\htdocs\simada\storage_simada`

## Edit VS Code
Buka folder:
`C:\xampp\htdocs\simada`

Simpan perubahan lalu refresh browser.

## Preview Word/Excel
SIMADA dapat mendeteksi LibreOffice for Windows dari:
- `C:\Program Files\LibreOffice\program\soffice.exe`
- `C:\Program Files (x86)\LibreOffice\program\soffice.exe`

Jika fitur preview Word/Excel diperlukan, install LibreOffice for Windows lalu restart Apache XAMPP.

## Catatan
Tampilan, menu, database logic, login, upload, dan fungsi utama aplikasi tidak diubah dalam penyesuaian platform ini.
