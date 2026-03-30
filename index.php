<?php
session_start();

/* Jika sudah login */
if (isset($_SESSION['role'])) {

    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
    } else {
        header('Location: user/dashboard.php');
    }

    exit();
}

$error_message = '';

/* Proses login */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error_message = "Username dan password wajib diisi!";
    } else {

        // Dummy user (sementara)
        $users = [
            'admin' => 'admin123',
            'user'  => 'user123'
        ];

        if (isset($users[$username]) && $users[$username] === $password) {

            session_regenerate_id(true);

            $_SESSION['username'] = $username;
            $_SESSION['role'] = ($username === 'admin') ? 'admin' : 'user';

            // Redirect sesuai role
            if ($_SESSION['role'] === 'admin') {
                header('Location: admin/dashboard.php');
            } else {
                header('Location: user/dashboard.php');
            }

            exit();

        } else {
            $error_message = "Username atau password salah!";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SIMADA</title>

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Font Awesome -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>

<body class="bg-gradient-to-br from-[#5BB4B1] to-[#4a9d9a] flex items-center justify-center min-h-screen">

<div class="w-full max-w-md bg-white p-10 rounded-2xl shadow-2xl">

    <!-- Logo -->
    <div class="text-center mb-8">
        <img src="assets/picture/logo.png" class="w-14 mx-auto mb-4" alt="SIMADA">
        <h1 class="text-2xl font-semibold text-gray-800">SIMADA</h1>
        <p class="text-sm text-gray-600">Sistem Manajemen Data</p>
    </div>

    <!-- Error -->
    <?php if ($error_message): ?>
        <div class="mb-4 p-3 bg-red-100 text-red-700 border border-red-300 rounded-lg">
            <?= htmlspecialchars($error_message); ?>
        </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" class="space-y-5">

        <!-- Username -->
        <div>
            <label class="block text-sm mb-2 text-gray-700">Nama Pengguna</label>
            <div class="relative">
                <i class="fa-regular fa-user absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input
                    type="text"
                    name="username"
                    class="pl-10 h-12 w-full rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:border-[#5BB4B1]"
                    placeholder="Masukkan nama pengguna"
                    required>
            </div>
        </div>

        <!-- Password -->
        <div>
            <label class="block text-sm mb-2 text-gray-700">Kata Sandi</label>
            <div class="relative">
                <i class="fa-solid fa-lock absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input
                    type="password"
                    name="password"
                    placeholder="Masukkan kata sandi"
                    class="pl-10 h-12 w-full rounded-lg border border-gray-300 bg-gray-50 focus:outline-none focus:border-[#5BB4B1]"
                    required>
            </div>

            <div class="text-right mt-2">
                <a href="#" class="text-sm text-[#5BB4B1] hover:underline">
                    Lupa kata sandi?
                </a>
            </div>
        </div>

        <!-- Button -->
        <button
            type="submit"
            class="w-full h-12 bg-[#5BB4B1] text-white rounded-lg hover:bg-[#4a9d9a] transition">
            Masuk
        </button>

    </form>

</div>

</body>
</html>
