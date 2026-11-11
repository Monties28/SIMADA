<?php
/*
 * pages/pengaturan.php
 * Fragment — di-include oleh user/dashboard.php
 * JANGAN tambahkan session_start(), <!DOCTYPE>, <head>, <body>, sidebar di sini
 */

$successMsg = '';
$errorMsg   = '';

/*
 * TODO: Hubungkan ke database untuk simpan profil & ganti password
 * Contoh alur:
 *   if POST simpan_profil  → UPDATE users SET fullname=? WHERE id=?
 *   if POST ganti_password → cek password lama, UPDATE password hash baru
 */
?>

<?php if ($successMsg): ?>
    <div class="mb-4 p-3 bg-green-100 text-green-700 border border-green-300 rounded-lg text-sm">
        <i class="fa-solid fa-circle-check mr-1"></i> <?= e($successMsg) ?>
    </div>
<?php endif; ?>

<?php if ($errorMsg): ?>
    <div class="mb-4 p-3 bg-red-100 text-red-700 border border-red-300 rounded-lg text-sm">
        <i class="fa-solid fa-circle-exclamation mr-1"></i> <?= e($errorMsg) ?>
    </div>
<?php endif; ?>

<div class="space-y-6">

    <div>
        <h1 class="text-lg font-semibold text-slate-700">Pengaturan Akun</h1>
        <p class="text-sm text-slate-400">Kelola profil dan keamanan akun Anda</p>
    </div>

    <div class="grid grid-cols-2 gap-6">

        <!-- PROFIL PENGGUNA -->
        <div class="bg-white rounded-xl p-6 shadow-sm space-y-4">
            <h3 class="font-semibold flex items-center gap-2 text-slate-700 text-sm">
                <i class="fa-regular fa-user text-primary"></i> Profil Pengguna
            </h3>

            <form method="POST" action="dashboard.php?page=pengaturan">
                <input type="hidden" name="action" value="simpan_profil">

                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Nama Lengkap</label>
                        <input type="text" name="fullname"
                               value=""
                               placeholder="Masukkan nama lengkap"
                               class="w-full mt-1 p-2 rounded-lg border text-sm focus:outline-none focus:border-primary">
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700">Username</label>
                        <input type="text"
                               value="<?= e($_SESSION['username']) ?>"
                               disabled
                               class="w-full mt-1 p-2 rounded-lg border bg-slate-100 text-slate-400 text-sm cursor-not-allowed">
                        <p class="text-xs text-slate-400 mt-1">Username tidak dapat diubah</p>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700">Email</label>
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
            <h3 class="font-semibold flex items-center gap-2 text-slate-700 text-sm">
                <i class="fa-solid fa-lock text-primary"></i> Ganti Password
            </h3>

            <form method="POST" action="dashboard.php?page=pengaturan">
                <input type="hidden" name="action" value="ganti_password">

                <div class="space-y-3">
                    <div>
                        <label class="text-sm font-medium text-slate-700">Password Lama</label>
                        <div class="relative mt-1">
                            <input type="password" id="settingsOldPassword" name="old_password"
                                   placeholder="Masukkan password lama"
                                   autocomplete="current-password"
                                   class="w-full p-2 pr-10 rounded-lg border text-sm focus:outline-none focus:border-primary">
                            <button type="button" onclick="toggleSettingsPassword('settingsOldPassword', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary" aria-label="Lihat password"><i class="fa-regular fa-eye"></i></button>
                        </div>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700">Password Baru</label>
                        <div class="relative mt-1">
                            <input type="password" id="settingsNewPassword" name="new_password"
                                   placeholder="Minimal 8 karakter"
                                   autocomplete="new-password"
                                   minlength="8"
                                   pattern="(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}"
                                   title="Minimal 8 karakter, mengandung huruf besar, angka, dan simbol"
                                   class="w-full p-2 pr-10 rounded-lg border text-sm focus:outline-none focus:border-primary">
                            <button type="button" onclick="toggleSettingsPassword('settingsNewPassword', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary" aria-label="Lihat password"><i class="fa-regular fa-eye"></i></button>
                        </div>
                    </div>

                    <div>
                        <label class="text-sm font-medium text-slate-700">Konfirmasi Password Baru</label>
                        <div class="relative mt-1">
                            <input type="password" id="settingsConfirmPassword" name="confirm_password"
                                   placeholder="Ulangi password baru"
                                   autocomplete="new-password"
                                   minlength="8"
                                   pattern="(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}"
                                   title="Minimal 8 karakter, mengandung huruf besar, angka, dan simbol"
                                   class="w-full p-2 pr-10 rounded-lg border text-sm focus:outline-none focus:border-primary">
                            <button type="button" onclick="toggleSettingsPassword('settingsConfirmPassword', this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary" aria-label="Lihat password"><i class="fa-regular fa-eye"></i></button>
                        </div>
                    </div>
                    <p class="text-xs text-slate-500">Password minimal 8 karakter dengan huruf besar, angka, dan simbol.</p>
                </div>

                <button type="submit"
                    class="w-full mt-5 py-2.5 rounded-lg bg-primary text-white hover:bg-primaryDark transition flex items-center justify-center gap-2 text-sm">
                    <i class="fa-solid fa-key"></i> Ganti Password
                </button>

                <button type="button"
                    class="w-full mt-2 py-2 rounded-lg border text-sm text-slate-600 hover:bg-slate-50 flex items-center justify-center gap-2">
                    <i class="fa-regular fa-comment"></i> Lupa Password? Hubungi Admin
                </button>
            </form>
        </div>

    </div>

    <!-- INFO KEAMANAN -->
    <div class="bg-primarySoft border border-primary/20 rounded-xl p-6">
        <h3 class="font-semibold text-slate-700 mb-3 text-sm">
            <i class="fa-solid fa-shield-halved text-primary mr-2"></i> Informasi Keamanan
        </h3>
        <ul class="list-disc list-inside text-sm text-slate-600 space-y-1">
            <li>Password minimal 8 karakter</li>
            <li>Gunakan kombinasi huruf besar, angka, dan simbol</li>
            <li>Jika lupa password, hubungi administrator</li>
        </ul>
    </div>

</div>

<script>
function toggleSettingsPassword(inputId, button) {
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

