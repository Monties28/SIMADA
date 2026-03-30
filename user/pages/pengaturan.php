<?php
/*
 * pages/pengaturan.php
 * Fragment — di-include oleh user/dashboard.php
 * JANGAN tambahkan session_start(), <!DOCTYPE>, <head>, <body>, sidebar di sini
 */

$success_msg = '';
$error_msg   = '';

/*
 * TODO: Hubungkan ke database untuk simpan profil & ganti password
 * Contoh alur:
 *   if POST simpan_profil  → UPDATE users SET fullname=? WHERE id=?
 *   if POST ganti_password → cek password lama, UPDATE password hash baru
 */
?>

<?php if ($success_msg): ?>
    <div class="mb-4 p-3 bg-green-100 text-green-700 border border-green-300 rounded-lg text-sm">
        <i class="fa-solid fa-circle-check mr-1"></i> <?= htmlspecialchars($success_msg) ?>
    </div>
<?php endif; ?>

<?php if ($error_msg): ?>
    <div class="mb-4 p-3 bg-red-100 text-red-700 border border-red-300 rounded-lg text-sm">
        <i class="fa-solid fa-circle-exclamation mr-1"></i> <?= htmlspecialchars($error_msg) ?>
    </div>
<?php endif; ?>

<div class="space-y-6">

    <div>
        <h1 class="text-lg font-semibold text-gray-700">Pengaturan Akun</h1>
        <p class="text-sm text-gray-400">Kelola profil dan keamanan akun Anda</p>
    </div>

    <div class="grid grid-cols-2 gap-6">

        <!-- PROFIL PENGGUNA -->
        <div class="bg-white rounded-xl p-6 shadow-sm space-y-4">
            <h3 class="font-semibold flex items-center gap-2 text-gray-700 text-sm">
                <i class="fa-regular fa-user text-primary"></i> Profil Pengguna
            </h3>

            <form method="POST" action="dashboard.php?page=pengaturan">
                <input type="hidden" name="action" value="simpan_profil">

                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Nama Lengkap</label>
                        <input type="text" name="fullname"
                               value=""
                               placeholder="Masukkan nama lengkap"
                               class="w-full mt-1 p-2 rounded-lg border text-sm focus:outline-none focus:border-primary">
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-700">Username</label>
                        <input type="text"
                               value="<?= htmlspecialchars($_SESSION['username']) ?>"
                               disabled
                               class="w-full mt-1 p-2 rounded-lg border bg-gray-100 text-gray-400 text-sm cursor-not-allowed">
                        <p class="text-xs text-gray-400 mt-1">Username tidak dapat diubah</p>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email"
                               placeholder="Masukkan email"
                               class="w-full mt-1 p-2 rounded-lg border text-sm focus:outline-none focus:border-primary">
                    </div>
                </div>

                <button type="submit"
                    class="w-full mt-5 py-2.5 rounded-lg bg-primary text-white hover:bg-primaryDark transition flex items-center justify-center gap-2 text-sm">
                    <i class="fa-regular fa-floppy-disk"></i> Simpan Profil
                </button>
            </form>
        </div>

        <!-- GANTI PASSWORD -->
        <div class="bg-white rounded-xl p-6 shadow-sm space-y-4">
            <h3 class="font-semibold flex items-center gap-2 text-gray-700 text-sm">
                <i class="fa-solid fa-lock text-primary"></i> Ganti Password
            </h3>

            <form method="POST" action="dashboard.php?page=pengaturan">
                <input type="hidden" name="action" value="ganti_password">

                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-gray-700">Password Lama</label>
                        <input type="password" name="old_password"
                               placeholder="Masukkan password lama"
                               autocomplete="current-password"
                               class="w-full mt-1 p-2 rounded-lg border text-sm focus:outline-none focus:border-primary">
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-700">Password Baru</label>
                        <input type="password" name="new_password"
                               placeholder="Minimal 8 karakter"
                               autocomplete="new-password"
                               class="w-full mt-1 p-2 rounded-lg border text-sm focus:outline-none focus:border-primary">
                    </div>

                    <div>
                        <label class="text-sm font-medium text-gray-700">Konfirmasi Password Baru</label>
                        <input type="password" name="confirm_password"
                               placeholder="Ulangi password baru"
                               autocomplete="new-password"
                               class="w-full mt-1 p-2 rounded-lg border text-sm focus:outline-none focus:border-primary">
                    </div>
                </div>

                <button type="submit"
                    class="w-full mt-5 py-2.5 rounded-lg bg-primary text-white hover:bg-primaryDark transition flex items-center justify-center gap-2 text-sm">
                    <i class="fa-solid fa-key"></i> Ganti Password
                </button>

                <button type="button"
                    class="w-full mt-2 py-2 rounded-lg border text-sm text-gray-600 hover:bg-gray-50 flex items-center justify-center gap-2">
                    <i class="fa-regular fa-comment"></i> Lupa Password? Hubungi Admin
                </button>
            </form>
        </div>

    </div>

    <!-- INFO KEAMANAN -->
    <div class="bg-primarySoft border border-primary/20 rounded-xl p-6">
        <h3 class="font-semibold text-gray-700 mb-3 text-sm">
            <i class="fa-solid fa-shield-halved text-primary mr-2"></i> Informasi Keamanan
        </h3>
        <ul class="list-disc list-inside text-sm text-gray-600 space-y-1">
            <li>Password harus minimal 8 karakter</li>
            <li>Gunakan kombinasi huruf besar, huruf kecil, angka, dan simbol</li>
            <li>Jangan gunakan password yang mudah ditebak</li>
            <li>Jika lupa password, hubungi administrator</li>
        </ul>
    </div>

</div>