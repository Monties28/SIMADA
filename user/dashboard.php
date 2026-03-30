<?php
session_start();

/* Cek login dan role */
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'user') {
    header("Location: ../index.php");
    exit();
}

$username = htmlspecialchars($_SESSION['username']);
$role     = htmlspecialchars($_SESSION['role']);

$page = $_GET['page'] ?? 'dashboard';

$allowed_pages = ['dashboard', 'upload', 'pustaka', 'pengaturan'];

if (!in_array($page, $allowed_pages)) {
    $page = 'dashboard';
}
?>


<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>SIMADA Dashboard</title>

<script src="https://cdn.tailwindcss.com"></script>

<script>
tailwind.config = {
    theme: {
        extend: {
            colors: {
                primary: '#5BB4B1',
                primaryDark: '#4aa3a0',
                primarySoft: '#edf7f7'
            }
        }
    }
}
</script>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-primarySoft">

<!-- SIDEBAR -->
<aside class="fixed left-0 top-0 w-64 h-screen bg-primary text-white flex flex-col">

    <div class="p-5 border-b border-white/20">
        <h1 class="font-bold text-lg">SIMADA</h1>
        <p class="text-xs opacity-80">Sistem Manajemen Data</p>
    </div>

    <div class="p-4 border-b border-white/20 text-sm">
        <p class="font-semibold"><?= $username ?></p>
        <p class="opacity-80 capitalize"><?= $role ?></p>
    </div>

    <nav class="flex-1 p-4 space-y-2 text-sm">

        <a href="dashboard.php?page=dashboard"
           class="block px-4 py-2 rounded-lg <?= $page=='dashboard'?'bg-white text-primary font-semibold':'hover:bg-primaryDark' ?>">
            <i class="fa-solid fa-gauge"></i> Dashboard
        </a>

        <a href="dashboard.php?page=upload"
           class="block px-4 py-2 rounded-lg <?= $page=='upload'?'bg-white text-primary font-semibold':'hover:bg-primaryDark' ?>">
            <i class="fa-solid fa-cloud-arrow-up"></i> Upload
        </a>

        <a href="dashboard.php?page=pustaka"
           class="block px-4 py-2 rounded-lg <?= $page=='pustaka'?'bg-white text-primary font-semibold':'hover:bg-primaryDark' ?>">
            <i class="fa-solid fa-folder"></i> Pustaka
        </a>

        <a href="dashboard.php?page=pengaturan"
           class="block px-4 py-2 rounded-lg <?= $page=='pengaturan'?'bg-white text-primary font-semibold':'hover:bg-primaryDark' ?>">
            <i class="fa-solid fa-gear"></i> Pengaturan
        </a>

    </nav>

    <a href="../logout.php" class="p-4 text-center bg-primaryDark hover:bg-teal-700">
        <i class="fa-solid fa-right-from-bracket"></i> Keluar
    </a>

</aside>

<!-- MAIN -->
<div class="ml-64 min-h-screen flex flex-col">

    <!-- HEADER -->
    <header class="bg-white px-8 py-4 shadow-sm">
        <h2 class="font-semibold text-gray-700 capitalize">
            <?= ucfirst($page) ?>
        </h2>
    </header>

    <!-- CONTENT -->
    <main class="p-8 flex-1">

        <?php
        include __DIR__ . "/pages/$page.php";
        ?>

    </main>

</div>

</body>
</html>
