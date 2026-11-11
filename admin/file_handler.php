<?php
/* =========================================================
   PREVIEW FILE ADMIN SIMADA
   Fungsi: Kompatibilitas link lama admin. Preview sekarang
   diarahkan ke /preview.php agar tidak berubah menjadi download.
========================================================= */

declare(strict_types=1);

session_start();

if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Akses ditolak.');
}

$action = (string) ($_GET['action'] ?? '');
$documentId = (int) ($_GET['id'] ?? 0);

if ($action !== 'view' || $documentId <= 0) {
    http_response_code(400);
    exit('Parameter tidak valid.');
}

header('Location: ../preview.php?id=' . $documentId);
exit;
