<?php
/* =========================================================
   ROUTER ADMIN
   Fungsi: Menentukan halaman admin yang dibuka, menyiapkan menu, lalu memuat konten melalui layout utama.
========================================================= */

declare(strict_types=1);

require_once __DIR__ . '/../config/security.php';
appSecureSessionStart();
appSendSecurityHeaders();

require_once __DIR__ . '/../config/koneksi.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../config/layout.php';

/* =========================================================
   BAGIAN VALIDASI SESSION
   Bagian ini memastikan hanya pengguna yang sudah login dan sesuai role yang dapat masuk.
========================================================= */
if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

/* =========================================================
   BAGIAN ROUTING HALAMAN
   Bagian ini memetakan parameter halaman ke file konten yang akan dimuat.
========================================================= */
$pages = [
    'dashboard' => [
        'label' => 'Dashboard',
        'icon' => 'fa-house',
    ],
    'pustaka' => [
        'label' => 'Pustaka',
        'icon' => 'fa-folder-open',
    ],
    'manajemen_user' => [
        'label' => 'Manajemen User',
        'icon' => 'fa-users',
    ],
    'manajemen_dokumen' => [
        'label' => 'Manajemen Dokumen',
        'icon' => 'fa-circle-check',
    ],
    'notifikasi' => [
        'label' => 'Notifikasi',
        'icon' => 'fa-bell',
    ],
    'profile' => [
        'label' => 'Pengaturan Profil',
        'icon' => 'fa-user-gear',
    ],
];

$page = (string) ($_GET['page'] ?? 'dashboard');

if (!array_key_exists($page, $pages)) {
    $page = 'dashboard';
}

/* =========================================================
   BAGIAN AKSI NOTIFIKASI
   Diproses sebelum layout dirender agar badge bisa langsung
   hilang/update tanpa harus refresh manual.
========================================================= */
if ($page === 'notifikasi') {
    $dbNotif = appDb();
    $notifUserId = (int) ($_SESSION['user_id'] ?? 0);
    $notifRole = (string) ($_SESSION['role'] ?? '');

    if ($dbNotif instanceof mysqli && $notifUserId > 0) {
        appEnsureSimadaCoreSchema($dbNotif);

        if ($_SERVER['REQUEST_METHOD'] === 'GET' && (($_GET['action'] ?? '') === 'open')) {
            $notifId = (int) ($_GET['id'] ?? 0);
            if ($notifId > 0) {
                appMarkNotificationRead($dbNotif, $notifId, $notifUserId, $notifRole);
                $redirectTo = appNotificationLinkForUser($dbNotif, $notifId, $notifUserId, $notifRole);
                header('Location: ' . $redirectTo);
                exit();
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            appRequireCsrfToken();

            $action = (string) ($_POST['action'] ?? '');
            $notice = '';

            if ($action === 'mark_read') {
                appMarkAllNotificationsRead($dbNotif, $notifUserId, $notifRole);
                $notice = 'read_all';
            } elseif ($action === 'delete_all') {
                appDeleteAllNotifications($dbNotif, $notifUserId, $notifRole);
                $notice = 'delete_all';
            } elseif ($action === 'mark_selected') {
                $ids = appParseNotificationIds($_POST['selected_ids'] ?? '');
                appBulkNotificationAction($dbNotif, $ids, $notifUserId, $notifRole, 'read');
                $notice = 'read_selected';
            } elseif ($action === 'delete_selected') {
                $ids = appParseNotificationIds($_POST['selected_ids'] ?? '');
                appBulkNotificationAction($dbNotif, $ids, $notifUserId, $notifRole, 'delete');
                $notice = 'delete_selected';
            } elseif ($action === 'delete_one') {
                $notifId = (int) ($_POST['id'] ?? 0);
                if ($notifId > 0) {
                    appDeleteNotification($dbNotif, $notifId, $notifUserId, $notifRole);
                }
                $notice = 'delete_one';
            }

            $redirect = 'main.php?page=notifikasi';
            if ($notice !== '') {
                $redirect .= '&notice=' . rawurlencode($notice);
            }

            header('Location: ' . $redirect);
            exit();
        }

        // Saat halaman notifikasi dibuka, semua notifikasi yang terlihat langsung dianggap sudah dibaca.
        // Ini membuat badge di header/sidebar hilang pada render pertama.
        appMarkAllNotificationsRead($dbNotif, $notifUserId, $notifRole);
    }
}


renderAppShell([
    'title' => 'SIMADA - Admin Panel',
    'panel_title' => 'Panel Administrator SIMADA',
    'page' => $page,
    'pages' => $pages,
    'page_dir' => __DIR__ . '/pages',
    'username' => (string) ($_SESSION['username'] ?? ''),
    'jabatan' => (string) ($_SESSION['jabatan'] ?? 'Administrator'),
    'user_icon' => 'fa-user-shield',
    'logo_path' => '../assets/picture/logo_uptd_clear.png',
    'secondary_logo_path' => '../assets/picture/logo_mitra_clear.png',
    'unit_name' => 'UPTD SENTRAL PEMBENIHAN AIR PAYAU DAN AIR LAUT MUARA BADAK-SEBULU (SPAPAL)',
    'favicon_path' => '../assets/picture/favicon_unit.png',
    'logout_path' => '../logout.php',
]);
