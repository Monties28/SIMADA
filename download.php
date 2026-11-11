<?php
/* =========================================================
   DOWNLOAD DOKUMEN SIMADA
   Fungsi: Validasi hak akses lalu kirim file dokumen ke browser.
========================================================= */

declare(strict_types=1);

require_once __DIR__ . '/config/security.php';
appSecureSessionStart();
appSendSecurityHeaders();

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/helpers.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$db = appDb();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$role = (string) ($_SESSION['role'] ?? '');
$documentId = (int) ($_GET['id'] ?? 0);

if (!$db) {
    http_response_code(500);
    exit('Koneksi database tidak tersedia.');
}
appEnsureSimadaCoreSchema($db);

if ($documentId <= 0) {
    http_response_code(400);
    exit('Parameter dokumen tidak valid.');
}

if ($role === 'admin') {
    $stmt = $db->prepare('SELECT id, user_id, file_name, file_path, file_extension, status FROM documents WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $documentId);
    }
} else {
    $stmt = $db->prepare('SELECT id, user_id, file_name, file_path, file_extension, status FROM documents WHERE id = ? AND user_id = ? AND LOWER(TRIM(status)) IN (\'approved\', \'disetujui\') LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('ii', $documentId, $userId);
    }
}

if (!$stmt) {
    http_response_code(500);
    exit('Query gagal disiapkan.');
}

$stmt->execute();
$result = $stmt->get_result();
$document = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$document) {
    http_response_code(404);
    exit('Dokumen tidak ditemukan atau belum dapat diunduh.');
}

$relativePath = trim((string) ($document['file_path'] ?? ''));
$relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
if ($relativePath === '' || str_contains($relativePath, '..')) {
    http_response_code(400);
    exit('Path file tidak valid.');
}

$absolutePath = appResolveDocumentAbsolutePath($relativePath, false);
if (!$absolutePath || !is_file($absolutePath)) {
    http_response_code(404);
    exit('File tidak ditemukan.');
}

$downloadName = appDisplayFileName((string) ($document['file_name'] ?? basename($absolutePath)));
$downloadName = $downloadName !== '' ? $downloadName : basename($absolutePath);
$extension = strtolower((string) ($document['file_extension'] ?? pathinfo($downloadName, PATHINFO_EXTENSION)));
$mimeTypes = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];
$mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

appOutputDocumentBinary($db, $absolutePath, $document, $downloadName, $mimeType, 'attachment');
