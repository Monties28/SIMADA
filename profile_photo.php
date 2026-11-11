<?php
/* =========================================================
   ENDPOINT FOTO PROFIL SIMADA
   Foto profil disimpan di storage eksternal SIMADA_PENYIMPANAN/Profile_Photos,
   lalu dibaca melalui endpoint ini agar tetap tidak berada langsung di public folder.
========================================================= */

declare(strict_types=1);
require_once __DIR__ . '/config/security.php';
appSecureSessionStart();
appSendSecurityHeaders();

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/helpers.php';

$db = appDb();
$currentUserId = (int) ($_SESSION['user_id'] ?? 0);
$currentRole = (string) ($_SESSION['role'] ?? '');
$requestedUserId = (int) ($_GET['id'] ?? 0);

if (!$db || $currentUserId <= 0 || $requestedUserId <= 0) {
    http_response_code(403);
    exit('Forbidden');
}

if ($requestedUserId !== $currentUserId && $currentRole !== 'admin') {
    http_response_code(403);
    exit('Forbidden');
}

$stmt = $db->prepare('SELECT profile_photo FROM users WHERE id = ? LIMIT 1');
if (!$stmt) {
    http_response_code(404);
    exit('Not found');
}

$stmt->bind_param('i', $requestedUserId);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$profilePhoto = trim((string) ($row['profile_photo'] ?? ''));
$absolutePath = $profilePhoto !== '' ? appResolveProfilePhotoAbsolutePath($profilePhoto, false) : null;

if (!$absolutePath || !is_file($absolutePath)) {
    http_response_code(404);
    exit('No photo');
}

$ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'jpg', 'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
    default => 'application/octet-stream',
};

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($absolutePath));
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');
readfile($absolutePath);
exit();
