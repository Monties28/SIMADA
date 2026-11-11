<?php
/* =========================================================
   PENGATURAN PROFIL SIMADA
   Fitur: update identitas, foto profil, password, dan informasi storage.
========================================================= */

declare(strict_types=1);
require_once __DIR__ . '/../../config/auth.php';

$db = appDb();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$flashMsg = '';
$flashMsgType = 'success';

if (!$db || $userId <= 0) {
    echo '<div class="rounded-2xl bg-red-50 border border-red-200 text-red-700 p-4">Session atau koneksi database tidak tersedia.</div>';
    return;
}

appEnsureSimadaCoreSchema($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_profile') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $nip = trim((string) ($_POST['nip'] ?? ''));
        $jabatan = trim((string) ($_POST['jabatan'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));

        if ($name === '' || $jabatan === '') {
            $flashMsg = 'Nama dan jabatan wajib diisi.';
            $flashMsgType = 'error';
        } else {
            $oldPhoto = null;
            $stmtOld = $db->prepare('SELECT profile_photo FROM users WHERE id = ? LIMIT 1');
            if ($stmtOld) {
                $stmtOld->bind_param('i', $userId);
                $stmtOld->execute();
                $oldRow = $stmtOld->get_result()->fetch_assoc();
                $oldPhoto = (string) ($oldRow['profile_photo'] ?? '');
                $stmtOld->close();
            }

            $newPhoto = $oldPhoto;
            $photoUpload = appSaveUploadedProfilePhoto($_FILES['profile_photo'] ?? [], $userId, $oldPhoto);

            if (!$photoUpload['ok']) {
                $flashMsg = (string) $photoUpload['message'];
                $flashMsgType = 'error';
            } else {
                $newPhoto = (string) ($photoUpload['path'] ?? $oldPhoto);
                $nipDb = $nip !== '' ? $nip : null;
                $phoneDb = $phone !== '' ? $phone : null;

                $stmt = $db->prepare('UPDATE users SET name = ?, nip = ?, jabatan = ?, phone = ?, profile_photo = ?, updated_at = NOW() WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('sssssi', $name, $nipDb, $jabatan, $phoneDb, $newPhoto, $userId);
                    if ($stmt->execute()) {
                        $_SESSION['name'] = $name;
                        $_SESSION['nip'] = $nipDb;
                        $_SESSION['jabatan'] = $jabatan;
                        $flashMsg = 'Profil berhasil diperbarui.';
                        if (($photoUpload['message'] ?? '') === 'Foto profil berhasil diperbarui.') {
                            $flashMsg .= ' Foto profil juga berhasil diganti.';
                        }
                        $flashMsgType = 'success';
                    } else {
                        if ($newPhoto !== $oldPhoto) {
                            appDeleteProfilePhoto($newPhoto);
                        }
                        $flashMsg = 'Profil gagal diperbarui. NIP mungkin sudah digunakan akun lain.';
                        $flashMsgType = 'error';
                    }
                    $stmt->close();
                }
            }
        }
    }

    if ($action === 'delete_photo') {
        $stmtOld = $db->prepare('SELECT profile_photo FROM users WHERE id = ? LIMIT 1');
        $oldPhoto = '';
        if ($stmtOld) {
            $stmtOld->bind_param('i', $userId);
            $stmtOld->execute();
            $oldPhoto = (string) (($stmtOld->get_result()->fetch_assoc()['profile_photo'] ?? ''));
            $stmtOld->close();
        }

        $stmtClear = $db->prepare('UPDATE users SET profile_photo = NULL, updated_at = NOW() WHERE id = ?');
        if ($stmtClear) {
            $stmtClear->bind_param('i', $userId);
            if ($stmtClear->execute()) {
                appDeleteProfilePhoto($oldPhoto);
                $flashMsg = 'Foto profil berhasil dihapus.';
                $flashMsgType = 'success';
            } else {
                $flashMsg = 'Foto profil gagal dihapus.';
                $flashMsgType = 'error';
            }
            $stmtClear->close();
        }
    }

    if ($action === 'change_password') {
        $oldPassword = (string) ($_POST['old_password'] ?? '');
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');

        $row = null;
        $stmt = $db->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $resultPassword = $stmt->get_result();
            $row = $resultPassword ? $resultPassword->fetch_assoc() : null;
            $stmt->close();
        }

        if (!verifyAppPassword($oldPassword, (string) ($row['password'] ?? ''))) {
            $flashMsg = 'Password lama tidak sesuai.';
            $flashMsgType = 'error';
        } elseif (!appIsStrongPassword($newPassword)) {
            $flashMsg = appPasswordPolicyDescription();
            $flashMsgType = 'error';
        } elseif ($newPassword !== $confirmPassword) {
            $flashMsg = 'Konfirmasi password tidak sama.';
            $flashMsgType = 'error';
        } else {
            $hash = hashAppPassword($newPassword);
            $stmtUpdate = $db->prepare('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?');
            if ($stmtUpdate) {
                $stmtUpdate->bind_param('si', $hash, $userId);
                $stmtUpdate->execute();
                $stmtUpdate->close();
                $flashMsg = 'Password berhasil diperbarui.';
                $flashMsgType = 'success';
            }
        }
    }
}

$stmt = $db->prepare('SELECT username, nip, name, jabatan, phone, role, profile_photo FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

$photoPath = trim((string) ($user['profile_photo'] ?? ''));
$photoUrl = $photoPath !== '' ? appProfilePhotoUrl($userId, $photoPath) : '';
$initials = appUserInitials((string) ($user['name'] ?? $user['username'] ?? 'User'));
?>

<div class="space-y-6">
    <?php if ($flashMsg !== ''): ?>
        <div class="rounded-2xl p-4 text-sm <?= $flashMsgType === 'error' ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-emerald-50 border border-emerald-200 text-emerald-700' ?>">
            <?= e($flashMsg) ?>
        </div>
    <?php endif; ?>

    <div class="simada-profile-hero relative overflow-hidden rounded-[2rem] text-white shadow-soft p-6 md:p-8">
        <div class="absolute -top-20 -right-16 w-72 h-72 bg-white/10 rounded-full blur-3xl"></div>
        <div class="relative flex flex-col md:flex-row md:items-center gap-5">
            <div class="w-24 h-24 rounded-3xl bg-white/15 border border-white/20 overflow-hidden flex items-center justify-center shrink-0 shadow-lg">
                <?php if ($photoUrl !== ''): ?>
                    <img src="<?= e($photoUrl) ?>" alt="Foto Profil" class="w-full h-full object-cover">
                <?php else: ?>
                    <span class="text-2xl font-black tracking-wide"><?= e($initials) ?></span>
                <?php endif; ?>
            </div>
            <div class="min-w-0">
                <p class="simada-profile-hero-muted text-sm">Profil Akun SIMADA</p>
                <h2 class="text-2xl md:text-3xl font-extrabold mt-1 truncate"><?= e($user['name'] ?? $user['username'] ?? '-') ?></h2>
                <p class="simada-profile-hero-muted text-sm mt-2"><?= e($user['jabatan'] ?? '-') ?> · <?= e(ucfirst((string) ($user['role'] ?? '-'))) ?></p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 bg-white rounded-3xl border border-slate-200 shadow-soft p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Pengaturan Profil</h3>
                    <p class="text-sm text-slate-500 mt-1">Perbarui identitas akun, nomor kontak, dan foto profil.</p>
                </div>
                <span class="simada-role-chip hidden sm:inline-flex px-3 py-1 rounded-full text-xs font-semibold">Akun <?= e(ucfirst((string) ($user['role'] ?? '-'))) ?></span>
            </div>

            <form method="POST" enctype="multipart/form-data" class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                <input type="hidden" name="action" value="update_profile">

                <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-[120px_1fr] gap-4 items-center rounded-3xl bg-slate-50 border border-slate-200 p-4">
                    <div class="w-24 h-24 rounded-3xl bg-white border border-slate-200 overflow-hidden flex items-center justify-center shadow-sm mx-auto md:mx-0">
                        <?php if ($photoUrl !== ''): ?>
                            <img src="<?= e($photoUrl) ?>" alt="Foto Profil" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="text-xl font-black text-primary"><?= e($initials) ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-2">Foto Profil</label>
                        <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-slate-600 file:mr-4 file:rounded-2xl file:border-0 file:bg-primary file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-white hover:file:bg-primaryDark">
                        <p class="text-xs text-slate-500 mt-2">Format JPG, PNG, atau WEBP. Maksimal 2 MB. File disimpan di storage SIMADA terpisah.</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Username</label>
                    <input value="<?= e($user['username'] ?? '') ?>" disabled class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">NIP</label>
                    <input name="nip" value="<?= e($user['nip'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:border-primary">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nama Lengkap</label>
                    <input name="name" value="<?= e($user['name'] ?? '') ?>" required class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:border-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Jabatan</label>
                    <input name="jabatan" value="<?= e($user['jabatan'] ?? '') ?>" required class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:border-primary">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">No. HP</label>
                    <input name="phone" value="<?= e($user['phone'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:border-primary">
                </div>
                <div class="md:col-span-2 flex flex-col sm:flex-row justify-end gap-3">
                    <?php if ($photoUrl !== ''): ?>
                        <button type="submit" name="action" value="delete_photo" form="deletePhotoForm" class="px-5 py-3 rounded-2xl border border-red-200 text-red-600 text-sm font-semibold hover:bg-red-50 transition">Hapus Foto</button>
                    <?php endif; ?>
                    <button class="px-5 py-3 rounded-2xl bg-primary text-white text-sm font-semibold hover:bg-primaryDark transition">Simpan Profil</button>
                </div>
            </form>

            <form id="deletePhotoForm" method="POST" onsubmit="return confirm('Hapus foto profil?')">
                <input type="hidden" name="action" value="delete_photo">
            </form>
        </div>

        <div class="space-y-6">
            <div class="bg-white rounded-3xl border border-slate-200 shadow-soft p-6">
                <div class="w-12 h-12 rounded-2xl bg-primarySoft text-primary flex items-center justify-center mb-4">
                    <i class="fa-solid fa-folder-tree"></i>
                </div>
                <h3 class="text-lg font-bold text-slate-900">Storage SIMADA</h3>
                <p class="text-sm text-slate-500 mt-2">Dokumen dan foto profil disimpan sebagai file asli pada storage SIMADA agar bisa dipreview dan diunduh sesuai hak akses.</p>
                <div class="mt-4 rounded-2xl bg-slate-50 border border-slate-200 p-4 text-xs text-slate-600 break-all">
                    <?= e(appStorageBasePath()) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-3xl border border-slate-200 shadow-soft p-6">
        <h3 class="text-lg font-bold text-slate-900">Ubah Password</h3>
        <form method="POST" class="mt-5 grid grid-cols-1 md:grid-cols-3 gap-4">
            <input type="hidden" name="action" value="change_password">
            <div class="relative">
                <input type="password" id="profileOldPassword" name="old_password" placeholder="Password lama" required class="w-full rounded-2xl border border-slate-200 pl-4 pr-12 py-3 text-sm focus:outline-none focus:border-primary">
                <button type="button" onclick="toggleProfilePassword('profileOldPassword', this)" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary" aria-label="Lihat password"><i class="fa-regular fa-eye"></i></button>
            </div>
            <div class="relative">
                <input type="password" id="profileNewPassword" name="new_password" placeholder="Password baru minimal 8 karakter" required minlength="8" pattern="(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}" title="Minimal 8 karakter, mengandung huruf besar, angka, dan simbol" class="w-full rounded-2xl border border-slate-200 pl-4 pr-12 py-3 text-sm focus:outline-none focus:border-primary">
                <button type="button" onclick="toggleProfilePassword('profileNewPassword', this)" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary" aria-label="Lihat password"><i class="fa-regular fa-eye"></i></button>
            </div>
            <div class="relative">
                <input type="password" id="profileConfirmPassword" name="confirm_password" placeholder="Konfirmasi password baru" required minlength="8" pattern="(?=.*[A-Z])(?=.*[0-9])(?=.*[^A-Za-z0-9]).{8,}" title="Minimal 8 karakter, mengandung huruf besar, angka, dan simbol" class="w-full rounded-2xl border border-slate-200 pl-4 pr-12 py-3 text-sm focus:outline-none focus:border-primary">
                <button type="button" onclick="toggleProfilePassword('profileConfirmPassword', this)" class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-primary" aria-label="Lihat password"><i class="fa-regular fa-eye"></i></button>
            </div>
            <p class="md:col-span-3 text-xs text-slate-500">Password minimal 8 karakter dan wajib mengandung huruf besar, angka, serta simbol.</p>
            <div class="md:col-span-3 flex justify-end">
                <button class="px-5 py-3 rounded-2xl border border-primary text-primary text-sm font-semibold hover:bg-primarySoft transition">Update Password</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleProfilePassword(inputId, button) {
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

