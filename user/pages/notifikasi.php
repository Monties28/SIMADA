<?php
/* =========================================================
   NOTIFIKASI SIMADA
   Tampilan daftar notifikasi dengan checkbox, hapus satuan,
   hapus massal, dan status terbaca yang otomatis sinkron.
========================================================= */

declare(strict_types=1);

$db = appDb();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$role = (string) ($_SESSION['role'] ?? '');

if (!$db || $userId <= 0) {
    echo '<div class="rounded-2xl bg-red-50 border border-red-200 text-red-700 p-4">Koneksi atau session tidak tersedia.</div>';
    return;
}

appEnsureSimadaCoreSchema($db);
appClearLegacyPopupNotificationNoise($db, $userId, $role);

$noticeMap = [
    'read_all' => 'Semua notifikasi sudah ditandai dibaca.',
    'read_selected' => 'Notifikasi terpilih sudah ditandai dibaca.',
    'delete_selected' => 'Notifikasi terpilih berhasil dihapus.',
    'delete_one' => 'Notifikasi berhasil dihapus.',
    'delete_all' => 'Semua notifikasi berhasil dihapus.',
];

$notice = (string) ($_GET['notice'] ?? '');

$notificationNoiseSql = appNotificationNotPopupNoiseSql();

$stmt = $db->prepare("SELECT
        n.id,
        n.title,
        n.message,
        n.link_url,
        COALESCE(ns.is_read, n.is_read, 0) AS is_read,
        n.created_at
    FROM notifications n
    LEFT JOIN notification_user_states ns
        ON ns.notification_id = n.id
       AND ns.user_id = ?
    WHERE (n.user_id = ? OR (n.user_id IS NULL AND n.target_role = ?))
      AND COALESCE(ns.is_deleted, 0) = 0
      AND {$notificationNoiseSql}
    ORDER BY n.created_at DESC
    LIMIT 80");

$notifications = [];

if ($stmt) {
    $stmt->bind_param('iis', $userId, $userId, $role);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }

    $stmt->close();
}

$totalNotifications = count($notifications);
?>

<div class="space-y-6">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-soft p-6">
        <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-primarySoft text-primary flex items-center justify-center"><i class="fa-solid fa-bell"></i></div>
                    <div>
                        <h3 class="text-xl font-bold text-slate-900">Pusat Notifikasi</h3>
                        <p class="text-sm text-slate-500 mt-1">Semua informasi penting akun tersimpan di sini. Membuka halaman ini menandai notifikasi yang terlihat sebagai dibaca.</p>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <label class="inline-flex items-center gap-2 px-3 py-2 rounded-2xl bg-slate-50 border border-slate-200 text-sm font-semibold text-slate-600 cursor-pointer">
                    <input id="checkAllNotifications" type="checkbox" class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary">
                    Pilih semua
                </label>

                <button type="button" onclick="submitBulkNotificationAction('mark_selected')" class="px-4 py-2.5 rounded-2xl bg-sea/10 text-reef text-sm font-semibold hover:bg-sea/15 transition">
                    <i class="fa-solid fa-check-double mr-1"></i> Tandai Terpilih
                </button>

                <button type="button" onclick="submitBulkNotificationAction('delete_selected')" class="px-4 py-2.5 rounded-2xl bg-red-50 text-red-700 text-sm font-semibold hover:bg-red-100 transition">
                    <i class="fa-solid fa-trash-can mr-1"></i> Hapus Terpilih
                </button>

                <form method="POST" class="inline">
                    <?= appCsrfInput() ?>
                    <input type="hidden" name="action" value="mark_read">
                    <button class="w-full sm:w-auto px-4 py-2.5 rounded-2xl bg-primary text-white text-sm font-semibold hover:bg-primaryDark transition">
                        <i class="fa-solid fa-check mr-1"></i> Tandai Semua Dibaca
                    </button>
                </form>

                <form method="POST" class="inline" onsubmit="return confirm('Yakin ingin menghapus semua notifikasi yang tampil untuk akun ini?');">
                    <?= appCsrfInput() ?>
                    <input type="hidden" name="action" value="delete_all">
                    <button class="w-full sm:w-auto px-4 py-2.5 rounded-2xl bg-red-600 text-white text-sm font-semibold hover:bg-red-700 transition">
                        <i class="fa-solid fa-trash mr-1"></i> Hapus Semua
                    </button>
                </form>
            </div>
        </div>

        <?php if (isset($noticeMap[$notice])): ?>
            <div class="mt-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm">
                <i class="fa-solid fa-circle-check mr-1"></i> <?= e($noticeMap[$notice]) ?>
            </div>
        <?php endif; ?>

        <form id="bulkNotificationForm" method="POST" class="hidden">
            <?= appCsrfInput() ?>
            <input type="hidden" name="action" id="bulkNotificationAction" value="">
            <input type="hidden" name="selected_ids" id="bulkNotificationIds" value="">
        </form>
    </div>

    <div class="space-y-3" id="notificationList">
        <?php if (!$notifications): ?>
            <div class="bg-white rounded-3xl border border-slate-200 shadow-soft p-10 text-center text-slate-400">
                <i class="fa-regular fa-bell text-3xl mb-3"></i>
                <p>Belum ada notifikasi.</p>
            </div>
        <?php endif; ?>

        <?php foreach ($notifications as $notif): ?>
            <?php
                $notifId = (int) ($notif['id'] ?? 0);
                $isRead = (int) ($notif['is_read'] ?? 0) === 1;
                $hasLink = trim((string) ($notif['link_url'] ?? '')) !== '';
            ?>
            <div class="bg-white rounded-3xl border <?= $isRead ? 'border-slate-200' : 'border-primary/40 ring-1 ring-primary/10' ?> shadow-soft p-5 flex gap-4">
                <div class="pt-3">
                    <input type="checkbox" data-notification-checkbox value="<?= $notifId ?>" class="w-4 h-4 rounded border-slate-300 text-primary focus:ring-primary">
                </div>

                <div class="w-11 h-11 rounded-2xl <?= $isRead ? 'bg-slate-100 text-slate-500' : 'bg-primarySoft text-primary' ?> flex items-center justify-center shrink-0">
                    <i class="fa-solid <?= $isRead ? 'fa-bell' : 'fa-bell-ring' ?>"></i>
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h4 class="font-bold text-slate-900"><?= e($notif['title'] ?? '') ?></h4>
                                <?php if (!$isRead): ?>
                                    <span class="px-2 py-0.5 rounded-full bg-coral/10 text-coral text-[10px] font-extrabold uppercase tracking-wide">Baru</span>
                                <?php else: ?>
                                    <span class="px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 text-[10px] font-bold">Dibaca</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-sm text-slate-600 mt-1"><?= e($notif['message'] ?? '') ?></p>
                            <span class="inline-flex items-center gap-1 text-xs text-slate-400 mt-2">
                                <i class="fa-regular fa-clock"></i> <?= e(appFormatDateTime((string) ($notif['created_at'] ?? ''))) ?>
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <?php if ($hasLink): ?>
                                <a href="main.php?page=notifikasi&action=open&id=<?= $notifId ?>" class="inline-flex items-center gap-2 px-3 py-2 rounded-2xl bg-sea/10 text-reef text-xs font-bold hover:bg-sea/15 transition">
                                    Buka Detail <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                </a>
                            <?php endif; ?>

                            <form method="POST" onsubmit="return confirm('Hapus notifikasi ini?');">
                                <?= appCsrfInput() ?>
                                <input type="hidden" name="action" value="delete_one">
                                <input type="hidden" name="id" value="<?= $notifId ?>">
                                <button type="submit" class="inline-flex items-center gap-2 px-3 py-2 rounded-2xl bg-red-50 text-red-700 text-xs font-bold hover:bg-red-100 transition" data-no-load="1">
                                    <i class="fa-solid fa-trash-can"></i> Hapus
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
(function () {
    const checkAll = document.getElementById('checkAllNotifications');

    function getNotificationCheckboxes() {
        return Array.from(document.querySelectorAll('[data-notification-checkbox]'));
    }

    function refreshCheckAllState() {
        if (!checkAll) return;
        const boxes = getNotificationCheckboxes();
        const checked = boxes.filter(box => box.checked).length;
        checkAll.checked = boxes.length > 0 && checked === boxes.length;
        checkAll.indeterminate = checked > 0 && checked < boxes.length;
    }

    if (checkAll) {
        checkAll.addEventListener('change', function () {
            getNotificationCheckboxes().forEach(box => { box.checked = checkAll.checked; });
            refreshCheckAllState();
        });
    }

    getNotificationCheckboxes().forEach(box => {
        box.addEventListener('change', refreshCheckAllState);
    });

    window.submitBulkNotificationAction = function (action) {
        const ids = getNotificationCheckboxes()
            .filter(box => box.checked)
            .map(box => box.value)
            .filter(Boolean);

        if (!ids.length) {
            alert('Pilih minimal satu notifikasi terlebih dahulu.');
            return;
        }

        if (action === 'delete_selected' && !confirm('Hapus notifikasi yang dipilih?')) {
            return;
        }

        const form = document.getElementById('bulkNotificationForm');
        const actionInput = document.getElementById('bulkNotificationAction');
        const idsInput = document.getElementById('bulkNotificationIds');

        if (!form || !actionInput || !idsInput) return;

        actionInput.value = action;
        idsInput.value = ids.join(',');
        form.submit();
    };
})();
</script>
