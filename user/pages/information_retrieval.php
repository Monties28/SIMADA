<?php
/* =========================================================
   INFORMATION RETRIEVAL TF-IDF SIMADA
========================================================= */

declare(strict_types=1);

$db = appDb();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$role = (string) ($_SESSION['role'] ?? '');
$query = trim((string) ($_GET['ir_q'] ?? ''));
$ranked = [];
$totalCorpus = 0;

if (!$db) {
    echo '<div class="rounded-2xl bg-red-50 border border-red-200 text-red-700 p-4">Koneksi database tidak tersedia.</div>';
    return;
}
appEnsureSimadaCoreSchema($db);

if ($role === 'admin') {
    $stmt = $db->prepare("SELECT id, user_id, file_name, kategori_dokumen, jenis_dokumen, status, created_at, COALESCE(ocr_text, '') AS ocr_text FROM documents WHERE LOWER(TRIM(status)) IN ('approved','disetujui','pending','revision','revisi','rejected','ditolak') ORDER BY updated_at DESC LIMIT 300");
} else {
    $stmt = $db->prepare("SELECT id, user_id, file_name, kategori_dokumen, jenis_dokumen, status, created_at, COALESCE(ocr_text, '') AS ocr_text FROM documents WHERE user_id = ? AND LOWER(TRIM(status)) IN ('approved','disetujui') ORDER BY updated_at DESC LIMIT 300");
    if ($stmt) { $stmt->bind_param('i', $userId); }
}

$documents = [];
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $row['search_text'] = implode(' ', [
            $row['file_name'] ?? '',
            $row['kategori_dokumen'] ?? '',
            $row['jenis_dokumen'] ?? '',
            $row['ocr_text'] ?? '',
        ]);
        $documents[] = $row;
    }
    $stmt->close();
}
$totalCorpus = count($documents);

if ($query !== '') {
    $ranked = appTfIdfRankDocuments($query, $documents);
}
?>

<div class="space-y-6">
    <div class="bg-white rounded-3xl border border-slate-200 shadow-soft p-6">
        <h3 class="text-xl font-bold text-slate-900">Information Retrieval TF-IDF</h3>
        <p class="text-sm text-slate-500 mt-1">Pencarian dokumen berbasis bobot kata. Hasil makin akurat jika teks OCR sudah tersedia.</p>
        <form method="GET" action="main.php" class="mt-6 grid grid-cols-1 md:grid-cols-[1fr_auto] gap-3">
            <input type="hidden" name="page" value="information_retrieval">
            <input name="ir_q" value="<?= e($query) ?>" placeholder="Contoh: laporan absensi bulan mei pegawai terlambat" class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:border-primary">
            <button class="rounded-2xl bg-primary text-white px-6 py-3 text-sm font-semibold hover:bg-primaryDark transition">
                Cari TF-IDF
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-3xl border border-slate-200 shadow-soft p-5">
            <p class="text-sm text-slate-500">Corpus Dokumen</p>
            <h3 class="text-2xl font-bold text-slate-900 mt-1"><?= number_format($totalCorpus) ?></h3>
        </div>
        <div class="bg-white rounded-3xl border border-slate-200 shadow-soft p-5">
            <p class="text-sm text-slate-500">Hasil Relevan</p>
            <h3 class="text-2xl font-bold text-primary mt-1"><?= number_format(count($ranked)) ?></h3>
        </div>
        <div class="bg-white rounded-3xl border border-slate-200 shadow-soft p-5">
            <p class="text-sm text-slate-500">Mode</p>
            <h3 class="text-lg font-bold text-slate-900 mt-2"><?= $role === 'admin' ? 'Semua Dokumen' : 'Dokumen Disetujui Saya' ?></h3>
        </div>
    </div>

    <div class="bg-white rounded-3xl border border-slate-200 shadow-soft p-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-slate-900">Ranking Hasil</h3>
            <?php if ($query !== ''): ?><span class="text-sm text-slate-500">Query: <?= e($query) ?></span><?php endif; ?>
        </div>

        <?php if ($query === ''): ?>
            <div class="rounded-2xl bg-slate-50 border border-slate-200 p-6 text-center text-slate-500">Masukkan kata kunci untuk mulai mencari.</div>
        <?php elseif (!$ranked): ?>
            <div class="rounded-2xl bg-yellow-50 border border-yellow-200 p-6 text-center text-yellow-700">Tidak ada dokumen relevan. Jalankan OCR atau gunakan kata kunci lain.</div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach (array_slice($ranked, 0, 25) as $doc): ?>
                    <div class="rounded-3xl border border-slate-200 p-5 hover:border-primary/30 transition">
                        <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-3">
                            <div>
                                <h4 class="font-bold text-slate-900"><?= e(appDisplayFileName($doc['file_name'] ?? '')) ?></h4>
                                <p class="text-sm text-slate-500 mt-1"><?= e($doc['kategori_dokumen'] ?? '-') ?> • <?= e($doc['jenis_dokumen'] ?? '-') ?> • <?= e(appDocumentStatusLabel((string) ($doc['status'] ?? ''))) ?></p>
                                <?php if (trim((string) ($doc['ocr_text'] ?? '')) !== ''): ?>
                                    <p class="text-xs text-emerald-600 mt-2"><i class="fa-solid fa-file-lines"></i> OCR tersedia</p>
                                <?php else: ?>
                                    <p class="text-xs text-slate-400 mt-2"><i class="fa-regular fa-file-lines"></i> Menggunakan metadata karena OCR belum tersedia</p>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="px-3 py-1.5 rounded-2xl bg-primarySoft text-primary text-sm font-bold">Score <?= e((string) ($doc['ir_score'] ?? 0)) ?></span>
                                <a href="../download.php?id=<?= (int) $doc['id'] ?>" class="px-3 py-1.5 rounded-2xl border border-slate-200 text-sm hover:bg-slate-50">Unduh</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

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

