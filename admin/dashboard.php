<?php
session_start();

/* ─── Cek login dan role admin ──────────────────────────────────────────── */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

$username = htmlspecialchars($_SESSION['username']);
$role     = htmlspecialchars($_SESSION['role']);

/* ─── Routing ───────────────────────────────────────────────────────────── */
$page = $_GET['page'] ?? 'dashboard';

$page_map = [
    'dashboard' => __DIR__ . '/pages/dashboard.php',
    'pustaka'   => __DIR__ . '/pages/pustaka.php',
    'users'     => __DIR__ . '/pages/users.php',
    'backup'    => __DIR__ . '/pages/backup.php',
    'database'  => __DIR__ . '/pages/database.php',
];

if (!array_key_exists($page, $page_map)) {
    $page = 'dashboard';
}

$page_titles = [
    'dashboard' => 'Dashboard Admin',
    'pustaka'   => 'Pustaka',
    'users'     => 'Manajemen User',
    'backup'    => 'Backup Data',
    'database'  => 'Database',
];

$page_title = $page_titles[$page] ?? 'Dashboard Admin';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIMADA Admin | <?= $page_title ?></title>

    <script>
        window.tailwind = window.tailwind || {};
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary:     '#5BB4B1',
                        primaryDark: '#4aa3a0',
                        primarySoft: '#edf7f7'
                    }
                }
            }
        }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-gray-50">

<!-- ═══════════════════════════════════════════════════════════════════════
     SIDEBAR ADMIN (warna teal seperti screenshot)
════════════════════════════════════════════════════════════════════════ -->
<aside class="fixed left-0 top-0 w-64 h-screen bg-gradient-to-b from-teal-400 to-teal-500 text-white flex flex-col shadow-xl">

    <!-- Logo -->
    <div class="p-6 flex items-center gap-3">
        <img src="../assets/picture/logo.png"
             onerror="this.src='https://upload.wikimedia.org/wikipedia/commons/thumb/9/9a/Garuda_Pancasila.svg/120px-Garuda_Pancasila.svg.png'"
             class="w-12 h-12" alt="logo">
        <div>
            <h1 class="font-bold text-lg">SIMADA</h1>
            <p class="text-xs opacity-90">Sistem Manajemen Data</p>
        </div>
    </div>

    <!-- Info User -->
    <div class="px-6 py-4 bg-white/10 backdrop-blur-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-white text-teal-500 flex items-center justify-center font-bold text-sm">
                <?= strtoupper(substr($username, 0, 1)) ?>
            </div>
            <div>
                <p class="text-sm font-semibold"><?= $username ?></p>
                <p class="text-xs opacity-80">Administrator</p>
            </div>
        </div>
    </div>

    <!-- Menu Navigasi -->
    <nav class="flex-1 px-4 py-6 space-y-2 text-sm">

        <a href="dashboard.php?page=dashboard"
           class="flex items-center gap-3 px-4 py-3 rounded-lg transition
                  <?= $page === 'dashboard' ? 'bg-white text-teal-600 font-semibold shadow-md' : 'hover:bg-white/10' ?>">
            <i class="fa-solid fa-th-large w-4"></i> Dashboard
        </a>

        <a href="dashboard.php?page=pustaka"
           class="flex items-center gap-3 px-4 py-3 rounded-lg transition
                  <?= $page === 'pustaka' ? 'bg-white text-teal-600 font-semibold shadow-md' : 'hover:bg-white/10' ?>">
            <i class="fa-solid fa-archive w-4"></i> Pustaka
        </a>

        <a href="dashboard.php?page=users"
           class="flex items-center gap-3 px-4 py-3 rounded-lg transition
                  <?= $page === 'users' ? 'bg-white text-teal-600 font-semibold shadow-md' : 'hover:bg-white/10' ?>">
            <i class="fa-solid fa-users w-4"></i> Manajemen User
        </a>

        <a href="dashboard.php?page=backup"
           class="flex items-center gap-3 px-4 py-3 rounded-lg transition
                  <?= $page === 'backup' ? 'bg-white text-teal-600 font-semibold shadow-md' : 'hover:bg-white/10' ?>">
            <i class="fa-solid fa-database w-4"></i> Backup Data
        </a>

        <a href="dashboard.php?page=database"
           class="flex items-center gap-3 px-4 py-3 rounded-lg transition
                  <?= $page === 'database' ? 'bg-white text-teal-600 font-semibold shadow-md' : 'hover:bg-white/10' ?>">
            <i class="fa-solid fa-server w-4"></i> Database
        </a>

    </nav>

    <!-- Tombol Keluar -->
    <a href="../logout.php"
       class="mx-4 mb-6 px-4 py-3 text-center text-sm bg-white/10 hover:bg-white/20 rounded-lg transition flex items-center justify-center gap-2">
        <i class="fa-solid fa-right-from-bracket"></i> Keluar
    </a>

</aside>

<!-- ═══════════════════════════════════════════════════════════════════════
     MAIN AREA
════════════════════════════════════════════════════════════════════════ -->
<div class="ml-64 min-h-screen flex flex-col">

    <!-- HEADER (seperti screenshot - dengan logo & notifikasi) -->
    <header class="bg-white px-8 py-4 shadow-sm border-b border-gray-100">
        <div class="flex items-center justify-between">
            <!-- Logo & Title -->
            <div class="flex items-center gap-3">
                <img src="../assets/picture/logo.png"
                     onerror="this.src='https://upload.wikimedia.org/wikipedia/commons/thumb/9/9a/Garuda_Pancasila.svg/120px-Garuda_Pancasila.svg.png'"
                     class="w-10 h-10" alt="logo">
                <div>
                    <h2 class="font-semibold text-gray-800">SIMADA</h2>
                    <p class="text-xs text-gray-400">Sistem Manajemen Data</p>
                </div>
            </div>

            <!-- Right side: Notification + User -->
            <div class="flex items-center gap-5">
                <!-- Notifikasi dengan red dot -->
                <div class="relative cursor-pointer">
                    <i class="fa-regular fa-bell text-gray-400 hover:text-gray-600 text-lg"></i>
                    <span class="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                </div>
                
                <!-- User Info -->
                <div class="flex items-center gap-2 text-sm">
                    <i class="fa-regular fa-user text-gray-400"></i>
                    <span class="text-gray-700"><?= $username ?></span>
                </div>
            </div>
        </div>
    </header>

    <!-- CONTENT -->
    <main class="p-8 flex-1 bg-gray-50">
        <?php
        $file = $page_map[$page];
        if (file_exists($file)) {
            include $file;
        } else {
            echo '<div class="bg-yellow-50 border border-yellow-300 text-yellow-700 rounded-xl p-6 text-sm">';
            echo '<i class="fa-solid fa-triangle-exclamation mr-2"></i>';
            echo 'Halaman <strong>' . htmlspecialchars($page) . '</strong> belum tersedia.';
            echo '</div>';
        }
        ?>
    </main>

</div>

</body>
</html>