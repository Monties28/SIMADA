<?php
/* =========================================================
   DASHBOARD USER SIMADA
   Fokus: kartu ringkasan dan aktivitas terbaru milik user login.
   Grafik/chart sudah dihapus.
========================================================= */

declare(strict_types=1);

$db = appDb();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$flashMsg = '';
$flashType = 'success';
$activities = [];
$stats = [
    'total_upload' => 0,
    'total_pending' => 0,
    'total_approved' => 0,
    'total_revision' => 0,
    'total_rejected' => 0,
];

if (!$db || $userId <= 0) {
    echo '<div class="rounded-2xl bg-red-50 border border-red-200 text-red-700 p-5">Sesi pengguna atau koneksi database tidak tersedia.</div>';
    return;
}

appEnsureSimadaCoreSchema($db);

if (!appTableExists($db, 'documents')) {
    $flashMsg = 'Tabel documents belum tersedia.';
    $flashType = 'warning';
} else {
    $stmtStats = $db->prepare("SELECT
        COUNT(*) AS total_upload,
        SUM(CASE WHEN LOWER(TRIM(status)) IN ('pending','menunggu') THEN 1 ELSE 0 END) AS total_pending,
        SUM(CASE WHEN LOWER(TRIM(status)) IN ('approved','disetujui') THEN 1 ELSE 0 END) AS total_approved,
        SUM(CASE WHEN LOWER(TRIM(status)) IN ('revision','revisi') THEN 1 ELSE 0 END) AS total_revision,
        SUM(CASE WHEN LOWER(TRIM(status)) IN ('rejected','ditolak') THEN 1 ELSE 0 END) AS total_rejected
        FROM documents
        WHERE user_id = ?");

    if ($stmtStats) {
        $stmtStats->bind_param('i', $userId);
        $stmtStats->execute();
        $resultStats = $stmtStats->get_result();
        if ($resultStats && $rowStats = $resultStats->fetch_assoc()) {
            foreach (['total_upload', 'total_pending', 'total_approved', 'total_revision', 'total_rejected'] as $key) {
                $stats[$key] = (int) ($rowStats[$key] ?? 0);
            }
        }
        $stmtStats->close();
    }

    $updatedCol = appTableHasColumn($db, 'documents', 'updated_at') ? 'updated_at' : 'NULL';
    $verifiedCol = appTableHasColumn($db, 'documents', 'verified_at') ? 'verified_at' : 'NULL';
    $lastRevisionCol = appTableHasColumn($db, 'documents', 'last_revision_at') ? 'last_revision_at' : 'NULL';
    $catatanCol = appTableHasColumn($db, 'documents', 'catatan_admin') ? 'catatan_admin' : (appTableHasColumn($db, 'documents', 'review_note') ? 'review_note' : "''");
    $revisionCountCol = appTableHasColumn($db, 'documents', 'revision_count') ? 'revision_count' : '0';
    $tanggalCol = appTableHasColumn($db, 'documents', 'tanggal_dokumen') ? 'tanggal_dokumen' : 'NULL';
    $kategoriCol = appTableHasColumn($db, 'documents', 'kategori_dokumen') ? 'kategori_dokumen' : "''";
    $jenisCol = appTableHasColumn($db, 'documents', 'jenis_dokumen') ? 'jenis_dokumen' : "''";
    $jenisLainnyaCol = appTableHasColumn($db, 'documents', 'jenis_dokumen_lainnya') ? 'jenis_dokumen_lainnya' : "''";

    $stmt = $db->prepare("
        SELECT
            id,
            file_name,
            status,
            created_at,
            {$updatedCol} AS updated_at,
            {$verifiedCol} AS verified_at,
            {$lastRevisionCol} AS last_revision_at,
            {$catatanCol} AS catatan_admin,
            {$revisionCountCol} AS revision_count,
            {$tanggalCol} AS tanggal_dokumen,
            {$kategoriCol} AS kategori_dokumen,
            {$jenisCol} AS jenis_dokumen,
            {$jenisLainnyaCol} AS jenis_dokumen_lainnya
        FROM documents
        WHERE user_id = ?
        ORDER BY COALESCE({$updatedCol}, {$verifiedCol}, created_at) DESC, created_at DESC, id DESC
        LIMIT 40
    ");

    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($result && $row = $result->fetch_assoc()) {
            $fileName = appDisplayFileName((string) ($row['file_name'] ?? '-'));
            $statusRaw = (string) ($row['status'] ?? 'Pending');
            $statusNorm = appNormalizeDocumentStatus($statusRaw);
            $statusLabel = appDocumentStatusLabel($statusRaw);
            $createdAt = (string) ($row['created_at'] ?? '');
            $updatedAt = (string) ($row['updated_at'] ?? '');
            $verifiedAt = (string) ($row['verified_at'] ?? '');
            $lastRevisionAt = (string) ($row['last_revision_at'] ?? '');
            $catatan = trim((string) ($row['catatan_admin'] ?? ''));
            $docType = appDisplayDocumentType($row);
            $docId = (int) ($row['id'] ?? 0);

            if ($createdAt !== '') {
                $activities[] = [
                    'time' => $createdAt,
                    'sort' => strtotime($createdAt) ?: 0,
                    'icon' => 'fa-cloud-arrow-up',
                    'tone' => 'bg-cyan-50 text-cyan-700 border-cyan-100',
                    'badge' => 'Upload',
                    'title' => 'Dokumen berhasil diupload',
                    'description' => 'Anda mengupload dokumen ' . $fileName . '.',
                    'meta' => $docType,
                    'status' => $statusLabel,
                    'status_class' => appDocumentStatusBadgeClass($statusRaw),
                    'note' => '',
                    'link' => 'main.php?page=pustaka',
                    'revision_link' => '',
                ];
            }

            $statusTime = $verifiedAt !== '' ? $verifiedAt : $updatedAt;
            $createdTs = strtotime($createdAt) ?: 0;
            $statusTs = strtotime($statusTime) ?: 0;
            if ($statusTime !== '' && ($statusNorm !== 'pending' || ($createdTs > 0 && $statusTs > $createdTs + 60))) {
                $revisionLink = '';
                if ($statusNorm === 'revision' && (int) ($row['revision_count'] ?? 0) < appMaxDocumentRevisions()) {
                    $revisionLink = 'main.php?page=upload&revision_id=' . $docId;
                }

                $activities[] = [
                    'time' => $statusTime,
                    'sort' => $statusTs,
                    'icon' => $statusNorm === 'approved' ? 'fa-circle-check' : ($statusNorm === 'revision' ? 'fa-rotate-right' : ($statusNorm === 'rejected' ? 'fa-circle-xmark' : 'fa-pen-to-square')),
                    'tone' => $statusNorm === 'approved' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : ($statusNorm === 'revision' ? 'bg-amber-50 text-amber-700 border-amber-100' : ($statusNorm === 'rejected' ? 'bg-red-50 text-red-700 border-red-100' : 'bg-slate-50 text-slate-700 border-slate-100')),
                    'badge' => 'Status',
                    'title' => 'Status dokumen diperbarui',
                    'description' => 'Dokumen ' . $fileName . ' sekarang berstatus ' . $statusLabel . '.',
                    'meta' => $docType,
                    'status' => $statusLabel,
                    'status_class' => appDocumentStatusBadgeClass($statusRaw),
                    'note' => $catatan !== '' ? 'Catatan admin: ' . $catatan : '',
                    'link' => 'main.php?page=pustaka',
                    'revision_link' => $revisionLink,
                ];
            }

            $revisionTs = strtotime($lastRevisionAt) ?: 0;
            if ($lastRevisionAt !== '' && $revisionTs > 0 && $revisionTs !== $statusTs) {
                $activities[] = [
                    'time' => $lastRevisionAt,
                    'sort' => $revisionTs,
                    'icon' => 'fa-file-arrow-up',
                    'tone' => 'bg-blue-50 text-blue-700 border-blue-100',
                    'badge' => 'Revisi',
                    'title' => 'Revisi dokumen diupload',
                    'description' => 'Anda mengupload ulang revisi dokumen ' . $fileName . '.',
                    'meta' => $docType,
                    'status' => $statusLabel,
                    'status_class' => appDocumentStatusBadgeClass($statusRaw),
                    'note' => '',
                    'link' => 'main.php?page=pustaka',
                    'revision_link' => '',
                ];
            }
        }
        $stmt->close();
    } else {
        $flashMsg = 'Gagal mengambil aktivitas dokumen.';
        $flashType = 'error';
    }
}

usort($activities, static fn(array $a, array $b): int => ($b['sort'] <=> $a['sort']));
$activities = array_slice($activities, 0, 20);
$totalShown = count($activities);

$statCards = [
    ['label' => 'Total Dokumen', 'value' => $stats['total_upload'], 'icon' => 'fa-file-lines', 'tone' => 'bg-cyan-50 text-cyan-700 border-cyan-100', 'desc' => 'Dokumen yang pernah diupload'],
    ['label' => 'Pending', 'value' => $stats['total_pending'], 'icon' => 'fa-clock', 'tone' => 'bg-amber-50 text-amber-700 border-amber-100', 'desc' => 'Menunggu verifikasi admin'],
    ['label' => 'Perlu Revisi', 'value' => $stats['total_revision'], 'icon' => 'fa-rotate-right', 'tone' => 'bg-blue-50 text-blue-700 border-blue-100', 'desc' => 'Perlu diperbaiki'],
    ['label' => 'Disetujui', 'value' => $stats['total_approved'], 'icon' => 'fa-circle-check', 'tone' => 'bg-emerald-50 text-emerald-700 border-emerald-100', 'desc' => 'Dokumen diterima'],
    ['label' => 'Ditolak', 'value' => $stats['total_rejected'], 'icon' => 'fa-circle-xmark', 'tone' => 'bg-red-50 text-red-700 border-red-100', 'desc' => 'Tidak dapat diproses'],
    ['label' => 'Aktivitas Tampil', 'value' => $totalShown, 'icon' => 'fa-list-check', 'tone' => 'bg-slate-50 text-slate-700 border-slate-100', 'desc' => 'Aktivitas terbaru Anda'],
];
?>

<div class="space-y-6">
    <?php if ($flashMsg !== ''): ?>
        <div class="rounded-2xl p-5 text-sm <?= e(appAlertClass($flashType)) ?>">
            <?= e($flashMsg) ?>
        </div>
    <?php endif; ?>

    <section class="relative overflow-hidden rounded-[1.75rem] border border-teal-100 bg-gradient-to-br from-white via-cyan-50 to-emerald-50 p-6 shadow-soft">
        <div class="absolute -right-16 -top-16 w-48 h-48 rounded-full bg-cyan-200/40 blur-3xl"></div>
        <div class="absolute -left-20 -bottom-20 w-56 h-56 rounded-full bg-emerald-200/35 blur-3xl"></div>
        <div class="relative">
            <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/80 border border-teal-100 text-[11px] font-extrabold tracking-[.16em] uppercase text-teal-700">
                <i class="fa-solid fa-water"></i> Dashboard User
            </span>
            <h2 class="text-2xl md:text-3xl font-black tracking-[-.03em] text-slate-900 mt-3">Ringkasan dan Aktivitas Terbaru Saya</h2>
            <p class="text-sm text-slate-600 mt-2 max-w-3xl">Kartu ringkasan tetap ditampilkan, sedangkan grafik dan chart dashboard dihapus. Aktivitas yang muncul hanya aktivitas dokumen milik akun Anda sendiri.</p>
        </div>
    </section>

    <section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
        <?php foreach ($statCards as $card): ?>
            <div class="bg-white rounded-2xl border border-slate-100 shadow-soft p-5">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-500"><?= e($card['label']) ?></p>
                        <p class="text-3xl font-black text-slate-900 mt-2"><?= number_format((int) $card['value'], 0, ',', '.') ?></p>
                        <p class="text-xs text-slate-400 mt-2"><?= e($card['desc']) ?></p>
                    </div>
                    <div class="w-12 h-12 rounded-2xl border flex items-center justify-center shrink-0 <?= e($card['tone']) ?>">
                        <i class="fa-solid <?= e($card['icon']) ?>"></i>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </section>

    <section class="bg-white rounded-[1.75rem] border border-slate-100 shadow-soft overflow-hidden">
        <div class="px-6 py-5 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="text-lg font-black text-slate-900">Aktivitas Terbaru Saya</h3>
                <p class="text-sm text-slate-500 mt-1">Urutan berdasarkan waktu terbaru.</p>
            </div>
            <a href="main.php?page=upload" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-2xl bg-teal-600 text-white text-xs font-bold hover:bg-teal-700 transition">
                <i class="fa-solid fa-cloud-arrow-up"></i> Upload dokumen
            </a>
        </div>

        <?php if (!$activities): ?>
            <div class="p-10 text-center text-slate-400">
                <i class="fa-regular fa-clock text-5xl mb-4"></i>
                <p class="font-semibold text-slate-500">Belum ada aktivitas.</p>
                <p class="text-sm mt-1">Aktivitas akan muncul setelah Anda mengupload dokumen atau status dokumen diperbarui admin.</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-100">
                <?php foreach ($activities as $activity): ?>
                    <div class="px-6 py-5 hover:bg-cyan-50/45 transition">
                        <div class="flex gap-4">
                            <div class="w-12 h-12 rounded-2xl border flex items-center justify-center shrink-0 <?= e($activity['tone']) ?>">
                                <i class="fa-solid <?= e($activity['icon']) ?>"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-[10px] font-black uppercase tracking-[.16em] text-teal-700 bg-teal-50 border border-teal-100 px-2.5 py-1 rounded-full"><?= e($activity['badge']) ?></span>
                                            <span class="text-xs text-slate-400"><?= e(appFormatDateTime($activity['time'])) ?></span>
                                        </div>
                                        <h4 class="font-bold text-slate-900 mt-1.5"><?= e($activity['title']) ?></h4>
                                    </div>
                                    <span class="w-fit px-3 py-1 rounded-full text-xs font-bold <?= e($activity['status_class']) ?>"><?= e($activity['status']) ?></span>
                                </div>
                                <p class="text-sm text-slate-600 mt-2 leading-relaxed"><?= e($activity['description']) ?></p>
                                <p class="text-xs text-slate-400 mt-1.5"><?= e($activity['meta']) ?></p>
                                <?php if ($activity['note'] !== ''): ?>
                                    <p class="text-xs text-slate-600 bg-slate-50 border border-slate-100 rounded-2xl p-3 mt-3"><?= e($activity['note']) ?></p>
                                <?php endif; ?>
                                <div class="flex flex-wrap gap-2 mt-3">
                                    <a href="<?= e($activity['link']) ?>" class="inline-flex items-center gap-2 px-3 py-2 rounded-2xl bg-slate-50 border border-slate-100 text-slate-600 text-xs font-bold hover:bg-slate-100 transition">
                                        <i class="fa-solid fa-folder-open"></i> Lihat pustaka
                                    </a>
                                    <?php if ($activity['revision_link'] !== ''): ?>
                                        <a href="<?= e($activity['revision_link']) ?>" class="inline-flex items-center gap-2 px-3 py-2 rounded-2xl bg-amber-50 border border-amber-100 text-amber-700 text-xs font-bold hover:bg-amber-100 transition">
                                            <i class="fa-solid fa-cloud-arrow-up"></i> Upload revisi
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
