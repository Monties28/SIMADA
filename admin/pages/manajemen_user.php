<?php
/* =========================================================
   MANAJEMEN USER ADMIN
   Fungsi: Mengelola penambahan user, status aktif atau nonaktif, reset password, pencarian, dan pagination.
========================================================= */

declare(strict_types=1);
/*
 * admin/pages/manajemen_user.php
 * - Data ambil dari database
 * - Tambah user aktif
 * - Tidak ada hapus, hanya aktif/nonaktif
 * - Reset password user
 * - Pagination 10 data per halaman
 * - Search aktif
 */

$successMsg = '';
$errorMsg   = '';

/* =========================
   KONEKSI DATABASE
========================= */
$db = appDb();

if (!$db) {
    echo "<div class='bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm'>
            Koneksi database tidak tersedia.
          </div>";
    return;
}

$currentAdminId = (int) ($_SESSION['user_id'] ?? 0);

/* =========================
   HELPER REDIRECT
========================= */
/* =========================================================
   BAGIAN FUNGSI PENDUKUNG
   Bagian ini mengatur redirect dan parameter filter halaman manajemen user.
========================================================= */
function redirectManageUserPage(string $msg = '', string $q = '', int $pageNum = 1): void
{
    $params = [
        'page' => 'manajemen_user',
        'page_num' => max(1, $pageNum)
    ];

    if ($q !== '') {
        $params['q'] = $q;
    }

    if ($msg !== '') {
        $params['msg'] = $msg;
    }

    $url = 'main.php?' . http_build_query($params);

    if (!headers_sent()) {
        header('Location: ' . $url);
    } else {
        $safeUrl = e($url);
        echo "<script>window.location.replace('{$safeUrl}');</script>";
        echo "<noscript><meta http-equiv='refresh' content='0;url={$safeUrl}'></noscript>";
    }
    exit();
}

/* =========================
   PARAMETER
========================= */
$q = trim($_GET['q'] ?? '');
$pageNum = max(1, (int) ($_GET['page_num'] ?? 1));
$perPage = 10;

/* =========================
   PROSES TAMBAH USER
========================= */
/* =========================================================
   BAGIAN PROSES USER
   Bagian ini memproses tambah user, ubah status aktif, dan reset password.
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_user') {
    $username  = trim($_POST['username'] ?? '');
    $nip       = trim($_POST['nip'] ?? '');
    $name      = trim($_POST['name'] ?? '');
    $jabatan   = trim($_POST['jabatan'] ?? '');
    $role      = trim($_POST['role'] ?? 'user');
    $password  = trim($_POST['password'] ?? '');
    $isActive = isset($_POST['is_active']) ? 1 : 0;


    if ($username === '' || $name === '' || $jabatan === '' || $password === '') {
        redirectManageUserPage('add_required', $q, $pageNum);
    }

    if (!in_array($role, ['admin', 'user'], true)) {
        redirectManageUserPage('invalid_role', $q, $pageNum);
    }

    if (!appIsStrongPassword($password)) {
        redirectManageUserPage('password_weak', $q, $pageNum);
    }

    $stmtCheck = $db->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
    if (!$stmtCheck) {
        redirectManageUserPage('add_failed', $q, $pageNum);
    }

    $stmtCheck->bind_param("s", $username);
    $stmtCheck->execute();
    $stmtCheck->store_result();

    if ($stmtCheck->num_rows > 0) {
        $stmtCheck->close();
        redirectManageUserPage('username_exists', $q, $pageNum);
    }
    $stmtCheck->close();

    $stmtInsert = $db->prepare("
        INSERT INTO users (username, nip, password, name, jabatan, role, is_active, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    if (!$stmtInsert) {
        redirectManageUserPage('add_failed', $q, $pageNum);
    }

    $passwordHash = hashAppPassword($password);

    $stmtInsert->bind_param(
        "ssssssi",
        $username,
        $nip,
        $passwordHash,
        $name,
        $jabatan,
        $role,
        $isActive
    );

    if ($stmtInsert->execute()) {
        $stmtInsert->close();
        redirectManageUserPage('add_success', $q, 1);
    }

    $stmtInsert->close();
    redirectManageUserPage('add_failed', $q, $pageNum);
}

/* =========================
   PROSES AKTIF / NONAKTIF
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    $targetId  = (int) ($_POST['user_id'] ?? 0);
    $newStatus = (int) ($_POST['new_status'] ?? 0);
    $qPost     = trim($_POST['q'] ?? '');
    $pagePost  = max(1, (int) ($_POST['page_num'] ?? 1));


    if ($targetId <= 0) {
        redirectManageUserPage('invalid_user', $qPost, $pagePost);
    }

    if ($targetId === $currentAdminId) {
        redirectManageUserPage('cannot_disable_self', $qPost, $pagePost);
    }

    if (!in_array($newStatus, [0, 1], true)) {
        redirectManageUserPage('invalid_status', $qPost, $pagePost);
    }

    $stmt = $db->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    if (!$stmt) {
        redirectManageUserPage('update_failed', $qPost, $pagePost);
    }

    $stmt->bind_param("ii", $newStatus, $targetId);
    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        redirectManageUserPage($newStatus === 1 ? 'activated' : 'deactivated', $qPost, $pagePost);
    }

    redirectManageUserPage('update_failed', $qPost, $pagePost);
}


/* =========================
   PROSES RESET PASSWORD
========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_password') {
    $targetId        = (int) ($_POST['user_id'] ?? 0);
    $newPassword     = trim($_POST['new_password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    $qPost           = trim($_POST['q'] ?? '');
    $pagePost        = max(1, (int) ($_POST['page_num'] ?? 1));

    if ($targetId <= 0) {
        redirectManageUserPage('invalid_user', $qPost, $pagePost);
    }

    if ($newPassword === '' || $confirmPassword === '') {
        redirectManageUserPage('reset_required', $qPost, $pagePost);
    }

    if (!appIsStrongPassword($newPassword)) {
        redirectManageUserPage('password_weak', $qPost, $pagePost);
    }

    if ($newPassword !== $confirmPassword) {
        redirectManageUserPage('password_not_match', $qPost, $pagePost);
    }

    $passwordHash = hashAppPassword($newPassword);
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ? LIMIT 1");
    if (!$stmt) {
        redirectManageUserPage('reset_failed', $qPost, $pagePost);
    }

    $stmt->bind_param("si", $passwordHash, $targetId);
    $ok = $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($ok && $affected >= 0) {
        redirectManageUserPage('reset_success', $qPost, $pagePost);
    }

    redirectManageUserPage('reset_failed', $qPost, $pagePost);
}

/* =========================
   FLASH MESSAGE
========================= */
$msg = $_GET['msg'] ?? '';

switch ($msg) {
    case 'add_success':
        $successMsg = 'User berhasil ditambahkan.';
        break;
    case 'activated':
        $successMsg = 'User berhasil diaktifkan.';
        break;
    case 'deactivated':
        $successMsg = 'User berhasil dinonaktifkan.';
        break;
    case 'reset_success':
        $successMsg = 'Password user berhasil direset.';
        break;
    case 'cannot_disable_self':
        $errorMsg = 'Akun admin yang sedang login tidak bisa dinonaktifkan.';
        break;
    case 'invalid_user':
        $errorMsg = 'User tidak valid.';
        break;
    case 'invalid_status':
        $errorMsg = 'Status tidak valid.';
        break;
    case 'username_exists':
        $errorMsg = 'Username sudah digunakan.';
        break;
    case 'invalid_role':
        $errorMsg = 'Role tidak valid.';
        break;
    case 'password_short':
    case 'password_weak':
        $errorMsg = appPasswordPolicyDescription();
        break;
    case 'reset_required':
        $errorMsg = 'Password baru dan konfirmasi password wajib diisi.';
        break;
    case 'password_not_match':
        $errorMsg = 'Konfirmasi password tidak sama.';
        break;
    case 'reset_failed':
        $errorMsg = 'Gagal mereset password user.';
        break;
    case 'add_required':
        $errorMsg = 'Username, nama lengkap, jabatan, dan password wajib diisi.';
        break;
    case 'add_failed':
        $errorMsg = 'Gagal menambahkan user.';
        break;
    case 'update_failed':
        $errorMsg = 'Gagal mengubah status user.';
        break;
}

/* =========================
   RINGKASAN
========================= */
$totalUsers = 0;
$totalAdmin = 0;
$activeUsers = 0;
$inactiveUsers = 0;

$resultCount = $db->query("
    SELECT
        COUNT(*) AS total_users,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) AS total_admin,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active_users,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) AS inactive_users
    FROM users
");

if ($resultCount && $rowCount = $resultCount->fetch_assoc()) {
    $totalUsers    = (int) ($rowCount['total_users'] ?? 0);
    $totalAdmin    = (int) ($rowCount['total_admin'] ?? 0);
    $activeUsers   = (int) ($rowCount['active_users'] ?? 0);
    $inactiveUsers = (int) ($rowCount['inactive_users'] ?? 0);
}

/* =========================
   COUNT UNTUK PAGINATION
========================= */
$totalFiltered = 0;

if ($q !== '') {
    $searchLike = '%' . $q . '%';

    $stmtCount = $db->prepare("
        SELECT COUNT(*)
        FROM users
        WHERE username LIKE ?
           OR name LIKE ?
           OR jabatan LIKE ?
           OR nip LIKE ?
    ");

    if ($stmtCount) {
        $stmtCount->bind_param("ssss", $searchLike, $searchLike, $searchLike, $searchLike);
        $stmtCount->execute();
        $stmtCount->bind_result($totalFiltered);
        $stmtCount->fetch();
        $stmtCount->close();
    }
} else {
    $resultTotalFiltered = $db->query("SELECT COUNT(*) FROM users");
    if ($resultTotalFiltered && $row = $resultTotalFiltered->fetch_row()) {
        $totalFiltered = (int) $row[0];
    }
}

$totalPages = max(1, (int) ceil($totalFiltered / $perPage));

if ($pageNum > $totalPages) {
    $pageNum = $totalPages;
}

$offset = ($pageNum - 1) * $perPage;

/* =========================
   DATA USER
========================= */
$users = [];

if ($q !== '') {
    $searchLike = '%' . $q . '%';

    $stmt = $db->prepare("
        SELECT id, username, nip, name, jabatan, role, is_active, created_at
        FROM users
        WHERE username LIKE ?
           OR name LIKE ?
           OR jabatan LIKE ?
           OR nip LIKE ?
        ORDER BY id ASC
        LIMIT ?, ?
    ");

    if ($stmt) {
        $stmt->bind_param("ssssii", $searchLike, $searchLike, $searchLike, $searchLike, $offset, $perPage);
        $stmt->execute();
        $resultUsers = $stmt->get_result();

        while ($row = $resultUsers->fetch_assoc()) {
            $users[] = $row;
        }

        $stmt->close();
    }
} else {
    $stmt = $db->prepare("
        SELECT id, username, nip, name, jabatan, role, is_active, created_at
        FROM users
        ORDER BY id ASC
        LIMIT ?, ?
    ");

    if ($stmt) {
        $stmt->bind_param("ii", $offset, $perPage);
        $stmt->execute();
        $resultUsers = $stmt->get_result();

        while ($row = $resultUsers->fetch_assoc()) {
            $users[] = $row;
        }

        $stmt->close();
    }
}

$startData = $totalFiltered > 0 ? $offset + 1 : 0;
$endData   = min($offset + $perPage, $totalFiltered);
/* =========================================================
   BAGIAN TAMPILAN
   Bagian setelah tanda penutup PHP berisi HTML, CSS, atau JavaScript
   yang membentuk tampilan halaman untuk pengguna.
========================================================= */
?>

<div class="space-y-6">

    <?php if ($successMsg): ?>
        <div class="p-4 rounded-xl border border-green-200 bg-green-50 text-green-700 text-sm">
            <i class="fa-solid fa-circle-check mr-1"></i> <?= e($successMsg) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="p-4 rounded-xl border border-red-200 bg-red-50 text-red-700 text-sm">
            <i class="fa-solid fa-circle-exclamation mr-1"></i> <?= e($errorMsg) ?>
        </div>
    <?php endif; ?>

    <!-- HEADER PAGE -->
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-semibold text-slate-800">Manajemen User</h2>
            <p class="text-sm text-slate-500 mt-1">Kelola pengguna dan status akun sistem</p>
        </div>

        <button type="button"
            onclick="openAddUserModal()"
            class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-white text-sm rounded-lg hover:bg-primaryDark transition shadow-sm">
            <i class="fa-solid fa-user-plus text-xs"></i>
            <span>Tambah User</span>
        </button>
    </div>

    <!-- STAT CARDS -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 mb-1">Total User</p>
                    <p class="text-4xl font-semibold text-slate-800"><?= $totalUsers ?></p>
                </div>
                <div class="text-teal-500 text-3xl">
                    <i class="fa-regular fa-user"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 mb-1">Admin</p>
                    <p class="text-4xl font-semibold text-slate-800"><?= $totalAdmin ?></p>
                </div>
                <div class="text-indigo-500 text-3xl">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 mb-1">User Aktif</p>
                    <p class="text-4xl font-semibold text-slate-800"><?= $activeUsers ?></p>
                </div>
                <div class="text-green-500 text-3xl">
                    <i class="fa-regular fa-circle-check"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500 mb-1">User Nonaktif</p>
                    <p class="text-4xl font-semibold text-slate-800"><?= $inactiveUsers ?></p>
                </div>
                <div class="text-red-500 text-3xl">
                    <i class="fa-regular fa-circle-xmark"></i>
                </div>
            </div>
        </div>

    </div>

    <!-- TABLE PANEL -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6">

        <div class="mb-5">
            <h3 class="text-xl font-medium text-slate-800">Daftar User</h3>
        </div>

        <!-- Search -->
        <form method="GET" action="main.php" class="mb-5">
            <input type="hidden" name="page" value="manajemen_user">
            <div class="relative max-w-sm">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input
                    type="text"
                    name="q"
                    value="<?= e($q) ?>"
                    placeholder="Cari username, nama, jabatan, atau nip..."
                    class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:border-primary"
                >
            </div>
        </form>

        <!-- Info jumlah -->
        <div class="mb-4 text-sm text-slate-500">
            Menampilkan <?= $startData ?> - <?= $endData ?> dari <?= $totalFiltered ?> data
        </div>

        <!-- Table -->
        <div class="overflow-x-auto border border-slate-200 rounded-xl">
            <table class="w-full text-sm text-left">
                <thead class="bg-slate-50 text-slate-600">
                    <tr class="hover:bg-slate-50 transition border-b border-slate-200">
                        <th class="px-4 py-3 font-medium" data-sort>Username</th>
                        <th class="px-4 py-3 font-medium" data-sort>Nama Lengkap</th>
                        <th class="px-4 py-3 font-medium" data-sort>NIP</th>
                        <th class="px-4 py-3 font-medium" data-sort>Role</th>
                        <th class="px-4 py-3 font-medium" data-sort>Jabatan</th>
                        <th class="px-4 py-3 font-medium" data-sort>Status</th>
                        <th class="px-4 py-3 font-medium" data-sort>Terdaftar</th>
                        <th class="px-4 py-3 font-medium text-center" data-sort>Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-slate-400">
                                Data user tidak ditemukan.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr class="hover:bg-slate-50 transition hover:bg-slate-50 transition">
                                <td class="px-4 py-4 text-slate-800">
                                    <?= e($user['username']) ?>
                                </td>

                                <td class="px-4 py-4 text-slate-800">
                                    <?= e($user['name']) ?>
                                </td>

                                <td class="px-4 py-4 text-slate-700">
                                    <?= e($user['nip'] ?: '-') ?>
                                </td>

                                <td class="px-4 py-4">
                                    <?php if (strtolower($user['role']) === 'admin'): ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-md text-xs font-medium bg-indigo-500 text-white">
                                            Admin
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-md text-xs font-medium bg-slate-100 text-slate-700">
                                            User
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-4 py-4 text-slate-700">
                                    <?= e($user['jabatan'] ?: '-') ?>
                                </td>

                                <td class="px-4 py-4">
                                    <?php if ((int)$user['is_active'] === 1): ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-md text-xs font-medium bg-green-600 text-white">
                                            Aktif
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-md text-xs font-medium bg-red-500 text-white">
                                            Nonaktif
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-4 py-4 text-slate-700">
                                    <?= e($user['created_at'] ?: '-') ?>
                                </td>

                                <td class="px-4 py-4">
                                    <div class="flex flex-wrap items-center justify-center gap-2">
                                        <button
                                            type="button"
                                            onclick="openResetPasswordModal(<?= (int)$user['id'] ?>, '<?= e($user['username']) ?>', '<?= e($user['name']) ?>')"
                                            class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-blue-50 text-blue-600 hover:bg-blue-100 transition text-xs font-medium"
                                            title="Reset password user">
                                            <i class="fa-solid fa-key"></i>
                                            <span>Reset</span>
                                        </button>

                                        <?php if ((int)$user['id'] === $currentAdminId): ?>
                                            <span class="text-xs text-slate-400">Akun Anda</span>
                                        <?php else: ?>
                                            <form method="POST" onsubmit="return confirm('Yakin ingin mengubah status user ini?')">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                                <input type="hidden" name="new_status" value="<?= (int)$user['is_active'] === 1 ? 0 : 1 ?>">
                                                <input type="hidden" name="q" value="<?= e($q) ?>">
                                                <input type="hidden" name="page_num" value="<?= $pageNum ?>">

                                                <?php if ((int)$user['is_active'] === 1): ?>
                                                    <button
                                                        type="submit"
                                                        class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-red-50 text-red-600 hover:bg-red-100 transition text-xs font-medium"
                                                        title="Nonaktifkan user">
                                                        <i class="fa-solid fa-user-slash"></i>
                                                        <span>Nonaktifkan</span>
                                                    </button>
                                                <?php else: ?>
                                                    <button
                                                        type="submit"
                                                        class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-green-50 text-green-600 hover:bg-green-100 transition text-xs font-medium"
                                                        title="Aktifkan user">
                                                        <i class="fa-solid fa-user-check"></i>
                                                        <span>Aktifkan</span>
                                                    </button>
                                                <?php endif; ?>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <div class="mt-5 flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="text-sm text-slate-500">
                Halaman <?= $pageNum ?> dari <?= $totalPages ?>
            </div>

            <div class="flex items-center gap-2">
                <?php
                $prevDisabled = $pageNum <= 1;
                $nextDisabled = $pageNum >= $totalPages;

                $baseParams = ['page' => 'manajemen_user'];
                if ($q !== '') {
                    $baseParams['q'] = $q;
                }
                ?>

                <?php if ($prevDisabled): ?>
                    <span class="px-4 py-2 rounded-lg bg-slate-100 text-slate-400 text-sm cursor-not-allowed">
                        <i class="fa-solid fa-chevron-left mr-1"></i> Sebelumnya
                    </span>
                <?php else: ?>
                    <a
                        href="main.php?<?= http_build_query(array_merge($baseParams, ['page_num' => $pageNum - 1])) ?>"
                        class="px-4 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm hover:bg-slate-200 transition">
                        <i class="fa-solid fa-chevron-left mr-1"></i> Sebelumnya
                    </a>
                <?php endif; ?>

                <?php if ($nextDisabled): ?>
                    <span class="px-4 py-2 rounded-lg bg-slate-100 text-slate-400 text-sm cursor-not-allowed">
                        Berikutnya <i class="fa-solid fa-chevron-right ml-1"></i>
                    </span>
                <?php else: ?>
                    <a
                        href="main.php?<?= http_build_query(array_merge($baseParams, ['page_num' => $pageNum + 1])) ?>"
                        class="px-4 py-2 rounded-lg bg-primary text-white text-sm hover:bg-primaryDark transition">
                        Berikutnya <i class="fa-solid fa-chevron-right ml-1"></i>
                    </a>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<!-- MODAL TAMBAH USER -->
<div id="addUserModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50 p-4">
    <div class="bg-white w-full max-w-2xl rounded-2xl shadow-xl border border-slate-200">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
            <h3 class="text-lg font-semibold text-slate-800">Tambah User</h3>
            <button type="button" onclick="closeAddUserModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="add_user">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium text-slate-700">Username <span class="text-red-500">*</span></label>
                    <input
                        type="text"
                        name="username"
                        required
                        class="w-full mt-1 p-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:border-primary"
                        placeholder="Masukkan username">
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">NIP</label>
                    <input
                        type="text"
                        name="nip"
                        class="w-full mt-1 p-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:border-primary"
                        placeholder="Masukkan NIP">
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Nama Lengkap <span class="text-red-500">*</span></label>
                    <input
                        type="text"
                        name="name"
                        required
                        class="w-full mt-1 p-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:border-primary"
                        placeholder="Masukkan nama lengkap">
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Jabatan <span class="text-red-500">*</span></label>
                    <input
                        type="text"
                        name="jabatan"
                        required
                        class="w-full mt-1 p-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:border-primary"
                        placeholder="Masukkan jabatan">
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Role <span class="text-red-500">*</span></label>
                    <select
                        name="role"
                        required
                        class="w-full mt-1 p-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:border-primary">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div>
                    <label class="text-sm font-medium text-slate-700">Password <span class="text-red-500">*</span></label>
                    <div class="relative mt-1">
                        <input
                            type="password"
                            id="addUserPassword"
                            name="password"
                            required
                            minlength="8"
                            pattern="(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}"
                            title="Minimal 8 karakter, mengandung huruf besar, angka, dan simbol"
                            class="w-full p-2.5 pr-11 rounded-lg border border-slate-200 text-sm focus:outline-none focus:border-primary"
                            placeholder="Masukkan password">
                        <button type="button" onclick="togglePasswordInput('addUserPassword', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary" aria-label="Lihat password">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                    <p class="text-xs text-slate-500 mt-1.5">Minimal 8 karakter, terdiri dari huruf besar, angka, dan simbol.</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <input type="checkbox" id="is_active" name="is_active" checked class="accent-primary">
                <label for="is_active" class="text-sm text-slate-700">Aktifkan user setelah dibuat</label>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <button
                    type="button"
                    onclick="closeAddUserModal()"
                    class="px-4 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm hover:bg-slate-50 transition">
                    Batal
                </button>

                <button
                    type="submit"
                    class="px-4 py-2.5 rounded-lg bg-primary text-white text-sm hover:bg-primaryDark transition">
                    Simpan User
                </button>
            </div>
        </form>
    </div>
</div>


<!-- MODAL RESET PASSWORD -->
<div id="resetPasswordModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50 p-4">
    <div class="bg-white w-full max-w-md rounded-2xl shadow-xl border border-slate-200">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
            <div>
                <h3 class="text-lg font-semibold text-slate-800">Reset Password</h3>
                <p id="resetPasswordUserInfo" class="text-xs text-slate-500 mt-1">-</p>
            </div>
            <button type="button" onclick="closeResetPasswordModal()" class="text-slate-400 hover:text-slate-600">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>

        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="user_id" id="resetPasswordUserId">
            <input type="hidden" name="q" value="<?= e($q) ?>">
            <input type="hidden" name="page_num" value="<?= $pageNum ?>">

            <div>
                <label class="text-sm font-medium text-slate-700">Password Baru <span class="text-red-500">*</span></label>
                <div class="relative mt-1">
                    <input
                        type="password"
                        id="resetNewPassword"
                        name="new_password"
                        required
                        minlength="8"
                        pattern="(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}"
                        title="Minimal 8 karakter, mengandung huruf besar, angka, dan simbol"
                        class="w-full p-2.5 pr-11 rounded-lg border border-slate-200 text-sm focus:outline-none focus:border-primary"
                        placeholder="Minimal 8 karakter">
                    <button type="button" onclick="togglePasswordInput('resetNewPassword', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary" aria-label="Lihat password"><i class="fa-regular fa-eye"></i></button>
                </div>
            </div>

            <div>
                <label class="text-sm font-medium text-slate-700">Konfirmasi Password <span class="text-red-500">*</span></label>
                <div class="relative mt-1">
                    <input
                        type="password"
                        id="resetConfirmPassword"
                        name="confirm_password"
                        required
                        minlength="8"
                        pattern="(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}"
                        title="Minimal 8 karakter, mengandung huruf besar, angka, dan simbol"
                        class="w-full p-2.5 pr-11 rounded-lg border border-slate-200 text-sm focus:outline-none focus:border-primary"
                        placeholder="Ulangi password baru">
                    <button type="button" onclick="togglePasswordInput('resetConfirmPassword', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary" aria-label="Lihat password"><i class="fa-regular fa-eye"></i></button>
                </div>
            </div>

            <div class="rounded-xl bg-yellow-50 border border-yellow-200 text-yellow-700 text-xs p-3">
                Password minimal 8 karakter dan wajib mengandung huruf besar, angka, serta simbol. Password disimpan dalam bentuk hash.
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <button
                    type="button"
                    onclick="closeResetPasswordModal()"
                    class="px-4 py-2.5 rounded-lg border border-slate-200 text-slate-600 text-sm hover:bg-slate-50 transition">
                    Batal
                </button>

                <button
                    type="submit"
                    class="px-4 py-2.5 rounded-lg bg-primary text-white text-sm hover:bg-primaryDark transition">
                    Simpan Password
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePasswordInput(inputId, button) {
    const input = document.getElementById(inputId);
    if (!input) return;
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    const icon = button.querySelector('i');
    if (icon) {
        icon.classList.toggle('fa-eye', !show);
        icon.classList.toggle('fa-eye-slash', show);
    }
    button.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Lihat password');
}

function openAddUserModal() {
    const modal = document.getElementById('addUserModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeAddUserModal() {
    const modal = document.getElementById('addUserModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function openResetPasswordModal(userId, username, name) {
    const modal = document.getElementById('resetPasswordModal');
    const userIdInput = document.getElementById('resetPasswordUserId');
    const info = document.getElementById('resetPasswordUserInfo');

    userIdInput.value = userId;
    info.textContent = name + ' (@' + username + ')';

    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeResetPasswordModal() {
    const modal = document.getElementById('resetPasswordModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
</script>

<script>
// Sortable table headers
document.querySelectorAll('th[data-sort]').forEach(th => {
    th.style.cursor = 'pointer';
    th.title = 'Klik untuk mengurutkan';
    th.addEventListener('click', () => {
        const table = th.closest('table');
        const col = [...th.parentElement.children].indexOf(th);
        const asc = th.dataset.dir !== 'asc';
        document.querySelectorAll('th[data-sort]').forEach(t => {
            delete t.dataset.dir;
            t.querySelector('.sort-icon')?.remove();
        });
        th.dataset.dir = asc ? 'asc' : 'desc';
        const icon = document.createElement('i');
        icon.className = 'sort-icon fa-solid ' + (asc ? 'fa-sort-up' : 'fa-sort-down') + ' ml-1 text-primary text-xs';
        th.appendChild(icon);
        [...table.querySelectorAll('tbody tr')]
            .sort((a, b) => {
                const x = a.cells[col]?.textContent.trim() ?? '';
                const y = b.cells[col]?.textContent.trim() ?? '';
                return asc ? x.localeCompare(y, 'id') : y.localeCompare(x, 'id');
            })
            .forEach(tr => table.querySelector('tbody').appendChild(tr));
    });
});
// Loading state on form submit
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        const btn = this.querySelector('[type=submit]:not([data-no-load])');
        if (btn && !btn.disabled) {
            btn.disabled = true;
            const orig = btn.innerHTML;
            btn.setAttribute('data-orig', orig);
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Memproses...';
            setTimeout(() => { btn.disabled = false; btn.innerHTML = orig; }, 8000);
        }
    });
});
</script>

<script>
function showToast(msg, type) {
    type = type || 'success';
    const colors = { success: 'bg-emerald-600', error: 'bg-red-600', info: 'bg-blue-600', warning: 'bg-amber-500' };
    const icons  = { success: 'fa-circle-check', error: 'fa-circle-xmark', info: 'fa-circle-info', warning: 'fa-triangle-exclamation' };
    const t = document.createElement('div');
    t.className = 'fixed top-5 right-5 z-[9999] flex items-center gap-2.5 px-4 py-3 rounded-xl text-white text-sm font-medium shadow-lg ' + (colors[type] || colors.success);
    t.style.cssText = 'opacity:0;transform:translateX(20px);transition:all .25s ease';
    t.innerHTML = '<i class="fa-solid ' + (icons[type] || icons.success) + '"></i><span>' + msg + '</span>';
    document.body.appendChild(t);
    requestAnimationFrame(() => { t.style.opacity='1'; t.style.transform='translateX(0)'; });
    setTimeout(() => { t.style.opacity='0'; t.style.transform='translateX(20px)'; setTimeout(() => t.remove(), 300); }, 3500);
}
// Auto-show flash messages as toast if present
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-toast-msg]').forEach(el => {
        showToast(el.dataset.toastMsg, el.dataset.toastType || 'success');
        el.remove();
    });
});
</script>

