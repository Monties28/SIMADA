<?php
/* =========================================================
   SMART OCR DOKUMEN SIMADA
   Catatan: SIMADA mencoba pdftotext untuk PDF digital lalu Tesseract OCR untuk PDF scan. Input manual tetap tersedia sebagai cadangan.
========================================================= */

declare(strict_types=1);

$db = appDb();
$flashMsg = '';
$flashMsgType = 'success';

if (!$db) {
    echo '<div class="rounded-2xl bg-red-50 border border-red-200 text-red-700 p-4">Koneksi database tidak tersedia.</div>';
    return;
}
appEnsureSimadaCoreSchema($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $documentId = (int) ($_POST['document_id'] ?? 0);

    if ($documentId <= 0) {
        $flashMsg = 'Pilih dokumen terlebih dahulu.';
        $flashMsgType = 'error';
    } else {
        $document = null;
        $stmt = $db->prepare('SELECT id, user_id, file_name, file_path, file_extension, encryption_enabled, encryption_iv, encryption_tag FROM documents WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $documentId);
            $stmt->execute();
            $resultDoc = $stmt->get_result();
            $document = $resultDoc ? $resultDoc->fetch_assoc() : null;
            $stmt->close();
        }

        if (!$document) {
            $flashMsg = 'Dokumen tidak ditemukan.';
            $flashMsgType = 'error';
        } else {
            $text = '';
            $engine = 'manual';

            if ($action === 'auto_ocr') {
                $absolutePath = appResolveDocumentAbsolutePath((string) ($document['file_path'] ?? ''), false);
                if (!$absolutePath || !is_file($absolutePath)) {
                    $flashMsg = 'File fisik tidak ditemukan.';
                    $flashMsgType = 'error';
                } else {
                    $extract = appExtractTextFromPdfOptional($db, $absolutePath, $document);
                    if (($extract['ok'] ?? false) && trim((string) ($extract['text'] ?? '')) !== '') {
                        $text = trim((string) $extract['text']);
                        $engine = (string) ($extract['engine'] ?? 'pdftotext');
                    } else {
                        $flashMsg = (string) ($extract['message'] ?? 'OCR otomatis gagal.');
                        $flashMsgType = 'error';
                    }
                }
            }

            if ($action === 'manual_ocr') {
                $text = trim((string) ($_POST['manual_text'] ?? ''));
                $engine = 'manual';
                if ($text === '') {
                    $flashMsg = 'Teks manual tidak boleh kosong.';
                    $flashMsgType = 'error';
                }
            }

            if ($text !== '') {
                $stmtUpdate = $db->prepare("UPDATE documents SET ocr_text = ?, ocr_status = 'processed', ocr_engine = ?, ocr_processed_at = NOW(), updated_at = NOW() WHERE id = ?");
                if ($stmtUpdate) {
                    $stmtUpdate->bind_param('ssi', $text, $engine, $documentId);
                    $stmtUpdate->execute();
                    $stmtUpdate->close();
                    $flashMsg = 'Teks OCR berhasil disimpan dan siap dipakai untuk Information Retrieval TF-IDF.';
                    $flashMsgType = 'success';
                }
            }
        }
    }
}

$docs = [];
$result = $db->query("SELECT id, file_name, kategori_dokumen, jenis_dokumen, status, ocr_status, ocr_engine, ocr_processed_at FROM documents WHERE LOWER(TRIM(status)) IN ('approved','disetujui','pending','revision','revisi') ORDER BY updated_at DESC, id DESC LIMIT 100");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $docs[] = $row;
    }
}
?>

<div class="space-y-6">
    <?php if ($flashMsg !== ''): ?>
        <div class="rounded-2xl p-4 text-sm <?= $flashMsgType === 'error' ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-emerald-50 border border-emerald-200 text-emerald-700' ?>">
            <?= e($flashMsg) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-3xl border border-slate-200 shadow-soft p-6">
        <h3 class="text-xl font-bold text-slate-900">Smart OCR Dokumen</h3>
        <p class="text-sm text-slate-500 mt-1">SIMADA mencoba <code>pdftotext</code> terlebih dahulu. Jika PDF tidak memiliki lapisan teks, sistem otomatis mencoba <code>pdftoppm + Tesseract</code>.</p>

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
            <form method="POST" class="rounded-3xl border border-slate-200 p-5 space-y-4">
                <input type="hidden" name="action" value="auto_ocr">
                <h4 class="font-bold text-slate-800">Ekstraksi Otomatis</h4>
                <select name="document_id" required class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:border-primary">
                    <option value="">Pilih dokumen...</option>
                    <?php foreach ($docs as $doc): ?>
                        <option value="<?= (int) $doc['id'] ?>"><?= e(appDisplayFileName($doc['file_name'] ?? '')) ?> — <?= e($doc['ocr_status'] ?? 'not_processed') ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="w-full rounded-2xl bg-primary text-white px-4 py-3 text-sm font-semibold hover:bg-primaryDark transition">
                    Jalankan Ekstraksi Otomatis
                </button>
            </form>

            <form method="POST" class="rounded-3xl border border-slate-200 p-5 space-y-4">
                <input type="hidden" name="action" value="manual_ocr">
                <h4 class="font-bold text-slate-800">Input Teks Manual</h4>
                <select name="document_id" required class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:border-primary">
                    <option value="">Pilih dokumen...</option>
                    <?php foreach ($docs as $doc): ?>
                        <option value="<?= (int) $doc['id'] ?>"><?= e(appDisplayFileName($doc['file_name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
                <textarea name="manual_text" rows="8" required placeholder="Paste teks hasil OCR/manual di sini..." class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm focus:outline-none focus:border-primary"></textarea>
                <button class="w-full rounded-2xl border border-primary text-primary px-4 py-3 text-sm font-semibold hover:bg-primarySoft transition">
                    Simpan Teks Manual
                </button>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-3xl border border-slate-200 shadow-soft p-6 overflow-x-auto">
        <h3 class="text-lg font-bold text-slate-900 mb-4">Status OCR</h3>
        <table class="w-full text-sm">
            <thead class="text-left text-slate-500 border-b">
                <tr><th class="py-3">Dokumen</th><th>Kategori</th><th>Status OCR</th><th>Engine</th><th>Diproses</th></tr>
            </thead>
            <tbody class="divide-y">
                <?php foreach ($docs as $doc): ?>
                    <tr>
                        <td class="py-3 font-medium text-slate-800"><?= e(appDisplayFileName($doc['file_name'] ?? '')) ?></td>
                        <td><?= e($doc['kategori_dokumen'] ?? '-') ?></td>
                        <td><?= e($doc['ocr_status'] ?? 'not_processed') ?></td>
                        <td><?= e($doc['ocr_engine'] ?? '-') ?></td>
                        <td><?= e(appFormatDateTime($doc['ocr_processed_at'] ?? null)) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
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

