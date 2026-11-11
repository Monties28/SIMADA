<?php
/* =========================================================
   LAYOUT APLIKASI SIMADA
   Kerangka UI responsif, sidebar, header, profil, notifikasi,
   dan area konten dengan nuansa perikanan modern.
========================================================= */

declare(strict_types=1);

require_once __DIR__ . '/security.php';
require_once __DIR__ . '/helpers.php';

if (!function_exists('renderAppShell')) {
    function renderAppShell(array $config): void
    {
        $db = appDb();
        if ($db instanceof mysqli) {
            appEnsureSimadaCoreSchema($db);
        }

        $page = (string) ($config['page'] ?? 'dashboard');
        $pages = (array) ($config['pages'] ?? []);
        $pageConfig = $pages[$page] ?? reset($pages);

        $pageTitle = (string) ($pageConfig['label'] ?? 'Dashboard');
        $panelTitle = (string) ($config['panel_title'] ?? 'Panel');
        $username = (string) ($config['username'] ?? '');
        $displayName = (string) ($_SESSION['name'] ?? $username);
        $jabatan = (string) ($config['jabatan'] ?? '-');
        $pageDir = rtrim((string) ($config['page_dir'] ?? ''), DIRECTORY_SEPARATOR);
        $logoPath = (string) ($config['logo_path'] ?? '../assets/picture/logo_uptd_clear.png');
        $secondaryLogoPath = (string) ($config['secondary_logo_path'] ?? '../assets/picture/logo_mitra_clear.png');
        $unitName = (string) ($config['unit_name'] ?? 'UPTD SENTRAL PEMBENIHAN AIR PAYAU DAN AIR LAUT MUARA BADAK-SEBULU (SPAPAL)');
        $faviconPath = (string) ($config['favicon_path'] ?? '../assets/picture/favicon_unit.png');
        $logoutPath = (string) ($config['logout_path'] ?? '../logout.php');
        $title = (string) ($config['title'] ?? 'SIMADA');
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $role = (string) ($_SESSION['role'] ?? '');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            appRequireCsrfToken();
        }

        if ($db instanceof mysqli && $userId > 0 && function_exists('appClearLegacyPopupNotificationNoise')) {
            appClearLegacyPopupNotificationNoise($db, $userId, $role);
        }
        $unreadCount = ($db instanceof mysqli && $userId > 0) ? appUnreadNotificationCount($db, $userId, $role) : 0;
        $csrfToken = appCsrfToken();
        $notificationEndpoint = (string) ($config['notification_endpoint'] ?? '../api/notification_count.php');

        $profilePhoto = '';
        if ($db instanceof mysqli && $userId > 0) {
            $stmtPhoto = $db->prepare('SELECT profile_photo FROM users WHERE id = ? LIMIT 1');
            if ($stmtPhoto) {
                $stmtPhoto->bind_param('i', $userId);
                $stmtPhoto->execute();
                $rowPhoto = $stmtPhoto->get_result()->fetch_assoc();
                $profilePhoto = trim((string) ($rowPhoto['profile_photo'] ?? ''));
                $stmtPhoto->close();
            }
        }

        $profilePhotoUrl = ($userId > 0 && $profilePhoto !== '') ? appProfilePhotoUrl($userId, $profilePhoto) : '';
        $initials = appUserInitials($displayName !== '' ? $displayName : $username);
        ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0F9D94">
    <title><?= e($title) ?></title>
    <link rel="icon" type="image/png" href="<?= e($faviconPath) ?>">
    <script>
        (function () {
            const saved = localStorage.getItem('simada-theme');
            const dark = saved ? saved === 'dark' : false;
            document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#00897B',
                        primaryDark: '#00695C',
                        primarySoft: '#E0F7F4',
                        reef: '#00897B',
                        sea: '#00BCD4',
                        coral: '#FF5252',
                        sand: '#FFEBCB',
                        foam: '#FFF8EF',
                        ink: '#16333D'
                    },
                    boxShadow: {
                        soft: '0 18px 45px rgba(22, 51, 61, .08)',
                        nav: '0 24px 65px rgba(0, 137, 123, .20)'
                    }
                }
            }
        };
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            color-scheme: light;
            --app-ink: #16333d;
            --app-muted: #6b8790;
            --app-bg: radial-gradient(circle at top left, rgba(0,188,212,.12), transparent 24%), radial-gradient(circle at bottom right, rgba(255,82,82,.10), transparent 20%), linear-gradient(180deg, #f8fffe 0%, #eefaf7 60%, #fffdf7 100%);
            --sidebar-bg: radial-gradient(circle at 14% 10%, rgba(255,255,255,.18), transparent 22%), linear-gradient(180deg, #00897B 0%, #00796B 38%, #00695C 100%);
            --header-bg: rgba(255,255,255,.82);
            --surface: rgba(255,255,255,.92);
            --surface-strong: rgba(255,255,255,.95);
            --line: #e2efe9;
            --line-strong: #cfe2dd;
            --chip-bg: rgba(255,255,255,.82);
        }
        html[data-theme="dark"] {
            color-scheme: dark;
            --app-ink: #eafffb;
            --app-muted: #a7c8c2;
            --app-bg: radial-gradient(circle at top left, rgba(0,188,212,.18), transparent 24%), radial-gradient(circle at bottom right, rgba(255,82,82,.10), transparent 20%), linear-gradient(180deg, #042d38 0%, #063f46 58%, #01252f 100%);
            --sidebar-bg: radial-gradient(circle at 14% 10%, rgba(255,255,255,.12), transparent 22%), linear-gradient(180deg, #025c62 0%, #014c52 45%, #012f39 100%);
            --header-bg: rgba(3, 40, 48, .86);
            --surface: rgba(5, 66, 70, .82);
            --surface-strong: rgba(3, 47, 55, .92);
            --line: rgba(138,243,235,.14);
            --line-strong: rgba(138,243,235,.22);
            --chip-bg: rgba(3, 47, 55, .82);
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            -webkit-font-smoothing: antialiased;
            background: var(--app-bg);
            color: var(--app-ink);
            transition: background .25s ease, color .25s ease;
        }
        .sidebar { background: var(--sidebar-bg); }
        .sidebar-scroll::-webkit-scrollbar { width: 5px; }
        .sidebar-scroll::-webkit-scrollbar-thumb { background: rgba(255,255,255,.22); border-radius: 999px; }
        .sidebar-logo-shell {
            width: 132px;
            height: 132px;
            margin: 0 auto;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(255,255,255,.96) 0%, rgba(255,248,239,.88) 72%);
            border: 2px solid rgba(255,255,255,.82);
            box-shadow: 0 24px 56px rgba(0,0,0,.18), inset 0 0 0 8px rgba(0,188,212,.08);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sidebar-logo-shell img {
            width: 116px;
            height: 116px;
            object-fit: contain;
            filter: drop-shadow(0 10px 14px rgba(0,0,0,.16));
        }
        .sidebar-unit {
            margin: .7rem auto 0;
            max-width: 15rem;
            color: rgba(255,255,255,.78);
            font-size: .62rem;
            line-height: 1.5;
            font-weight: 700;
            letter-spacing: .055em;
            text-transform: uppercase;
        }
        .sidebar-subtitle {
            color: rgba(255,255,255,.78);
            font-size: .72rem;
            font-weight: 600;
        }
        .app-header {
            background: var(--chip-bg);
            border-color: rgba(15, 118, 110, .12);
            backdrop-filter: blur(16px);
        }
        .partner-chip {
            display: inline-flex;
            align-items: center;
            gap: .7rem;
            padding: .5rem .85rem;
            border-radius: 18px;
            background: var(--chip-bg);
            border: 1px solid var(--line);
            box-shadow: 0 14px 30px rgba(22, 51, 61, .06);
        }
        .partner-chip img {
            width: 30px;
            height: 38px;
            object-fit: contain;
        }
        .content-card,
        .bg-white,
        .bg-white\/95,
        .bg-white\/90,
        .bg-white\/80,
        .bg-white\/70 {
            background-color: var(--surface) !important;
        }
        .shadow-sm, .shadow-soft { box-shadow: 0 18px 45px rgba(22, 51, 61, .08) !important; }
        .border-slate-100, .border-slate-200 { border-color: var(--line) !important; }
        .border-slate-300 { border-color: var(--line-strong) !important; }
        .text-slate-950, .text-slate-900, .text-slate-800 { color: var(--app-ink) !important; }
        .text-slate-700 { color: var(--app-ink) !important; opacity: .88; }
        .text-slate-600 { color: var(--app-muted) !important; }
        .text-slate-500, .text-slate-400 { color: var(--app-muted) !important; }
        input, select, textarea {
            background-color: var(--surface-strong) !important;
            border-color: var(--line-strong) !important;
            color: var(--app-ink) !important;
        }
        input::placeholder, textarea::placeholder { color: #8aa0a7 !important; }

        /* Warna utilitas utama agar tombol/font tetap jelas di mode siang dan malam. */
        .bg-primary { background-color: #00897B !important; color: #ffffff !important; }
        .hover\:bg-primaryDark:hover { background-color: #00695C !important; color: #ffffff !important; }
        .text-primary { color: #00897B !important; }
        .border-primary { border-color: #00897B !important; }
        .focus\:border-primary:focus { border-color: #00897B !important; }
        .accent-primary { accent-color: #00897B !important; }
        .bg-primarySoft { background-color: #E0F7F4 !important; }
        .border-primary\/20 { border-color: rgba(0, 137, 123, .20) !important; }
        .border-primary\/40 { border-color: rgba(0, 137, 123, .40) !important; }
        html[data-theme="dark"] .bg-primary { background-color: #00A99D !important; color: #ffffff !important; }
        html[data-theme="dark"] .hover\:bg-primaryDark:hover { background-color: #00C2B8 !important; color: #01252f !important; }
        html[data-theme="dark"] .text-primary { color: #8af3eb !important; }
        html[data-theme="dark"] .border-primary { border-color: #8af3eb !important; }
        html[data-theme="dark"] .focus\:border-primary:focus { border-color: #8af3eb !important; }
        html[data-theme="dark"] .accent-primary { accent-color: #8af3eb !important; }
        html[data-theme="dark"] .bg-primarySoft { background-color: rgba(0, 188, 212, .14) !important; color: #eafffb !important; }
        html[data-theme="dark"] .bg-slate-50,
        html[data-theme="dark"] .bg-slate-100,
        html[data-theme="dark"] .bg-slate-200 { background-color: rgba(3, 47, 55, .88) !important; }
        html[data-theme="dark"] .hover\:bg-slate-50:hover,
        html[data-theme="dark"] .hover\:bg-slate-100:hover,
        html[data-theme="dark"] .hover\:bg-slate-200:hover { background-color: rgba(138, 243, 235, .12) !important; }
        html[data-theme="dark"] .text-blue-700,
        html[data-theme="dark"] .text-blue-600 { color: #93c5fd !important; }
        html[data-theme="dark"] .text-green-700,
        html[data-theme="dark"] .text-green-600,
        html[data-theme="dark"] .text-emerald-700,
        html[data-theme="dark"] .text-emerald-600 { color: #86efac !important; }
        html[data-theme="dark"] .text-red-700,
        html[data-theme="dark"] .text-red-600,
        html[data-theme="dark"] .text-red-500 { color: #fca5a5 !important; }
        html[data-theme="dark"] .text-yellow-700,
        html[data-theme="dark"] .text-yellow-600,
        html[data-theme="dark"] .text-yellow-500 { color: #fde68a !important; }
        html[data-theme="dark"] .text-orange-700,
        html[data-theme="dark"] .text-orange-600 { color: #fdba74 !important; }
        html[data-theme="dark"] .bg-blue-50,
        html[data-theme="dark"] .bg-green-50,
        html[data-theme="dark"] .bg-emerald-50,
        html[data-theme="dark"] .bg-red-50,
        html[data-theme="dark"] .bg-yellow-50,
        html[data-theme="dark"] .bg-orange-50 { background-color: rgba(255,255,255,.08) !important; }
        html[data-theme="dark"] .border-blue-100,
        html[data-theme="dark"] .border-blue-200,
        html[data-theme="dark"] .border-green-100,
        html[data-theme="dark"] .border-green-200,
        html[data-theme="dark"] .border-red-100,
        html[data-theme="dark"] .border-red-200,
        html[data-theme="dark"] .border-yellow-100,
        html[data-theme="dark"] .border-yellow-200,
        html[data-theme="dark"] .border-orange-100,
        html[data-theme="dark"] .border-orange-200 { border-color: rgba(255,255,255,.16) !important; }
        html[data-theme="dark"] button:disabled,
        html[data-theme="dark"] .cursor-not-allowed { opacity: .75 !important; }

        main > * { max-width: 100%; }
        main img { max-width: 100%; height: auto; }
        main .overflow-x-auto, .table-scroll-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: thin; }
        main table { min-width: 680px; }
        .sidebar-bubble {
            position: absolute;
            border-radius: 999px;
            background: rgba(255,255,255,.10);
            pointer-events: none;
        }
        .sidebar-bubble.b1 { width: 110px; height: 110px; top: -28px; right: -24px; }
        .sidebar-bubble.b2 { width: 58px; height: 58px; bottom: 115px; left: -18px; }

        .theme-button-ocean {
            width: 40px;
            height: 40px;
            border-radius: 18px;
            border: 1px solid var(--line);
            background: var(--chip-bg);
            color: #00897B;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform .18s ease, background .18s ease, color .18s ease;
        }
        html[data-theme="dark"] .theme-button-ocean { color: #8af3eb; }
        .theme-button-ocean:hover { transform: translateY(-1px); }
        html[data-theme="dark"] .shadow-sm,
        html[data-theme="dark"] .shadow-soft { box-shadow: 0 18px 45px rgba(0,0,0,.18) !important; }
        html[data-theme="dark"] input::placeholder,
        html[data-theme="dark"] textarea::placeholder { color: rgba(214,255,250,.52) !important; }
        html[data-theme="dark"] table,
        html[data-theme="dark"] tbody,
        html[data-theme="dark"] thead { color: var(--app-ink); }
        html[data-theme="dark"] thead,
        html[data-theme="dark"] thead.bg-slate-50,
        html[data-theme="dark"] .bg-slate-50,
        html[data-theme="dark"] .bg-slate-100 { background-color: rgba(3, 47, 55, .78) !important; }


        /* =========================================================
           NOTIFICATION CENTER
           Badge tetap terlihat, panel ringkas saat lonceng diklik,
           dan toast di tengah atas saat notifikasi baru masuk.
        ========================================================= */
        .notification-trigger {
            position: relative;
            min-height: 42px;
            padding: 0 .78rem;
            border-radius: 16px;
            border: 1px solid var(--ui-border, var(--line));
            background: var(--ui-surface, var(--surface));
            color: var(--ui-text-2, var(--app-muted));
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            box-shadow: var(--ui-shadow-sm, 0 6px 18px rgba(0,0,0,.06));
            transition: transform .18s ease, border-color .18s ease, color .18s ease, background .18s ease;
        }
        .notification-trigger:hover {
            transform: translateY(-1px);
            border-color: color-mix(in srgb, var(--ui-primary, #0f9d94) 42%, var(--ui-border, #dce8e6));
            color: var(--ui-primary, #0f9d94);
        }
        .notification-trigger.has-unread {
            border-color: color-mix(in srgb, #ef4444 35%, var(--ui-border, #dce8e6));
        }
        .notification-trigger.has-unread .notification-bell-icon {
            color: #ef4444;
            transform-origin: top center;
            animation: simadaBellAttention 2.8s ease-in-out infinite;
        }
        .notification-live-badge {
            min-width: 19px;
            height: 19px;
            padding: 0 5px;
            border-radius: 999px;
            background: #ef4444;
            color: #fff;
            font-size: 10px;
            font-weight: 900;
            line-height: 19px;
            text-align: center;
            box-shadow: 0 0 0 3px var(--ui-surface, #fff), 0 5px 12px rgba(239,68,68,.28);
        }
        .notification-count-copy {
            color: var(--ui-text-2, #455f5c);
            font-size: .73rem;
            font-weight: 800;
            white-space: nowrap;
        }
        .notification-trigger.has-unread .notification-count-copy { color: #dc2626; }

        .simada-notif-panel {
            position: fixed;
            top: 82px;
            right: 18px;
            z-index: 80;
            width: min(390px, calc(100vw - 24px));
            max-height: min(560px, calc(100vh - 104px));
            overflow: hidden;
            border-radius: 22px;
            border: 1px solid var(--ui-border, var(--line));
            background: color-mix(in srgb, var(--ui-surface, #fff) 96%, transparent);
            color: var(--ui-text, #172c2b);
            box-shadow: 0 24px 70px rgba(15, 23, 42, .18);
            backdrop-filter: blur(18px);
            transform-origin: top right;
            animation: simadaNotifPanelIn .18s ease-out both;
        }
        html[data-theme="dark"] .simada-notif-panel { box-shadow: 0 24px 70px rgba(0,0,0,.40); }
        .simada-notif-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.05rem .85rem;
            border-bottom: 1px solid var(--ui-border, var(--line));
        }
        .simada-notif-panel-body {
            max-height: 390px;
            overflow-y: auto;
            padding: .55rem;
        }
        .simada-notif-item {
            display: flex;
            gap: .75rem;
            padding: .8rem;
            border-radius: 16px;
            color: inherit;
            text-decoration: none;
            transition: background .18s ease, transform .18s ease;
        }
        .simada-notif-item:hover {
            background: var(--ui-primary-soft, #e8f7f5);
            transform: translateX(2px);
        }
        .simada-notif-item-icon {
            width: 38px;
            height: 38px;
            border-radius: 13px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            background: var(--ui-primary-soft, #e8f7f5);
            color: var(--ui-primary, #0f9d94);
        }
        .simada-notif-item-title {
            color: var(--ui-text, #172c2b);
            font-size: .85rem;
            font-weight: 850;
            line-height: 1.35;
        }
        .simada-notif-item-message {
            color: var(--ui-muted, #667c79);
            font-size: .76rem;
            line-height: 1.45;
            margin-top: .16rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .simada-notif-item-time {
            color: var(--ui-faint, #849794);
            font-size: .66rem;
            margin-top: .35rem;
        }
        .simada-notif-empty {
            padding: 2rem 1rem;
            text-align: center;
            color: var(--ui-muted, #667c79);
            font-size: .82rem;
        }
        .simada-notif-panel-foot {
            padding: .7rem;
            border-top: 1px solid var(--ui-border, var(--line));
        }
        .simada-notif-view-all {
            min-height: 42px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .5rem;
            background: var(--ui-primary, #0f9d94);
            color: #fff !important;
            font-size: .78rem;
            font-weight: 850;
            text-decoration: none;
        }

        .simada-alert-toast-host {
            position: fixed;
            top: 88px;
            left: 50%;
            z-index: 95;
            width: min(560px, calc(100vw - 24px));
            transform: translateX(-50%);
            pointer-events: none;
        }
        .simada-alert-toast {
            pointer-events: auto;
            display: grid;
            grid-template-columns: 42px minmax(0,1fr) auto;
            gap: .8rem;
            align-items: start;
            padding: .85rem .9rem;
            border-radius: 18px;
            border: 1px solid color-mix(in srgb, var(--ui-primary, #0f9d94) 35%, var(--ui-border, #dce8e6));
            background: color-mix(in srgb, var(--ui-surface, #fff) 97%, transparent);
            color: var(--ui-text, #172c2b);
            box-shadow: 0 18px 55px rgba(15,23,42,.18);
            backdrop-filter: blur(16px);
            animation: simadaToastIn .28s cubic-bezier(.22,.8,.28,1) both;
        }
        html[data-theme="dark"] .simada-alert-toast { box-shadow: 0 18px 55px rgba(0,0,0,.42); }
        .simada-alert-toast.out { animation: simadaToastOut .22s ease-in both; }
        .simada-alert-toast-icon {
            width: 42px;
            height: 42px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--ui-primary-soft, #e8f7f5);
            color: var(--ui-primary, #0f9d94);
            font-size: 1rem;
        }
        .simada-alert-toast-title {
            font-size: .88rem;
            font-weight: 900;
            line-height: 1.35;
        }
        .simada-alert-toast-message {
            color: var(--ui-muted, #667c79);
            font-size: .77rem;
            line-height: 1.45;
            margin-top: .18rem;
        }
        .simada-alert-toast-actions {
            display: flex;
            align-items: center;
            gap: .35rem;
        }
        .simada-alert-toast-open,
        .simada-alert-toast-close {
            min-height: 34px;
            border-radius: 11px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: .72rem;
            font-weight: 850;
        }
        .simada-alert-toast-open {
            padding: 0 .65rem;
            background: var(--ui-primary, #0f9d94);
            color: #fff !important;
            text-decoration: none;
        }
        .simada-alert-toast-close {
            width: 34px;
            border: 1px solid var(--ui-border, var(--line));
            background: var(--ui-surface-soft, #f5f9f8);
            color: var(--ui-muted, #667c79);
        }
        @keyframes simadaBellAttention {
            0%, 72%, 100% { transform: rotate(0); }
            78% { transform: rotate(12deg); }
            84% { transform: rotate(-10deg); }
            90% { transform: rotate(7deg); }
            96% { transform: rotate(-4deg); }
        }
        @keyframes simadaNotifPanelIn {
            from { opacity: 0; transform: translateY(-7px) scale(.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes simadaToastIn {
            from { opacity: 0; transform: translateY(-14px) scale(.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }
        @keyframes simadaToastOut {
            to { opacity: 0; transform: translateY(-10px) scale(.985); }
        }
        @media (prefers-reduced-motion: reduce) {
            .notification-trigger.has-unread .notification-bell-icon,
            .simada-notif-panel,
            .simada-alert-toast,
            .simada-alert-toast.out { animation: none !important; }
        }

        @media (max-width: 767px) {
            .notification-count-copy { display: none; }
            .notification-trigger { width: 42px; padding: 0; }
            .simada-notif-panel { top: 76px; right: 8px; width: calc(100vw - 16px); max-height: calc(100vh - 88px); }
            .simada-alert-toast-host { top: 78px; width: calc(100vw - 16px); }
            .simada-alert-toast { grid-template-columns: 38px minmax(0,1fr) auto; gap: .65rem; padding: .75rem; }
            .simada-alert-toast-icon { width: 38px; height: 38px; border-radius: 12px; }
            .simada-alert-toast-open { display: none; }
            #sidebar { width: min(88vw, 312px); }
            .sidebar-logo-shell { width: 110px; height: 110px; border-radius: 999px; }
            .sidebar-logo-shell img { width: 96px; height: 96px; }
            .app-header { height: 72px !important; padding-left: .85rem !important; padding-right: .85rem !important; }
            .app-main { padding: .85rem !important; padding-bottom: 1.6rem !important; }
            main .rounded-3xl { border-radius: 1.15rem !important; }
            main .rounded-2xl { border-radius: 1rem !important; }
            main .p-10 { padding: 1.2rem !important; }
            main .p-8 { padding: 1.1rem !important; }
            main .p-6 { padding: 1rem !important; }
            main .p-5 { padding: .9rem !important; }
            main .grid { column-gap: .75rem; row-gap: .75rem; }
            main input, main select, main textarea, main button { min-height: 44px; }
            main table { min-width: 620px; }
            .partner-chip .partner-copy { display: none; }
        }
        @media (max-width: 390px) {
            .app-main { padding: .62rem !important; }
            .header-user-copy { display: none !important; }
            main .p-6, main .p-5 { padding: .82rem !important; }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/simada-theme.css?v=20260809-theme2">
</head>
<body>
    <div class="min-h-screen">
        <div id="sidebarOverlay" class="fixed inset-0 bg-slate-950/40 backdrop-blur-sm z-40 hidden md:hidden" onclick="closeSidebar()"></div>

        <aside id="sidebar" class="sidebar fixed top-0 left-0 z-50 w-[282px] h-screen text-white flex flex-col shadow-nav transform -translate-x-full transition-transform duration-300 md:translate-x-0">
            <div class="sidebar-bubble b1"></div>
            <div class="sidebar-bubble b2"></div>
            <div class="relative overflow-hidden px-5 pt-5 pb-4 border-b border-white/10">
                <div class="relative text-center">
                    <div class="sidebar-logo-shell" aria-label="Logo Unit UPTD SPAPAL">
                        <img src="<?= e($logoPath) ?>" alt="Logo Unit UPTD SPAPAL Muara Badak-Sebulu">
                    </div>
                    <div>
                        <span class="inline-flex items-center gap-1.5 mt-3 px-2.5 py-1 rounded-full bg-white/10 border border-white/15 text-[9px] font-extrabold tracking-[.15em] uppercase text-white">
                            <i class="fa-solid fa-water"></i> UPTD SPAPAL
                        </span>
                        <h1 class="text-[1.75rem] font-black tracking-[-.04em] leading-none mt-2">SIMADA</h1>
                        <p class="sidebar-subtitle mt-1">Sistem Informasi Manajemen Data</p>
                        <p class="sidebar-unit"><?= e($unitName) ?></p>
                    </div>
                </div>
            </div>

            <div class="px-5 py-4 border-b border-white/10 bg-white/[.045]">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl border border-white/15 bg-white/12 overflow-hidden flex items-center justify-center shrink-0">
                        <?php if ($profilePhotoUrl !== ''): ?>
                            <img src="<?= e($profilePhotoUrl) ?>" alt="Foto Profil" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="text-sm font-bold text-white"><?= e($initials) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="min-w-0">
                        <p class="font-semibold text-sm truncate"><?= e($displayName !== '' ? $displayName : $username) ?></p>
                        <p class="text-xs text-white/70 leading-relaxed truncate"><?= e($jabatan) ?></p>
                    </div>
                </div>
            </div>

            <nav class="sidebar-scroll flex-1 px-3 py-4 space-y-1.5 text-sm overflow-y-auto">
                <?php foreach ($pages as $key => $item): ?>
                    <?php
                    $isActive = $page === (string) $key;
                    $menuClass = $isActive
                        ? 'bg-white text-reef font-bold shadow-lg shadow-emerald-950/10'
                        : 'hover:bg-white/10 text-white/80 hover:text-white';
                    ?>
                    <a href="main.php?page=<?= e($key) ?>" class="flex items-center gap-3 px-4 py-3 rounded-2xl transition <?= e($menuClass) ?>">
                        <i class="fa-solid <?= e((string) ($item['icon'] ?? 'fa-circle')) ?> w-5 text-center"></i>
                        <span class="flex-1"><?= e((string) ($item['label'] ?? ucfirst((string) $key))) ?></span>
                        <?php if ((string) $key === 'notifikasi'): ?>
                            <span data-notification-badge class="min-w-[20px] h-5 px-1.5 rounded-full bg-red-500 text-white text-[10px] font-black inline-flex items-center justify-center <?= $unreadCount > 0 ? '' : 'hidden' ?>"><?= (int) min($unreadCount, 99) ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <div class="p-3 border-t border-white/10">
                <a href="<?= e($logoutPath) ?>" class="flex items-center justify-center gap-2 w-full px-4 py-3 rounded-2xl bg-white/10 hover:bg-red-500 transition text-sm font-semibold">
                    <i class="fa-solid fa-right-from-bracket"></i><span>Keluar</span>
                </a>
            </div>
        </aside>

        <div class="md:ml-[282px] min-h-screen flex flex-col">
            <header class="app-header sticky top-0 z-30 h-[76px] border-b flex items-center justify-between px-4 md:px-7 shadow-sm">
                <div class="flex items-center gap-3 min-w-0">
                    <button type="button" class="md:hidden w-10 h-10 rounded-2xl border border-slate-200 bg-white text-slate-600 flex items-center justify-center" onclick="openSidebar()" aria-label="Buka menu">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <div class="min-w-0">
                        <h2 class="text-base md:text-xl font-extrabold leading-none text-slate-950 truncate"><?= e($pageTitle) ?></h2>
                        <p class="text-[11px] md:text-xs text-slate-500 mt-1 truncate"><?= e($panelTitle) ?></p>
                    </div>
                </div>

                <div class="flex items-center gap-2 sm:gap-3">
                    <div class="partner-chip hidden sm:flex" aria-label="Logo Mitra">
                        <img src="<?= e($secondaryLogoPath) ?>" alt="Logo Mitra Kalimantan Timur">
                        <div class="partner-copy text-left">
                            <span class="block text-xs font-semibold text-slate-600 truncate max-w-[150px]">Kalimantan Timur</span>
                        </div>
                    </div>
                    <button type="button" id="themeToggle" onclick="toggleAppTheme()" class="theme-button-ocean" aria-label="Ubah mode siang malam"><i id="themeIcon" class="fa-solid fa-moon"></i></button>
                    <button type="button" id="notificationToggle" class="notification-trigger <?= $unreadCount > 0 ? 'has-unread' : '' ?>" onclick="toggleNotificationPanel(event)" aria-label="Buka notifikasi" aria-expanded="false" aria-controls="notificationPanel">
                        <span class="relative inline-flex items-center justify-center w-5 h-5">
                            <i class="notification-bell-icon fa-regular fa-bell"></i>
                            <span data-notification-badge data-header-notification-badge class="notification-live-badge absolute -top-3 -right-3 <?= $unreadCount > 0 ? '' : 'hidden' ?>"><?= (int) min($unreadCount, 99) ?></span>
                        </span>
                        <span data-notification-label class="notification-count-copy hidden lg:inline"><?= $unreadCount > 0 ? e((string) min($unreadCount, 99)) . ' baru' : 'Notifikasi' ?></span>
                    </button>
                    <a href="main.php?page=profile" class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-2 py-1.5 hover:border-reef/30 hover:shadow-sm transition">
                        <div class="w-9 h-9 rounded-xl bg-sea/12 text-reef flex items-center justify-center shrink-0 overflow-hidden">
                            <?php if ($profilePhotoUrl !== ''): ?>
                                <img src="<?= e($profilePhotoUrl) ?>" alt="Foto Profil" class="w-full h-full object-cover">
                            <?php else: ?>
                                <span class="text-xs font-bold"><?= e($initials) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="header-user-copy hidden sm:block text-left max-w-[150px]">
                            <span class="block text-sm font-semibold text-slate-800 truncate"><?= e($displayName !== '' ? $displayName : $username) ?></span>
                            <span class="block text-[11px] text-slate-500 truncate"><?= e(ucfirst($role)) ?></span>
                        </div>
                    </a>
                </div>
            </header>

            <div id="notificationPanel" class="simada-notif-panel hidden" role="dialog" aria-label="Notifikasi terbaru">
                <div class="simada-notif-panel-head">
                    <div>
                        <div class="font-extrabold text-sm">Notifikasi terbaru</div>
                        <div id="notificationPanelSummary" class="text-xs text-slate-500 mt-0.5">Tidak ada notifikasi baru</div>
                    </div>
                    <button type="button" onclick="closeNotificationPanel()" class="w-9 h-9 rounded-xl border border-slate-200 bg-slate-50 text-slate-500 hover:text-primary" aria-label="Tutup notifikasi">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
                <div id="notificationPanelBody" class="simada-notif-panel-body">
                    <div class="simada-notif-empty"><i class="fa-regular fa-bell text-xl mb-2 block"></i>Belum ada notifikasi baru.</div>
                </div>
                <div class="simada-notif-panel-foot">
                    <a href="main.php?page=notifikasi" class="simada-notif-view-all"><i class="fa-regular fa-bell"></i> Lihat semua notifikasi</a>
                </div>
            </div>
            <div id="notificationToastHost" class="simada-alert-toast-host" aria-live="polite" aria-atomic="true"></div>

            <main class="app-main p-4 md:p-7 flex-1">
                <?php
                $contentFile = $pageDir . DIRECTORY_SEPARATOR . $page . '.php';
                if (is_file($contentFile)) {
                    include $contentFile;
                } else {
                    echo '<div class="bg-white rounded-3xl p-6 shadow-soft text-red-600">Halaman tidak ditemukan.</div>';
                }
                ?>
            </main>
        </div>
    </div>

    <script>
        const SIMADA_CSRF_TOKEN = <?= json_encode($csrfToken, JSON_UNESCAPED_SLASHES) ?>;
        const SIMADA_NOTIFICATION_ENDPOINT = <?= json_encode($notificationEndpoint, JSON_UNESCAPED_SLASHES) ?>;
        let simadaLastNotificationCount = <?= (int) $unreadCount ?>;
        function syncAppThemeIcon() {
            const dark = document.documentElement.getAttribute('data-theme') === 'dark';
            const icon = document.getElementById('themeIcon');
            if (icon) icon.className = dark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
            const meta = document.querySelector('meta[name="theme-color"]');
            if (meta) meta.setAttribute('content', dark ? '#071416' : '#0F9D94');
        }
        function toggleAppTheme() {
            const dark = document.documentElement.getAttribute('data-theme') !== 'dark';
            document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
            localStorage.setItem('simada-theme', dark ? 'dark' : 'light');
            syncAppThemeIcon();
        }
        function openSidebar() {
            document.getElementById('sidebar').classList.remove('-translate-x-full');
            document.getElementById('sidebarOverlay').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        function closeSidebar() {
            document.getElementById('sidebar').classList.add('-translate-x-full');
            document.getElementById('sidebarOverlay').classList.add('hidden');
            document.body.style.overflow = '';
        }


        function escapeNotificationHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatNotificationTime(value) {
            if (!value) return '';
            const normalized = String(value).replace(' ', 'T');
            const date = new Date(normalized);
            if (Number.isNaN(date.getTime())) return String(value);
            try {
                return new Intl.DateTimeFormat('id-ID', {
                    day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit'
                }).format(date);
            } catch (e) {
                return String(value);
            }
        }

        function attachCsrfToForms() {
            document.querySelectorAll('form').forEach(function (form) {
                const method = (form.getAttribute('method') || 'GET').toUpperCase();
                if (method !== 'POST') return;
                let input = form.querySelector('input[name="csrf_token"]');
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'csrf_token';
                    form.appendChild(input);
                }
                input.value = SIMADA_CSRF_TOKEN;
            });
        }

        function updateNotificationBadges(count) {
            const safeCount = Math.max(0, Number(count) || 0);
            document.querySelectorAll('[data-notification-badge]').forEach(function (badge) {
                badge.textContent = safeCount > 99 ? '99+' : String(safeCount);
                badge.classList.toggle('hidden', safeCount <= 0);
            });
            document.querySelectorAll('[data-notification-label]').forEach(function (label) {
                label.textContent = safeCount > 0 ? (safeCount > 99 ? '99+ baru' : safeCount + ' baru') : 'Notifikasi';
            });
            const trigger = document.getElementById('notificationToggle');
            if (trigger) trigger.classList.toggle('has-unread', safeCount > 0);
        }

        function renderNotificationPanel(items, count) {
            const body = document.getElementById('notificationPanelBody');
            const summary = document.getElementById('notificationPanelSummary');
            if (summary) {
                summary.textContent = count > 0 ? count + ' notifikasi belum dibaca' : 'Semua notifikasi sudah dibaca';
            }
            if (!body) return;
            if (!Array.isArray(items) || items.length === 0) {
                body.innerHTML = '<div class="simada-notif-empty"><i class="fa-regular fa-bell text-xl mb-2 block"></i>Belum ada notifikasi baru.</div>';
                return;
            }
            body.innerHTML = items.map(function (item) {
                const id = Number(item.id) || 0;
                const url = id > 0 ? 'main.php?page=notifikasi&action=open&id=' + encodeURIComponent(id) : 'main.php?page=notifikasi';
                return '<a class="simada-notif-item" href="' + url + '">' +
                    '<span class="simada-notif-item-icon"><i class="fa-solid fa-bell"></i></span>' +
                    '<span class="min-w-0 flex-1">' +
                        '<span class="simada-notif-item-title block">' + escapeNotificationHtml(item.title || 'Notifikasi') + '</span>' +
                        '<span class="simada-notif-item-message block">' + escapeNotificationHtml(item.message || '') + '</span>' +
                        '<span class="simada-notif-item-time block"><i class="fa-regular fa-clock mr-1"></i>' + escapeNotificationHtml(formatNotificationTime(item.created_at)) + '</span>' +
                    '</span>' +
                '</a>';
            }).join('');
        }

        function toggleNotificationPanel(event) {
            if (event) event.stopPropagation();
            const panel = document.getElementById('notificationPanel');
            const trigger = document.getElementById('notificationToggle');
            if (!panel) return;
            const opening = panel.classList.contains('hidden');
            panel.classList.toggle('hidden', !opening);
            if (trigger) trigger.setAttribute('aria-expanded', opening ? 'true' : 'false');
            if (opening) refreshNotificationCount(true);
        }

        function closeNotificationPanel() {
            const panel = document.getElementById('notificationPanel');
            const trigger = document.getElementById('notificationToggle');
            if (panel) panel.classList.add('hidden');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
        }

        function dismissNotificationToast() {
            const host = document.getElementById('notificationToastHost');
            if (!host) return;
            const toast = host.querySelector('.simada-alert-toast');
            if (!toast) return;
            toast.classList.add('out');
            setTimeout(function () { host.innerHTML = ''; }, 240);
        }

        function showNotificationToast(item, count, isReminder) {
            const host = document.getElementById('notificationToastHost');
            if (!host) return;
            const title = item && item.title ? item.title : (count + ' notifikasi belum dibaca');
            const message = item && item.message
                ? item.message
                : 'Ada informasi yang perlu Anda lihat. Klik Lihat untuk membuka halaman notifikasi.';
            const id = item && Number(item.id) > 0 ? Number(item.id) : 0;
            const url = id > 0 ? 'main.php?page=notifikasi&action=open&id=' + encodeURIComponent(id) : 'main.php?page=notifikasi';
            host.innerHTML = '<div class="simada-alert-toast" role="status">' +
                '<div class="simada-alert-toast-icon"><i class="fa-solid ' + (isReminder ? 'fa-bell' : 'fa-bell-ring') + '"></i></div>' +
                '<div class="min-w-0">' +
                    '<div class="simada-alert-toast-title">' + escapeNotificationHtml(title) + '</div>' +
                    '<div class="simada-alert-toast-message">' + escapeNotificationHtml(message) + '</div>' +
                '</div>' +
                '<div class="simada-alert-toast-actions">' +
                    '<a class="simada-alert-toast-open" href="' + url + '">Lihat</a>' +
                    '<button type="button" class="simada-alert-toast-close" onclick="dismissNotificationToast()" aria-label="Tutup"><i class="fa-solid fa-xmark"></i></button>' +
                '</div>' +
            '</div>';
            clearTimeout(window.simadaNotificationToastTimer);
            window.simadaNotificationToastTimer = setTimeout(dismissNotificationToast, isReminder ? 9000 : 11000);
        }

        async function refreshNotificationCount(silent) {
            try {
                const response = await fetch(SIMADA_NOTIFICATION_ENDPOINT + '?_=' + Date.now(), {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': SIMADA_CSRF_TOKEN
                    }
                });
                if (!response.ok) return;
                const data = await response.json();
                if (!data || data.ok !== true) return;
                const nextCount = Math.max(0, Number(data.unread_count) || 0);
                const latest = Array.isArray(data.latest) ? data.latest : [];
                const previousCount = simadaLastNotificationCount;

                updateNotificationBadges(nextCount);
                renderNotificationPanel(latest, nextCount);

                if (!silent && nextCount > previousCount) {
                    showNotificationToast(latest[0] || null, nextCount, false);
                }

                if (silent && nextCount > 0) {
                    const reminderKey = 'simada-notification-reminder-' + String(nextCount) + '-' + String(latest[0]?.id || 0);
                    if (!sessionStorage.getItem(reminderKey)) {
                        sessionStorage.setItem(reminderKey, '1');
                        setTimeout(function () { showNotificationToast(latest[0] || null, nextCount, true); }, 700);
                    }
                }

                simadaLastNotificationCount = nextCount;
            } catch (error) {
                // Gangguan polling notifikasi tidak boleh mengganggu halaman utama.
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            syncAppThemeIcon();
            attachCsrfToForms();
            updateNotificationBadges(simadaLastNotificationCount);
            refreshNotificationCount(true);
            setInterval(function () { refreshNotificationCount(false); }, 5000);
            document.addEventListener('visibilitychange', function () {
                if (!document.hidden) refreshNotificationCount(false);
            });
            window.addEventListener('focus', function () { refreshNotificationCount(false); });
            document.addEventListener('click', function (event) {
                const panel = document.getElementById('notificationPanel');
                const trigger = document.getElementById('notificationToggle');
                if (!panel || panel.classList.contains('hidden')) return;
                if (panel.contains(event.target) || (trigger && trigger.contains(event.target))) return;
                closeNotificationPanel();
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closeNotificationPanel();
                    dismissNotificationToast();
                }
            });

            if (window.innerWidth < 768) {
                document.getElementById('sidebar').classList.add('-translate-x-full');
            }

            document.querySelectorAll('main table').forEach(function (table) {
                if (table.parentElement && (table.parentElement.classList.contains('overflow-x-auto') || table.parentElement.classList.contains('table-scroll-wrap'))) return;
                const wrapper = document.createElement('div');
                wrapper.className = 'table-scroll-wrap';
                table.parentNode.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            });

            document.querySelectorAll('th[data-sort]').forEach(function (th) {
                th.style.cursor = 'pointer';
                th.addEventListener('click', function () {
                    const table = th.closest('table');
                    if (!table || !table.tBodies.length) return;
                    const col = Array.prototype.indexOf.call(th.parentElement.children, th);
                    const asc = th.dataset.dir !== 'asc';
                    th.dataset.dir = asc ? 'asc' : 'desc';
                    Array.from(table.tBodies[0].rows)
                        .sort(function (a, b) {
                            const x = (a.cells[col]?.textContent || '').trim();
                            const y = (b.cells[col]?.textContent || '').trim();
                            return asc ? x.localeCompare(y, 'id', { numeric: true }) : y.localeCompare(x, 'id', { numeric: true });
                        })
                        .forEach(function (tr) { table.tBodies[0].appendChild(tr); });
                });
            });

            document.querySelectorAll('form').forEach(function (form) {
                form.addEventListener('submit', function () {
                    const btn = form.querySelector('[type="submit"]:not([data-no-load])');
                    if (!btn || btn.dataset.loadingApplied === '1') return;
                    btn.dataset.loadingApplied = '1';
                    btn.disabled = true;
                    const original = btn.innerHTML;
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Memproses...';
                    setTimeout(function () {
                        btn.disabled = false;
                        btn.dataset.loadingApplied = '0';
                        btn.innerHTML = original;
                    }, 10000);
                });
            });
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth >= 768) {
                document.getElementById('sidebarOverlay').classList.add('hidden');
                document.getElementById('sidebar').classList.remove('-translate-x-full');
                document.body.style.overflow = '';
            } else {
                document.getElementById('sidebar').classList.add('-translate-x-full');
            }
        });

        window.exportCsv = function (tableId, filename) {
            const rows = Array.from(document.querySelectorAll('#' + tableId + ' tr'));
            const csv = rows.map(function (row) {
                return Array.from(row.cells).map(function (cell) {
                    return '"' + cell.textContent.trim().replace(/"/g, '""') + '"';
                }).join(',');
            }).join('\n');
            const a = document.createElement('a');
            a.href = 'data:text/csv;charset=utf-8,' + encodeURIComponent(csv);
            a.download = filename + '.csv';
            a.click();
        };
    </script>
</body>
</html>
        <?php
    }
}
