<?php
/* =========================================================
   LOGOUT
   Fungsi: Menghapus session login dan mengembalikan pengguna ke halaman login.
========================================================= */

declare(strict_types=1);
require_once __DIR__ . '/config/security.php';
appSecureSessionStart();
appSendSecurityHeaders(true);
/* =========================================================
   BAGIAN HAPUS SESSION
   Bagian ini mengosongkan session dan mengarahkan pengguna ke halaman login.
========================================================= */

/* Hapus semua session */
session_unset();
session_destroy();

/* Redirect ke halaman login */
header("Location: index.php");
exit();
