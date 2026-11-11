<?php
/* =========================================================
   HALAMAN LOGIN
   Fungsi: Memproses login, memeriksa akun aktif, membuat session, lalu mengarahkan admin atau user ke dashboard.
========================================================= */

declare(strict_types=1);
require_once __DIR__ . '/config/security.php';
appSecureSessionStart();
appSendSecurityHeaders();
require_once 'config/koneksi.php';
require_once 'config/auth.php';

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

$adminWa = '62895700381419';
$waText = urlencode('Halo Admin, saya lupa kata sandi akun SIMADA. Mohon bantuannya untuk reset kata sandi akun saya.');
$waLink = "https://wa.me/{$adminWa}?text={$waText}";

if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/main.php');
    } else {
        header('Location: user/main.php');
    }
    exit();
}

$errorMsg = '';
$oldUsername = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    appRequireCsrfToken();
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $oldUsername = $username;

    if ($username === '' || $password === '') {
        $errorMsg = 'Username dan password wajib diisi!';
    } else {
        $stmt = $conn->prepare("
            SELECT id, username, nip, password, name, jabatan, role, is_active
            FROM users
            WHERE username = ?
            LIMIT 1
        ");

        if ($stmt) {
            $stmt->bind_param('s', $username);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();

            if (!$user) {
                $errorMsg = 'Username atau password salah!';
            } elseif ((int) $user['is_active'] !== 1) {
                $errorMsg = 'Akun tidak aktif.';
            } elseif (!verifyAppPassword($password, $user['password'] ?? '')) {
                $errorMsg = 'Username atau password salah!';
            } else {
                if (shouldUpgradePasswordHash($user['password'] ?? '')) {
                    upgradePasswordHash($conn, (int) $user['id'], $password);
                }

                session_regenerate_id(true);

                $_SESSION['user_id']  = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nip']      = $user['nip'];
                $_SESSION['name']     = $user['name'];
                $_SESSION['jabatan']  = $user['jabatan'];
                $_SESSION['role']     = $user['role'];

                if ($user['role'] === 'admin') {
                    header('Location: admin/main.php');
                } else {
                    header('Location: user/main.php');
                }
                exit();
            }
        } else {
            $errorMsg = 'Query login gagal diproses.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0F9D94">
    <title>SIMADA - Sistem Informasi Manajemen Data</title>
    <link rel="icon" type="image/png" href="assets/picture/favicon_unit.png">
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
                        sea: '#00BCD4',
                        coral: '#FF5252',
                        reef: '#00897B',
                        foam: '#FFF8EF',
                        sand: '#FFEBCB',
                        sun: '#FFEB3B'
                    },
                    boxShadow: {
                        ocean: '0 28px 80px rgba(0, 137, 123, .18)',
                        soft: '0 18px 45px rgba(0, 0, 0, .10)'
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            color-scheme: light;
            --ink: #16333d;
            --muted: #5f7d85;
            --page-bg: radial-gradient(circle at top left, rgba(0,188,212,.18), transparent 28%), radial-gradient(circle at bottom right, rgba(255,82,82,.16), transparent 28%), linear-gradient(180deg, #f9fffd 0%, #eefbf8 45%, #fffdf6 100%);
            --stage-bg: rgba(255, 255, 255, .74);
            --card-bg: rgba(255,255,255,.88);
            --card-strong: rgba(255,255,255,.92);
            --hero-bg: linear-gradient(180deg, rgba(0,188,212,.10), rgba(0,137,123,.12) 55%, rgba(255,235,203,.42));
            --form-bg: linear-gradient(180deg, rgba(255,255,255,.62), rgba(255,255,255,.90));
            --border-soft: rgba(0,137,123,.12);
            --theme-chip-bg: rgba(255,255,255,.82);
            --theme-chip-text: #00897B;
        }
        html[data-theme="dark"] {
            color-scheme: dark;
            --ink: #eafffb;
            --muted: #b6d5d0;
            --page-bg: radial-gradient(circle at top left, rgba(0,188,212,.20), transparent 28%), radial-gradient(circle at bottom right, rgba(255,82,82,.12), transparent 30%), linear-gradient(180deg, #032f3a 0%, #064b52 46%, #01252f 100%);
            --stage-bg: rgba(3, 40, 48, .70);
            --card-bg: rgba(5, 66, 70, .70);
            --card-strong: rgba(6, 72, 76, .82);
            --hero-bg: linear-gradient(180deg, rgba(0,188,212,.12), rgba(0,137,123,.18) 55%, rgba(1,37,47,.56));
            --form-bg: linear-gradient(180deg, rgba(5,62,70,.68), rgba(2,37,48,.92));
            --border-soft: rgba(255,255,255,.16);
            --theme-chip-bg: rgba(2,37,48,.78);
            --theme-chip-text: #8af3eb;
        }
        * { box-sizing: border-box; }
        html, body { min-height: 100%; }
        body {
            margin: 0;
            font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            color: var(--ink);
            background: var(--page-bg);
            overflow-x: hidden;
            transition: background .25s ease, color .25s ease;
        }
        .ocean-bg {
            position: fixed;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
        }
        .bubble {
            position: absolute;
            bottom: -140px;
            border-radius: 999px;
            background: rgba(255,255,255,.30);
            border: 1.5px solid rgba(13,148,136,.34);
            box-shadow:
                inset 0 0 0 2px rgba(255,255,255,.34),
                0 7px 20px rgba(13,148,136,.12);
            animation: riseBubble linear infinite;
            will-change: transform, opacity;
        }
        .bubble::after {
            content: '';
            position: absolute;
            width: 28%;
            height: 28%;
            top: 18%;
            left: 20%;
            border-radius: 999px;
            background: rgba(255,255,255,.78);
            filter: blur(.2px);
        }
        .bubble:nth-child(1)  { left: 3%;  width: 13px; height: 13px; animation-duration: 10s; animation-delay: -2s; }
        .bubble:nth-child(2)  { left: 8%;  width: 24px; height: 24px; animation-duration: 15s; animation-delay: -8s; }
        .bubble:nth-child(3)  { left: 14%; width: 9px;  height: 9px;  animation-duration: 8s;  animation-delay: -4s; }
        .bubble:nth-child(4)  { left: 20%; width: 17px; height: 17px; animation-duration: 13s; animation-delay: -10s; }
        .bubble:nth-child(5)  { left: 27%; width: 30px; height: 30px; animation-duration: 19s; animation-delay: -6s; }
        .bubble:nth-child(6)  { left: 34%; width: 12px; height: 12px; animation-duration: 9s;  animation-delay: -1s; }
        .bubble:nth-child(7)  { left: 41%; width: 20px; height: 20px; animation-duration: 16s; animation-delay: -12s; }
        .bubble:nth-child(8)  { left: 47%; width: 8px;  height: 8px;  animation-duration: 8s;  animation-delay: -5s; }
        .bubble:nth-child(9)  { left: 53%; width: 26px; height: 26px; animation-duration: 18s; animation-delay: -9s; }
        .bubble:nth-child(10) { left: 59%; width: 14px; height: 14px; animation-duration: 11s; animation-delay: -7s; }
        .bubble:nth-child(11) { left: 65%; width: 34px; height: 34px; animation-duration: 21s; animation-delay: -15s; }
        .bubble:nth-child(12) { left: 71%; width: 10px; height: 10px; animation-duration: 9s;  animation-delay: -3s; }
        .bubble:nth-child(13) { left: 76%; width: 18px; height: 18px; animation-duration: 14s; animation-delay: -11s; }
        .bubble:nth-child(14) { left: 81%; width: 27px; height: 27px; animation-duration: 17s; animation-delay: -13s; }
        .bubble:nth-child(15) { left: 86%; width: 11px; height: 11px; animation-duration: 10s; animation-delay: -6s; }
        .bubble:nth-child(16) { left: 90%; width: 21px; height: 21px; animation-duration: 15s; animation-delay: -2s; }
        .bubble:nth-child(17) { left: 94%; width: 8px;  height: 8px;  animation-duration: 8s;  animation-delay: -7s; }
        .bubble:nth-child(18) { left: 97%; width: 16px; height: 16px; animation-duration: 12s; animation-delay: -10s; }
        @keyframes riseBubble {
            0%   { transform: translate3d(0, 0, 0) scale(.72); opacity: 0; }
            10%  { opacity: .78; }
            45%  { transform: translate3d(18px, -48vh, 0) scale(1); }
            72%  { transform: translate3d(-10px, -80vh, 0) scale(1.08); opacity: .62; }
            100% { transform: translate3d(10px, -118vh, 0) scale(1.16); opacity: 0; }
        }
        html[data-theme="dark"] .bubble {
            background: rgba(125,211,252,.10);
            border-color: rgba(94,234,212,.34);
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.11), 0 7px 24px rgba(45,212,191,.08);
        }

        .fish {
            position: absolute;
            opacity: .82;
            filter: drop-shadow(0 8px 14px rgba(0,0,0,.10));
            animation-timing-function: linear;
            animation-iteration-count: infinite;
            will-change: transform;
        }
        .fish i { display: block; }
        .fish-left i { transform: scaleX(-1); }
        .fish-a { top: 10%; font-size: 1.35rem; color: #14b8a6; animation: swimRightWave 19s linear infinite; animation-delay: -4s; }
        .fish-b { top: 22%; font-size: 2.05rem; color: #fb7185; animation: swimLeftWave 24s linear infinite; animation-delay: -13s; }
        .fish-c { top: 36%; font-size: 1.65rem; color: #0ea5a4; animation: swimRightDeep 27s linear infinite; animation-delay: -18s; }
        .fish-d { top: 52%; font-size: 1.25rem; color: #f59e0b; animation: swimLeftWave 17s linear infinite; animation-delay: -8s; }
        .fish-e { top: 69%; font-size: 1.75rem; color: #22c55e; animation: swimRightWave 23s linear infinite; animation-delay: -16s; }
        .fish-f { top: 82%; font-size: 1.15rem; color: #38bdf8; animation: swimLeftDeep 20s linear infinite; animation-delay: -5s; }
        .fish-g { top: 43%; font-size: 2.35rem; color: rgba(13,148,136,.52); animation: swimRightSlow 34s linear infinite; animation-delay: -25s; }
        .fish-h { top: 75%; font-size: 1rem; color: #f97316; animation: swimRightWave 15s linear infinite; animation-delay: -10s; }
        .fish-i { top: 16%; font-size: 1.05rem; color: #06b6d4; animation: swimLeftDeep 18s linear infinite; animation-delay: -11s; }
        .fish-j { top: 60%; font-size: 1.45rem; color: #2dd4bf; animation: swimRightDeep 29s linear infinite; animation-delay: -20s; }

        .fish-school {
            position: absolute;
            display: flex;
            align-items: center;
            gap: 9px;
            color: rgba(20,184,166,.68);
            filter: drop-shadow(0 7px 12px rgba(0,0,0,.08));
            animation: schoolAcross 31s linear infinite;
            will-change: transform;
        }
        .fish-school i:nth-child(1) { font-size: .78rem; }
        .fish-school i:nth-child(2) { font-size: 1.02rem; transform: translateY(-8px); }
        .fish-school i:nth-child(3) { font-size: .66rem; transform: translateY(7px); }
        .fish-school i:nth-child(4) { font-size: .88rem; transform: translateY(-2px); }
        .school-one { top: 28%; animation-delay: -21s; }
        .school-two { top: 88%; animation-duration: 25s; animation-delay: -9s; color: rgba(56,189,248,.62); }
        .school-two i { transform: scaleX(-1); }
        @keyframes swimRightWave {
            0%   { transform: translate3d(-14vw, 0, 0) rotate(-2deg); }
            25%  { transform: translate3d(20vw, -16px, 0) rotate(2deg); }
            55%  { transform: translate3d(58vw, 10px, 0) rotate(-1deg); }
            78%  { transform: translate3d(84vw, -12px, 0) rotate(2deg); }
            100% { transform: translate3d(114vw, 6px, 0) rotate(0deg); }
        }
        @keyframes swimLeftWave {
            0%   { transform: translate3d(114vw, 0, 0) rotate(2deg); }
            30%  { transform: translate3d(75vw, 14px, 0) rotate(-2deg); }
            58%  { transform: translate3d(42vw, -12px, 0) rotate(1deg); }
            100% { transform: translate3d(-14vw, 5px, 0) rotate(0deg); }
        }
        @keyframes swimRightDeep {
            0%   { transform: translate3d(-15vw, 18px, 0) scale(.86); opacity: .25; }
            16%  { opacity: .72; }
            45%  { transform: translate3d(42vw, -22px, 0) scale(1.03); }
            72%  { transform: translate3d(78vw, 18px, 0) scale(.95); }
            100% { transform: translate3d(115vw, -8px, 0) scale(.82); opacity: .20; }
        }
        @keyframes swimLeftDeep {
            0%   { transform: translate3d(115vw, -10px, 0) scale(.82); opacity: .24; }
            22%  { opacity: .72; }
            55%  { transform: translate3d(48vw, 20px, 0) scale(1.04); }
            100% { transform: translate3d(-15vw, -14px, 0) scale(.84); opacity: .20; }
        }
        @keyframes swimRightSlow {
            from { transform: translate3d(-18vw, 0, 0) scale(.86); }
            50%  { transform: translate3d(48vw, -26px, 0) scale(1.1); }
            to   { transform: translate3d(116vw, 12px, 0) scale(.90); }
        }
        @keyframes schoolAcross {
            0%   { transform: translate3d(-20vw, 0, 0); opacity: 0; }
            8%   { opacity: .82; }
            35%  { transform: translate3d(30vw, -15px, 0); }
            70%  { transform: translate3d(75vw, 10px, 0); }
            94%  { opacity: .78; }
            100% { transform: translate3d(120vw, -7px, 0); opacity: 0; }
        }
        .seaweed {
            position: absolute;
            bottom: -10px;
            width: 150px;
            height: 190px;
            opacity: .17;
            pointer-events: none;
        }
        .seaweed::before, .seaweed::after {
            content: '';
            position: absolute;
            bottom: 0;
            width: 24px;
            height: 150px;
            background: linear-gradient(180deg, rgba(0,137,123,.20), rgba(0,137,123,.55));
            border-radius: 999px 999px 0 0;
            transform-origin: bottom center;
        }
        .seaweed::before { left: 25px; transform: rotate(-12deg); }
        .seaweed::after { left: 72px; height: 178px; transform: rotate(10deg); }
        .seaweed.left { left: 2%; }
        .seaweed.right { right: 2%; transform: scaleX(-1); }

        .page-wrap {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
        }
        .login-stage {
            position: relative;
            width: min(1180px, 100%);
            border-radius: 34px;
            background: var(--stage-bg);
            border: 1px solid rgba(255,255,255,.7);
            box-shadow: 0 30px 90px rgba(0, 137, 123, .16);
            backdrop-filter: blur(18px);
            overflow: hidden;
        }
        .login-stage::before {
            content: '';
            position: absolute;
            inset: 0;
            background:
                radial-gradient(circle at 18% 18%, rgba(0,188,212,.18), transparent 30%),
                radial-gradient(circle at 75% 20%, rgba(255,82,82,.16), transparent 24%),
                linear-gradient(125deg, rgba(255,255,255,.12), rgba(255,255,255,.04));
            pointer-events: none;
        }
        .hero-side {
            position: relative;
            background: var(--hero-bg);
        }
        .hero-logo-card {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 156px;
            height: 156px;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(255,255,255,.96) 0%, rgba(255,248,239,.86) 72%);
            border: 2px solid rgba(0,188,212,.30);
            box-shadow: 0 26px 70px rgba(0,137,123,.20), inset 0 0 0 9px rgba(255,255,255,.58);
        }
        .hero-logo-card img { width: 138px; height: 138px; object-fit: contain; filter: drop-shadow(0 10px 18px rgba(0,0,0,.16)); }
        .hero-section-fishes .mini-fish {
            position: absolute;
            color: rgba(0,137,123,.75);
            animation: floatFish 7s ease-in-out infinite;
        }
        .hero-section-fishes .mini-fish:nth-child(1) { top: 10%; right: 18%; font-size: 1.45rem; animation-delay: 0s; }
        .hero-section-fishes .mini-fish:nth-child(2) { top: 30%; right: 8%; font-size: 1.75rem; animation-delay: 1.8s; color: rgba(255,82,82,.78); }
        .hero-section-fishes .mini-fish:nth-child(3) { bottom: 24%; left: 12%; font-size: 1.55rem; animation-delay: 3s; color: rgba(0,188,212,.78); }
        .hero-section-fishes .mini-fish:nth-child(4) { bottom: 14%; right: 22%; font-size: 1.2rem; animation-delay: 4.4s; color: rgba(255,179,0,.82); }
        @keyframes floatFish {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-14px) rotate(-3deg); }
        }
        .partner-card {
            display: inline-flex;
            align-items: center;
            gap: .85rem;
            padding: .7rem .95rem;
            border-radius: 20px;
            background: var(--card-bg);
            border: 1px solid var(--border-soft);
            box-shadow: 0 14px 40px rgba(0,0,0,.06);
        }
        .partner-card img {
            width: 42px;
            height: 52px;
            object-fit: contain;
        }
        .form-side {
            position: relative;
            background: var(--form-bg);
        }
        .form-panel {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 460px;
            margin: 0 auto;
        }
        .field-wrap input {
            transition: border-color .22s ease, box-shadow .22s ease, transform .18s ease;
            background: var(--card-strong);
        }
        .field-wrap input:focus {
            transform: translateY(-1px);
        }
        .field-wrap .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            z-index: 20;
            pointer-events: none;
            color: var(--muted);
            transition: color .18s ease, transform .18s ease;
        }
        .field-wrap .relative:focus-within .input-icon {
            color: #14b8a6;
            transform: translateY(-50%) scale(1.04);
        }
        .field-wrap input {
            position: relative;
            z-index: 10;
        }
        .field-wrap .password-toggle {
            z-index: 30;
        }
        .field-wrap input:-webkit-autofill,
        .field-wrap input:-webkit-autofill:hover,
        .field-wrap input:-webkit-autofill:focus {
            -webkit-text-fill-color: var(--ink) !important;
            -webkit-box-shadow: 0 0 0 1000px var(--card-strong) inset !important;
            box-shadow: 0 0 0 1000px var(--card-strong) inset !important;
            caret-color: var(--ink);
        }
        html[data-theme="dark"] .field-wrap input:-webkit-autofill,
        html[data-theme="dark"] .field-wrap input:-webkit-autofill:hover,
        html[data-theme="dark"] .field-wrap input:-webkit-autofill:focus {
            -webkit-text-fill-color: #f2fffd !important;
            -webkit-box-shadow: 0 0 0 1000px rgba(1,37,47,.96) inset !important;
            box-shadow: 0 0 0 1000px rgba(1,37,47,.96) inset !important;
            caret-color: #f2fffd;
        }
        .pill {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            padding: .45rem .8rem;
            border-radius: 999px;
            background: rgba(0,188,212,.12);
            border: 1px solid rgba(0,188,212,.16);
            color: #007d8e;
            font-weight: 700;
            font-size: .76rem;
        }

        .theme-toggle-ocean {
            position: fixed;
            top: 18px;
            right: 18px;
            z-index: 60;
            display: inline-flex;
            align-items: center;
            gap: .55rem;
            min-height: 44px;
            padding: 0 .9rem;
            border-radius: 999px;
            background: var(--theme-chip-bg);
            color: var(--theme-chip-text);
            border: 1px solid var(--border-soft);
            box-shadow: 0 14px 34px rgba(0,0,0,.10);
            backdrop-filter: blur(14px);
            font-size: .78rem;
            font-weight: 800;
            transition: transform .2s ease, background .2s ease, color .2s ease;
        }
        .theme-toggle-ocean:hover { transform: translateY(-1px); }
        html[data-theme="dark"] .text-slate-800,
        html[data-theme="dark"] .text-slate-700,
        html[data-theme="dark"] .text-slate-600,
        html[data-theme="dark"] .text-slate-500,
        html[data-theme="dark"] .text-slate-400,
        html[data-theme="dark"] .text-slate-900 { color: var(--ink) !important; }
        html[data-theme="dark"] p.text-slate-600,
        html[data-theme="dark"] p.text-slate-500,
        html[data-theme="dark"] .text-slate-500 { color: var(--muted) !important; }
        html[data-theme="dark"] .bg-white\/85,
        html[data-theme="dark"] .bg-white\/88,
        html[data-theme="dark"] .bg-foam { background-color: var(--card-bg) !important; }
        html[data-theme="dark"] .border-white,
        html[data-theme="dark"] .border-white\/90,
        html[data-theme="dark"] .border-slate-100,
        html[data-theme="dark"] .border-slate-200 { border-color: var(--border-soft) !important; }
        html[data-theme="dark"] input { background-color: rgba(1,37,47,.72) !important; border-color: rgba(138,243,235,.20) !important; color: #f2fffd !important; }
        html[data-theme="dark"] input::placeholder { color: rgba(214,255,250,.55) !important; }
        html[data-theme="dark"] .ocean-bg { opacity: .78; }
        @media (max-width: 520px) { .theme-toggle-ocean span { display: none; } .theme-toggle-ocean { width: 46px; justify-content: center; padding: 0; } }

        @media (prefers-reduced-motion: reduce) {
            .bubble, .fish, .fish-school, .mini-fish { animation-duration: .01ms !important; animation-iteration-count: 1 !important; }
        }
        @media (max-width: 1023px) {
            .fish-g, .fish-school.school-two { display: none; }
            .login-stage { border-radius: 28px; }
        }
        @media (max-width: 767px) {
            .page-wrap { padding: 18px 12px; }
            .login-stage { border-radius: 24px; }
            .hero-logo-card {
                width: 122px;
                height: 122px;
                border-radius: 999px;
            }
            .hero-logo-card img { width: 106px; height: 106px; }
        }
    </style>
    <link rel="stylesheet" href="assets/css/simada-theme.css?v=20260809-theme2">
</head>
<body>
    <button type="button" id="themeToggle" class="theme-toggle-ocean" onclick="toggleOceanTheme()" aria-label="Ubah mode siang malam"><i id="themeIcon" class="fa-solid fa-moon"></i><span id="themeText">Mode Malam</span></button>
    <div class="ocean-bg" aria-hidden="true">
        <div class="bubble"></div><div class="bubble"></div><div class="bubble"></div><div class="bubble"></div><div class="bubble"></div><div class="bubble"></div>
        <div class="bubble"></div><div class="bubble"></div><div class="bubble"></div><div class="bubble"></div><div class="bubble"></div><div class="bubble"></div>
        <div class="bubble"></div><div class="bubble"></div><div class="bubble"></div><div class="bubble"></div><div class="bubble"></div><div class="bubble"></div>
        <div class="fish fish-a"><i class="fa-solid fa-fish"></i></div>
        <div class="fish fish-b fish-left"><i class="fa-solid fa-fish-fins"></i></div>
        <div class="fish fish-c"><i class="fa-solid fa-fish"></i></div>
        <div class="fish fish-d fish-left"><i class="fa-solid fa-fish-fins"></i></div>
        <div class="fish fish-e"><i class="fa-solid fa-fish"></i></div>
        <div class="fish fish-f fish-left"><i class="fa-solid fa-fish"></i></div>
        <div class="fish fish-g"><i class="fa-solid fa-fish-fins"></i></div>
        <div class="fish fish-h"><i class="fa-solid fa-fish"></i></div>
        <div class="fish fish-i fish-left"><i class="fa-solid fa-fish"></i></div>
        <div class="fish fish-j"><i class="fa-solid fa-fish-fins"></i></div>
        <div class="fish-school school-one"><i class="fa-solid fa-fish"></i><i class="fa-solid fa-fish"></i><i class="fa-solid fa-fish"></i><i class="fa-solid fa-fish"></i></div>
        <div class="fish-school school-two"><i class="fa-solid fa-fish"></i><i class="fa-solid fa-fish"></i><i class="fa-solid fa-fish"></i><i class="fa-solid fa-fish"></i></div>
        <div class="seaweed left"></div>
        <div class="seaweed right"></div>
    </div>

    <main class="page-wrap">
        <section class="login-stage grid lg:grid-cols-[1.08fr_.92fr]">
            <div class="hero-side p-7 sm:p-10 lg:p-12">
                <div class="hero-section-fishes absolute inset-0 pointer-events-none">
                    <i class="mini-fish fa-solid fa-fish"></i>
                    <i class="mini-fish fa-solid fa-fish"></i>
                    <i class="mini-fish fa-solid fa-fish"></i>
                    <i class="mini-fish fa-solid fa-fish"></i>
                </div>

                <div class="relative z-10 flex flex-col h-full">
                    <div class="flex flex-wrap items-center gap-4 justify-between">
                        <div class="hero-logo-card">
                            <img src="assets/picture/logo_uptd_clear.png" alt="Logo Unit UPTD SPAPAL Muara Badak-Sebulu">
                        </div>
                        <div class="partner-card">
                            <img src="assets/picture/logo_mitra_clear.png" alt="Logo Mitra Kalimantan Timur">
                            <div>
                                <p class="text-sm font-bold text-slate-700 leading-tight">Pemerintah Provinsi Kalimantan Timur</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8 lg:mt-10">
                        <span class="pill"><i class="fa-solid fa-water"></i> Nuansa perikanan modern</span>
                        <h1 class="mt-4 text-3xl sm:text-4xl xl:text-[2.85rem] font-black leading-tight text-slate-800">SIMADA</h1>
                        <p class="mt-2 text-base sm:text-lg font-bold text-reef">Sistem Informasi Manajemen Data</p>
                        <p class="mt-4 text-sm sm:text-base text-slate-600 leading-relaxed max-w-xl">
                            Pengelolaan dokumen UPTD Sentral Pembenihan Air Payau dan Air Laut Muara Badak-Sebulu dalam satu sistem yang lebih rapi, cepat, dan mudah diakses.
                        </p>
                    </div>

                    <div class="mt-8">
                        <div class="rounded-[26px] bg-white/85 border border-white/90 shadow-soft p-5 max-w-xl">
                            <div class="flex items-start gap-4">
                                <div class="w-11 h-11 rounded-2xl bg-sea/15 text-sea flex items-center justify-center shrink-0">
                                    <i class="fa-solid fa-folder-tree"></i>
                                </div>
                                <div>
                                    <h3 class="font-extrabold text-slate-800">Arsip Tertata</h3>
                                    <p class="text-sm text-slate-600 mt-1.5 leading-relaxed">Dokumen tersimpan lebih rapi dengan proses unggah, verifikasi, dan pencarian yang lebih terarah.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-auto pt-7 text-[11px] sm:text-xs text-slate-500 font-semibold uppercase tracking-[.08em] leading-relaxed">
                        UPTD Sentral Pembenihan Air Payau dan Air Laut Muara Badak-Sebulu (SPAPAL)
                    </div>
                </div>
            </div>

            <div class="form-side p-7 sm:p-10 lg:p-12 flex items-center">
                <div class="form-panel">
                    <div class="rounded-[30px] bg-white/88 border border-white shadow-ocean p-6 sm:p-8">
                        <div class="mb-6">
                            <span class="pill !bg-coral/10 !border-coral/15 !text-coral"><i class="fa-solid fa-right-to-bracket"></i> Portal Masuk</span>
                            <h2 class="mt-4 text-2xl sm:text-[2rem] font-black text-slate-800 leading-tight">Selamat datang kembali</h2>
                            <p class="mt-2 text-sm sm:text-base text-slate-600">Masuk ke SIMADA untuk mengelola dokumen, melihat aktivitas, dan memantau arsip dengan mudah.</p>
                        </div>

                        <?php if ($errorMsg): ?>
                            <div class="mb-5 p-4 text-sm bg-red-50 text-red-700 border border-red-200 rounded-2xl">
                                <i class="fa-solid fa-circle-exclamation mr-1"></i> <?= e($errorMsg) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="space-y-5">
                            <?= appCsrfInput(); ?>
                            <div class="field-wrap">
                                <label class="block text-sm font-bold mb-2 text-slate-700">Nama Pengguna</label>
                                <div class="relative">
                                    <i class="input-icon fa-regular fa-user"></i>
                                    <input type="text" name="username" value="<?= e($oldUsername) ?>" class="pl-11 pr-4 min-h-[54px] w-full rounded-2xl border border-slate-200 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-sea/15 focus:border-sea" placeholder="Masukkan nama pengguna" autocomplete="username" required>
                                </div>
                            </div>

                            <div class="field-wrap">
                                <label class="block text-sm font-bold mb-2 text-slate-700">Kata Sandi</label>
                                <div class="relative">
                                    <i class="input-icon fa-solid fa-lock"></i>
                                    <input type="password" id="loginPassword" name="password" placeholder="Masukkan kata sandi" class="pl-11 pr-12 min-h-[54px] w-full rounded-2xl border border-slate-200 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-4 focus:ring-sea/15 focus:border-sea" autocomplete="current-password" required>
                                    <button type="button" onclick="togglePasswordField('loginPassword', this)" class="password-toggle absolute right-3 top-1/2 -translate-y-1/2 w-10 h-10 rounded-xl text-slate-400 hover:text-reef hover:bg-sea/10 transition" aria-label="Lihat password"><i class="fa-regular fa-eye"></i></button>
                                </div>
                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2 mt-2.5">
                                    <p class="text-[11px] leading-relaxed text-slate-500 max-w-[290px]">Minimal 8 karakter agar akun lebih aman.</p>
                                    <a href="<?= e($waLink) ?>" target="_blank" rel="noopener" class="text-xs font-semibold text-reef hover:underline whitespace-nowrap">Lupa kata sandi?</a>
                                </div>
                            </div>

                            <button type="submit" class="w-full min-h-[54px] bg-gradient-to-r from-reef to-sea text-white text-sm font-extrabold rounded-2xl hover:opacity-95 active:scale-[.99] transition shadow-soft">
                                Masuk ke SIMADA <i class="fa-solid fa-arrow-right ml-2"></i>
                            </button>
                        </form>

                        <div class="mt-6 pt-6 border-t border-slate-100 grid grid-cols-3 gap-3 text-center">
                            <div class="rounded-2xl bg-foam px-3 py-3">
                                <div class="text-coral text-lg"><i class="fa-solid fa-fish"></i></div>
                                <p class="text-[11px] font-bold text-slate-500 mt-1">Perikanan</p>
                            </div>
                            <div class="rounded-2xl bg-foam px-3 py-3">
                                <div class="text-sea text-lg"><i class="fa-solid fa-file-shield"></i></div>
                                <p class="text-[11px] font-bold text-slate-500 mt-1">Dokumen</p>
                            </div>
                            <div class="rounded-2xl bg-foam px-3 py-3">
                                <div class="text-reef text-lg"><i class="fa-solid fa-bell"></i></div>
                                <p class="text-[11px] font-bold text-slate-500 mt-1">Aktivitas</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

<script>
function syncOceanThemeIcon() {
    const dark = document.documentElement.getAttribute('data-theme') === 'dark';
    const icon = document.getElementById('themeIcon');
    const text = document.getElementById('themeText');
    if (icon) icon.className = dark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
    if (text) text.textContent = dark ? 'Mode Siang' : 'Mode Malam';
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute('content', dark ? '#071416' : '#0F9D94');
}
function toggleOceanTheme() {
    const dark = document.documentElement.getAttribute('data-theme') !== 'dark';
    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
    localStorage.setItem('simada-theme', dark ? 'dark' : 'light');
    syncOceanThemeIcon();
}
function togglePasswordField(inputId, button) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    const icon = button.querySelector('i');
    if (icon) icon.className = show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
    button.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Lihat password');
}

document.addEventListener('DOMContentLoaded', function () {
    syncOceanThemeIcon();
    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function () {
            const btn = form.querySelector('[type="submit"]');
            if (!btn || btn.disabled) return;
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Memproses...';
            setTimeout(function () { btn.disabled = false; btn.innerHTML = original; }, 8000);
        });
    });
});
</script>
</body>
</html>
