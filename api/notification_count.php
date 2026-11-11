<?php
/* =========================================================
   AJAX NOTIFICATION COUNT SIMADA
   Fungsi: Mengirim jumlah notifikasi belum dibaca dan ringkasan notifikasi terbaru
   untuk badge, panel header, dan toast informasi pengguna.
========================================================= */

declare(strict_types=1);

require_once __DIR__ . '/../config/security.php';
appSecureSessionStart();
appSendSecurityHeaders(true);

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    appJsonResponse([
        'ok' => false,
        'message' => 'Method tidak diizinkan.',
    ], 405);
}

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    appJsonResponse([
        'ok' => false,
        'message' => 'Session tidak aktif.',
    ], 401);
}

$db = appDb();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$role = (string) ($_SESSION['role'] ?? '');

if (!$db || $userId <= 0 || !in_array($role, ['admin', 'user'], true)) {
    appJsonResponse([
        'ok' => false,
        'message' => 'Akses tidak valid.',
    ], 403);
}

appEnsureSimadaCoreSchema($db);

// Bersihkan/hilangkan notifikasi generik lama dari popup versi sebelumnya.
// Ini mencegah kartu "Notifikasi Baru" muncul lagi walaupun data lama masih ada di database.
if (function_exists('appClearLegacyPopupNotificationNoise')) {
    appClearLegacyPopupNotificationNoise($db, $userId, $role);
}

$unreadCount = appUnreadNotificationCount($db, $userId, $role);

$latestNotifications = [];
$noiseSql = appNotificationNotPopupNoiseSql();
$sqlLatest = "SELECT
        n.id,
        n.title,
        n.message,
        n.created_at
    FROM notifications n
    LEFT JOIN notification_user_states ns
        ON ns.notification_id = n.id
       AND ns.user_id = ?
    WHERE (n.user_id = ? OR (n.user_id IS NULL AND n.target_role = ?))
      AND COALESCE(ns.is_deleted, 0) = 0
      AND {$noiseSql}
      AND COALESCE(ns.is_read, n.is_read, 0) = 0
    ORDER BY n.created_at DESC, n.id DESC
    LIMIT 5";

$stmtLatest = $db->prepare($sqlLatest);
if ($stmtLatest) {
    $stmtLatest->bind_param('iis', $userId, $userId, $role);
    $stmtLatest->execute();
    $resultLatest = $stmtLatest->get_result();
    while ($row = $resultLatest ? $resultLatest->fetch_assoc() : null) {
        if (!$row) {
            break;
        }
        $notificationId = (int) ($row['id'] ?? 0);
        if ($notificationId <= 0) {
            continue;
        }
        $latestNotifications[] = [
            'id' => $notificationId,
            'title' => trim((string) ($row['title'] ?? 'Notifikasi')),
            'message' => trim((string) ($row['message'] ?? '')),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'open_url' => 'main.php?page=notifikasi&action=open&id=' . $notificationId,
        ];
    }
    $stmtLatest->close();
}

appJsonResponse([
    'ok' => true,
    'unread_count' => $unreadCount,
    'latest' => $latestNotifications,
    'server_time' => date('c'),
]);
