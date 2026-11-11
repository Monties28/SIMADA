<?php
/* =========================================================
   MANAJEMEN DOKUMEN ADMIN
   Fungsi: Memproses persetujuan, revisi, penolakan, filter data, pagination, dan tampilan daftar dokumen.
========================================================= */

declare(strict_types=1);
/*
 * admin/pages/manajemen_dokumen.php
 * Fragment — di-include oleh admin/main.php
 * - Ambil data dari database
 * - Approve / reject dokumen
 * - Search dokumen
 * - Filter status dokumen
 * - Ringkasan total dokumen
 * - Pagination
 */

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

appEnsureSimadaCoreSchema($db);

$successMsg = '';
$errorMsg   = '';

/* =========================
   HELPER REDIRECT
========================= */
/* =========================================================
   BAGIAN FUNGSI PENDUKUNG
   Bagian ini berisi fungsi untuk redirect, validasi status, path file, dan pemindahan dokumen.
========================================================= */
function redirectManageDoc(string $msg = '', string $q = '', string $status = '', int $pageNum = 1): void
{
    $params = ['page' => 'manajemen_dokumen'];

    if ($q !== '') {
        $params['q'] = $q;
    }

    if ($status !== '') {
        $params['status'] = $status;
    }

    if ($pageNum > 1) {
        $params['page_num'] = $pageNum;
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
   HELPER TAMPILAN NAMA FILE
========================= */

/* =========================
   HELPER FSM DOKUMEN
========================= */






appEnsureDocumentRevisionColumns($db);
$hasCatatanAdminColumn = appTableHasColumn($db, 'documents', 'catatan_admin');
$hasRevisionCountColumn = appTableHasColumn($db, 'documents', 'revision_count');
$hasLastRevisionAtColumn = appTableHasColumn($db, 'documents', 'last_revision_at');

/* =========================
   PROSES UPDATE STATUS
========================= */
/* =========================================================
   BAGIAN PROSES AKSI ADMIN
   Bagian ini memproses keputusan admin: setujui, minta revisi, atau tolak dokumen.
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $documentId    = (int) ($_POST['document_id'] ?? 0);
    $newStatus     = appNormalizeDocumentState(trim($_POST['new_status'] ?? ''));
    $adminNote = trim((string) ($_POST['admin_note'] ?? ($_POST['rejection_note'] ?? '')));
    $qPost         = trim($_POST['q'] ?? '');
    $statusPost    = strtolower(trim($_POST['status_filter'] ?? ''));
    $pagePost      = isset($_POST['page_num']) ? max(1, (int) $_POST['page_num']) : 1;

    $allowedStatus = ['Pending', 'Approved', 'Revision', 'Rejected'];

    if ($documentId <= 0) {
        redirectManageDoc('invalid_document', $qPost, $statusPost, $pagePost);
    }

    if (!in_array($newStatus, $allowedStatus, true)) {
        redirectManageDoc('invalid_status', $qPost, $statusPost, $pagePost);
    }

    if (in_array($newStatus, ['Revision', 'Rejected'], true) && $adminNote === '') {
        redirectManageDoc('missing_note', $qPost, $statusPost, $pagePost);
    }

    $stmtDoc = $db->prepare("
        SELECT 
            d.id, 
            d.user_id, 
            d.file_name,
            d.stored_file_name, 
            d.file_path, 
            d.file_size,
            d.file_hash,
            d.kategori_dokumen,
            d.jenis_dokumen,
            d.jenis_dokumen_lainnya,
            d.tanggal_dokumen,
            d.created_at,
            d.periode_bulan, 
            d.status,
            d.revision_count,
            u.name AS user_name
        FROM documents d
        LEFT JOIN users u ON u.id = d.user_id
        WHERE d.id = ? 
        LIMIT 1
    ");

    if (!$stmtDoc) {
        redirectManageDoc('update_failed', $qPost, $statusPost, $pagePost);
    }

    $stmtDoc->bind_param('i', $documentId);
    $stmtDoc->execute();

    $resultDoc = $stmtDoc->get_result();
    $doc = $resultDoc ? $resultDoc->fetch_assoc() : null;

    $stmtDoc->close();

    if (!$doc) {
        redirectManageDoc('invalid_document', $qPost, $statusPost, $pagePost);
    }

    $currentStatus = appNormalizeDocumentState((string) ($doc['status'] ?? 'Pending'));

    if (!appCanChangeDocumentState($currentStatus, $newStatus)) {
        redirectManageDoc('invalid_transition', $qPost, $statusPost, $pagePost);
    }

    if ($newStatus === 'Revision' && (int) ($doc['revision_count'] ?? 0) >= appMaxDocumentRevisions()) {
        redirectManageDoc('revision_limit', $qPost, $statusPost, $pagePost);
    }

    $moveResult = appMoveDocumentByState($doc, $newStatus);

    if (!$moveResult['ok']) {
        redirectManageDoc('move_failed', $qPost, $statusPost, $pagePost);
    }

    $newPath = $moveResult['path'];
    $newStoredFileName = (string) ($moveResult['stored_file_name'] ?? ($doc['stored_file_name'] ?? ''));

    if ($hasCatatanAdminColumn) {
        $catatanAdmin = match ($newStatus) {
            'Revision', 'Rejected' => $adminNote,
            'Pending' => ($adminNote !== '' ? $adminNote : 'Persetujuan dibatalkan oleh admin.'),
            default => '',
        };

        $stmt = $db->prepare("
            UPDATE documents 
            SET status = ?, 
                stored_file_name = ?,
                file_path = ?, 
                catatan_admin = ?, 
                updated_at = NOW() 
            WHERE id = ?
        ");

        if (!$stmt) {
            redirectManageDoc('update_failed', $qPost, $statusPost, $pagePost);
        }

        $stmt->bind_param("ssssi", $newStatus, $newStoredFileName, $newPath, $catatanAdmin, $documentId);
    } else {
        $stmt = $db->prepare("
            UPDATE documents 
            SET status = ?, 
                stored_file_name = ?,
                file_path = ?, 
                updated_at = NOW() 
            WHERE id = ?
        ");

        if (!$stmt) {
            redirectManageDoc('update_failed', $qPost, $statusPost, $pagePost);
        }

        $stmt->bind_param("sssi", $newStatus, $newStoredFileName, $newPath, $documentId);
    }

    $ok = $stmt->execute();
    $stmt->close();

    if ($ok) {
        $notifTitle = match ($newStatus) {
            'Approved' => 'Dokumen disetujui',
            'Revision' => 'Dokumen perlu revisi',
            'Rejected' => 'Dokumen ditolak',
            default => 'Persetujuan dokumen dibatalkan',
        };
        $notifMessage = 'Dokumen ' . appDisplayFileName((string) ($doc['file_name'] ?? '')) . ' berstatus ' . appDocumentStatusLabel($newStatus) . ($adminNote !== '' ? '. Catatan: ' . $adminNote : '.');
        appCreateNotification($db, (int) ($doc['user_id'] ?? 0), null, $notifTitle, $notifMessage, '../user/main.php?page=pustaka');

        redirectManageDoc(match ($newStatus) {
            'Approved' => 'approved',
            'Revision' => 'revision',
            'Rejected' => 'rejected',
            default => 'pending',
        }, $qPost, $statusPost, $pagePost);
    }

    redirectManageDoc('update_failed', $qPost, $statusPost, $pagePost);
}

/* =========================
   FLASH MESSAGE
========================= */
$msg = $_GET['msg'] ?? '';
$q   = trim($_GET['q'] ?? '');

$statusFilter = strtolower(trim($_GET['status'] ?? ''));

$allowedStatusFilter = ['pending', 'revision', 'approved', 'rejected'];

if (!in_array($statusFilter, $allowedStatusFilter, true)) {
    $statusFilter = '';
}

switch ($msg) {
    case 'approved':
        $successMsg = 'Dokumen berhasil disetujui.';
        break;

    case 'revision':
        $successMsg = 'Dokumen berhasil diminta revisi.';
        break;

    case 'rejected':
        $successMsg = 'Dokumen berhasil ditolak.';
        break;

    case 'pending':
        $successMsg = 'Persetujuan berhasil dibatalkan. Dokumen kembali ke status Pending.';
        break;

    case 'invalid_document':
        $errorMsg = 'Dokumen tidak valid.';
        break;

    case 'invalid_status':
        $errorMsg = 'Status tidak valid.';
        break;

    case 'invalid_transition':
        $errorMsg = 'Transisi status tidak valid atau status dokumen sudah sama. Admin dapat membatalkan persetujuan atau mengubah status dokumen yang sudah diproses.';
        break;

    case 'missing_note':
        $errorMsg = 'Catatan admin wajib diisi untuk status Perlu Revisi atau Ditolak.';
        break;

    case 'revision_limit':
        $errorMsg = 'Batas maksimal revisi adalah ' . appMaxDocumentRevisions() . ' kali. Admin hanya dapat menyetujui atau menolak dokumen ini.';
        break;

    case 'move_failed':
        $errorMsg = 'Gagal memindahkan file sesuai status dokumen.';
        break;

    case 'duplicate_file':
        $errorMsg = 'Dokumen tidak dapat disetujui karena file yang sama sudah pernah disetujui sebelumnya.';
        break;

    case 'update_failed':
        $errorMsg = 'Gagal memperbarui status dokumen.';
        break;
}

/* =========================
   RINGKASAN DOKUMEN
========================= */
$totalDocuments = 0;
$totalPending   = 0;
$totalApproved  = 0;
$totalRevision  = 0;
$totalRejected  = 0;

$resultCount = $db->query("
    SELECT
        COUNT(*) AS total_documents,

        SUM(
            CASE 
                WHEN LOWER(TRIM(status)) IN ('approved', 'disetujui') 
                THEN 1 ELSE 0 
            END
        ) AS total_approved,

        SUM(
            CASE 
                WHEN LOWER(TRIM(status)) IN ('revision', 'revisi') 
                THEN 1 ELSE 0 
            END
        ) AS total_revision,

        SUM(
            CASE 
                WHEN LOWER(TRIM(status)) IN ('rejected', 'ditolak') 
                THEN 1 ELSE 0 
            END
        ) AS total_rejected,

        SUM(
            CASE 
                WHEN status IS NULL 
                  OR TRIM(status) = ''
                  OR LOWER(TRIM(status)) NOT IN ('approved', 'disetujui', 'revision', 'revisi', 'rejected', 'ditolak')
                THEN 1 ELSE 0 
            END
        ) AS total_pending

    FROM documents
");

if ($resultCount && $rowCount = $resultCount->fetch_assoc()) {
    $totalDocuments = (int) ($rowCount['total_documents'] ?? 0);
    $totalPending   = (int) ($rowCount['total_pending'] ?? 0);
    $totalApproved  = (int) ($rowCount['total_approved'] ?? 0);
    $totalRevision  = (int) ($rowCount['total_revision'] ?? 0);
    $totalRejected  = (int) ($rowCount['total_rejected'] ?? 0);
}

$catatanSelect = $hasCatatanAdminColumn ? 'd.catatan_admin' : "'' AS catatan_admin";
$revisionCountSelect = $hasRevisionCountColumn ? 'd.revision_count' : '0 AS revision_count';
$lastRevisionAtSelect = $hasLastRevisionAtColumn ? 'd.last_revision_at' : 'NULL AS last_revision_at';

/* =========================
   FILTER DATA
========================= */
$documents = [];

$where  = [];
$params = [];
$types  = '';

if ($q !== '') {
    $search = '%' . $q . '%';

    $where[] = "(
        REPLACE(d.file_name, '_', ' ') LIKE ?
        OR d.kategori_dokumen LIKE ?
        OR d.jenis_dokumen LIKE ?
        OR d.jenis_dokumen_lainnya LIKE ?
        OR u.name LIKE ?
        OR u.jabatan LIKE ?
        OR d.status LIKE ?
    )";

    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;

    $types .= 'sssssss';
}

if ($statusFilter === 'pending') {
    $where[] = "(
        d.status IS NULL 
        OR TRIM(d.status) = ''
        OR LOWER(TRIM(d.status)) NOT IN ('approved', 'disetujui', 'revision', 'revisi', 'rejected', 'ditolak')
    )";
} elseif ($statusFilter === 'revision') {
    $where[] = "LOWER(TRIM(d.status)) IN ('revision', 'revisi')";
} elseif ($statusFilter === 'approved') {
    $where[] = "LOWER(TRIM(d.status)) IN ('approved', 'disetujui')";
} elseif ($statusFilter === 'rejected') {
    $where[] = "LOWER(TRIM(d.status)) IN ('rejected', 'ditolak')";
}

$whereSql = '';

if (!empty($where)) {
    $whereSql = 'WHERE ' . implode(' AND ', $where);
}

/* =========================
   PAGINATION
========================= */
$limit = 10;
$pageNum = isset($_GET['page_num']) ? max(1, (int) $_GET['page_num']) : 1;
$offset = ($pageNum - 1) * $limit;

$totalData = 0;
$totalPages = 1;

$sqlCount = "
    SELECT COUNT(*) AS total
    FROM documents d
    LEFT JOIN users u ON u.id = d.user_id
    {$whereSql}
";

$stmtCount = $db->prepare($sqlCount);

if ($stmtCount) {
    if (!empty($params)) {
        $countParams = $params;
        $countTypes  = $types;
        appBindParams($stmtCount, $countTypes, $countParams);
    }

    $stmtCount->execute();
    $resultCountDocs = $stmtCount->get_result();
    $rowTotal = $resultCountDocs ? $resultCountDocs->fetch_assoc() : null;

    $totalData = (int) ($rowTotal['total'] ?? 0);
    $stmtCount->close();
}

$totalPages = max(1, (int) ceil($totalData / $limit));

if ($pageNum > $totalPages) {
    $pageNum = $totalPages;
    $offset = ($pageNum - 1) * $limit;
}

/* =========================
   DATA DOKUMEN
========================= */
$sqlDocs = "
    SELECT
        d.id,
        d.user_id,
        d.file_name,
        d.file_path,
        d.kategori_dokumen,
        d.jenis_dokumen,
        d.jenis_dokumen_lainnya,
        d.file_extension,
        d.file_size,
        d.file_hash,
        d.created_at,
        d.status,
        0 AS approved_duplicate_count,
        {$catatanSelect},
        {$revisionCountSelect},
        {$lastRevisionAtSelect},
        u.name,
        u.jabatan
    FROM documents d
    LEFT JOIN users u ON u.id = d.user_id
    {$whereSql}
    ORDER BY 
        CASE
            WHEN d.status IS NULL OR TRIM(d.status) = '' THEN 1
            WHEN LOWER(TRIM(d.status)) = 'pending' THEN 1
            WHEN LOWER(TRIM(d.status)) NOT IN ('approved', 'disetujui', 'revision', 'revisi', 'rejected', 'ditolak') THEN 1
            WHEN LOWER(TRIM(d.status)) IN ('revision', 'revisi') THEN 2
            WHEN LOWER(TRIM(d.status)) IN ('rejected', 'ditolak') THEN 3
            WHEN LOWER(TRIM(d.status)) IN ('approved', 'disetujui') THEN 4
            ELSE 5
        END,
        d.created_at DESC,
        d.id DESC
    LIMIT ? OFFSET ?
";

$stmtDocs = $db->prepare($sqlDocs);

if ($stmtDocs) {
    $docParams = $params;
    $docTypes  = $types;

    $docParams[] = $limit;
    $docParams[] = $offset;
    $docTypes .= 'ii';

    appBindParams($stmtDocs, $docTypes, $docParams);

    $stmtDocs->execute();
    $resultDocs = $stmtDocs->get_result();

    while ($row = $resultDocs->fetch_assoc()) {
        $documents[] = $row;
    }

    $stmtDocs->close();
}
/* =========================================================
   BAGIAN TAMPILAN
   Bagian setelah tanda penutup PHP berisi HTML, CSS, atau JavaScript
   yang membentuk tampilan halaman untuk pengguna.
========================================================= */
?>

<style>
/* =========================================================
   UI MANAJEMEN DOKUMEN — minimal, jelas, light/dark friendly
   Hanya presentasi. Proses/query dokumen tetap ditangani PHP di atas.
========================================================= */
.doc-manager {
    --dm-card: var(--surface, #ffffff);
    --dm-strong: var(--surface-strong, #ffffff);
    --dm-border: var(--line, #e2e8f0);
    --dm-border-strong: var(--line-strong, #cbd5e1);
    --dm-text: var(--app-ink, #172b2a);
    --dm-muted: var(--app-muted, #64748b);
    --dm-soft: #f7fbfa;
    --dm-teal: #159a93;
    --dm-teal-dark: #117b76;
    --dm-teal-soft: #e9f8f6;
    color: var(--dm-text);
}
html[data-theme="dark"] .doc-manager {
    --dm-soft: rgba(255,255,255,.045);
    --dm-teal-soft: rgba(38,198,190,.12);
}
.doc-shell,
.doc-card,
.doc-filter-panel,
.doc-stat {
    background: var(--dm-card);
    border: 1px solid var(--dm-border);
}
.doc-shell { border-radius: 22px; overflow: hidden; }
.doc-filter-panel { border-radius: 20px; padding: 18px; }
.doc-stat {
    border-radius: 18px;
    padding: 16px;
    min-height: 104px;
    transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
}
.doc-stat:hover {
    transform: translateY(-2px);
    border-color: rgba(21,154,147,.26);
    box-shadow: 0 12px 30px rgba(15,23,42,.06);
}
html[data-theme="dark"] .doc-stat:hover { box-shadow: 0 12px 30px rgba(0,0,0,.16); }
.doc-stat-icon {
    width: 42px;
    height: 42px;
    border-radius: 13px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
}
.doc-card {
    border-radius: 20px;
    padding: 18px;
    transition: transform .2s ease, border-color .2s ease, box-shadow .2s ease;
    animation: docEnter .34s ease both;
}
.doc-card:hover {
    transform: translateY(-1px);
    border-color: rgba(21,154,147,.24);
    box-shadow: 0 14px 34px rgba(15,23,42,.055);
}
html[data-theme="dark"] .doc-card:hover { box-shadow: 0 14px 34px rgba(0,0,0,.16); }
@keyframes docEnter {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
.doc-file-icon {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    background: var(--dm-teal-soft);
    color: var(--dm-teal);
    font-size: 1.05rem;
}
.doc-meta-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
}
.doc-meta {
    min-width: 0;
    padding: 11px 12px;
    border-radius: 14px;
    background: var(--dm-soft);
    border: 1px solid var(--dm-border);
}
.doc-meta-label {
    font-size: 10px;
    line-height: 1.35;
    text-transform: uppercase;
    letter-spacing: .07em;
    font-weight: 800;
    color: var(--dm-muted);
}
.doc-meta-value {
    margin-top: 4px;
    font-size: 12px;
    line-height: 1.45;
    font-weight: 650;
    color: var(--dm-text);
    overflow-wrap: anywhere;
}
.doc-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    min-height: 28px;
    padding: 5px 10px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 750;
    border: 1px solid var(--dm-border);
    background: var(--dm-soft);
    color: var(--dm-muted);
}
.doc-status {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    min-height: 30px;
    padding: 6px 11px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 800;
    white-space: nowrap;
}
.doc-status::before {
    content: '';
    width: 7px;
    height: 7px;
    border-radius: 999px;
    background: currentColor;
}
.doc-status-approved { background: rgba(22,163,74,.11); color: #15803d; border: 1px solid rgba(22,163,74,.16); }
.doc-status-revision { background: rgba(21,154,147,.11); color: #117b76; border: 1px solid rgba(21,154,147,.18); }
.doc-status-rejected { background: rgba(220,38,38,.09); color: #dc2626; border: 1px solid rgba(220,38,38,.15); }
.doc-status-pending { background: rgba(217,119,6,.10); color: #b45309; border: 1px solid rgba(217,119,6,.16); }
html[data-theme="dark"] .doc-status-approved { color: #86efac; background: rgba(34,197,94,.13); }
html[data-theme="dark"] .doc-status-revision { color: #5eead4; background: rgba(20,184,166,.13); }
html[data-theme="dark"] .doc-status-rejected { color: #fca5a5; background: rgba(239,68,68,.12); }
html[data-theme="dark"] .doc-status-pending { color: #fcd34d; background: rgba(245,158,11,.12); }
.doc-btn {
    min-height: 38px;
    padding: 8px 12px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    font-size: 12px;
    line-height: 1;
    font-weight: 750;
    border: 1px solid var(--dm-border);
    transition: transform .17s ease, background .17s ease, border-color .17s ease, color .17s ease;
}
.doc-btn:hover { transform: translateY(-1px); }
.doc-btn:active { transform: translateY(0) scale(.98); }
.doc-btn-primary { background: var(--dm-teal); color: #fff; border-color: var(--dm-teal); }
.doc-btn-primary:hover { background: var(--dm-teal-dark); border-color: var(--dm-teal-dark); }
.doc-btn-soft { background: var(--dm-strong); color: var(--dm-text); }
.doc-btn-soft:hover { background: var(--dm-soft); border-color: var(--dm-border-strong); }
.doc-btn-success { background: #16a34a; color: #fff; border-color: #16a34a; }
.doc-btn-success:hover { background: #15803d; border-color: #15803d; }
.doc-btn-danger { color: #dc2626; background: rgba(220,38,38,.055); border-color: rgba(220,38,38,.20); }
.doc-btn-danger:hover { background: rgba(220,38,38,.10); }
.doc-btn-warning { color: #a16207; background: rgba(245,158,11,.07); border-color: rgba(245,158,11,.22); }
.doc-btn-warning:hover { background: rgba(245,158,11,.13); }
html[data-theme="dark"] .doc-btn-danger { color: #fca5a5; }
html[data-theme="dark"] .doc-btn-warning { color: #fcd34d; }
.doc-actions {
    margin-top: 14px;
    padding-top: 14px;
    border-top: 1px solid var(--dm-border);
}
.doc-action-details {
    border: 1px solid var(--dm-border);
    border-radius: 14px;
    background: var(--dm-soft);
    overflow: hidden;
}
.doc-action-details > summary {
    list-style: none;
    cursor: pointer;
    min-height: 38px;
    padding: 9px 12px;
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 12px;
    font-weight: 750;
    color: var(--dm-text);
    user-select: none;
}
.doc-action-details > summary::-webkit-details-marker { display: none; }
.doc-action-details > summary::after {
    content: '\f078';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    margin-left: auto;
    font-size: 10px;
    color: var(--dm-muted);
    transition: transform .2s ease;
}
.doc-action-details[open] > summary::after { transform: rotate(180deg); }
.doc-action-body {
    padding: 0 12px 12px;
    animation: docPanelIn .18s ease both;
}
@keyframes docPanelIn { from { opacity:0; transform: translateY(-4px); } to { opacity:1; transform:none; } }
.doc-manager input,
.doc-manager select,
.doc-manager textarea {
    background: var(--dm-strong) !important;
    color: var(--dm-text) !important;
    border-color: var(--dm-border-strong) !important;
}
.doc-manager input::placeholder,
.doc-manager textarea::placeholder { color: var(--dm-muted) !important; opacity: .8; }
.doc-manager input:focus,
.doc-manager select:focus,
.doc-manager textarea:focus {
    outline: none !important;
    border-color: rgba(21,154,147,.65) !important;
    box-shadow: 0 0 0 3px rgba(21,154,147,.10) !important;
}
.doc-empty {
    padding: 46px 20px;
    text-align: center;
    border: 1px dashed var(--dm-border-strong);
    border-radius: 18px;
    background: var(--dm-soft);
}
.doc-note {
    margin-top: 12px;
    padding: 11px 12px;
    border-radius: 14px;
    border: 1px solid var(--dm-border);
    background: var(--dm-soft);
    color: var(--dm-muted);
    font-size: 12px;
    line-height: 1.55;
}
.doc-pagination a,
.doc-pagination span { transition: transform .17s ease, background .17s ease; }
.doc-pagination a:hover { transform: translateY(-1px); }
@media (max-width: 1023px) { .doc-meta-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 639px) {
    .doc-card { padding: 14px; border-radius: 17px; }
    .doc-filter-panel { padding: 14px; border-radius: 17px; }
    .doc-meta-grid { grid-template-columns: 1fr; gap: 8px; }
    .doc-btn { min-height: 40px; }
}
@media (prefers-reduced-motion: reduce) {
    .doc-card, .doc-stat, .doc-action-body { animation: none !important; transition: none !important; }
}
</style>

<div class="doc-manager space-y-5">

    <?php if ($successMsg): ?>
        <div class="p-4 rounded-2xl border border-green-200 bg-green-50 text-green-700 text-sm flex items-start gap-3">
            <i class="fa-solid fa-circle-check mt-0.5"></i>
            <span><?= e($successMsg) ?></span>
        </div>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
        <div class="p-4 rounded-2xl border border-red-200 bg-red-50 text-red-700 text-sm flex items-start gap-3">
            <i class="fa-solid fa-circle-exclamation mt-0.5"></i>
            <span><?= e($errorMsg) ?></span>
        </div>
    <?php endif; ?>

    <!-- Ringkasan ringkas: angka mudah dipindai tanpa warna berlebihan -->
    <section class="grid grid-cols-2 lg:grid-cols-5 gap-3" aria-label="Ringkasan dokumen">
        <div class="doc-stat">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold text-slate-500">Total Dokumen</p>
                    <p class="text-2xl font-extrabold text-slate-900 mt-1"><?= number_format($totalDocuments) ?></p>
                </div>
                <span class="doc-stat-icon bg-primarySoft text-primary"><i class="fa-regular fa-folder-open"></i></span>
            </div>
        </div>
        <div class="doc-stat">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold text-slate-500">Pending</p>
                    <p class="text-2xl font-extrabold text-slate-900 mt-1"><?= number_format($totalPending) ?></p>
                </div>
                <span class="doc-stat-icon bg-yellow-50 text-yellow-600"><i class="fa-regular fa-clock"></i></span>
            </div>
        </div>
        <div class="doc-stat">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold text-slate-500">Disetujui</p>
                    <p class="text-2xl font-extrabold text-slate-900 mt-1"><?= number_format($totalApproved) ?></p>
                </div>
                <span class="doc-stat-icon bg-green-50 text-green-600"><i class="fa-regular fa-circle-check"></i></span>
            </div>
        </div>
        <div class="doc-stat">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold text-slate-500">Perlu Revisi</p>
                    <p class="text-2xl font-extrabold text-slate-900 mt-1"><?= number_format($totalRevision) ?></p>
                </div>
                <span class="doc-stat-icon bg-primarySoft text-primary"><i class="fa-solid fa-pen-to-square"></i></span>
            </div>
        </div>
        <div class="doc-stat col-span-2 lg:col-span-1">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold text-slate-500">Ditolak</p>
                    <p class="text-2xl font-extrabold text-slate-900 mt-1"><?= number_format($totalRejected) ?></p>
                </div>
                <span class="doc-stat-icon bg-red-50 text-red-600"><i class="fa-regular fa-circle-xmark"></i></span>
            </div>
        </div>
    </section>

    <!-- Search/filter dipisahkan dari daftar agar lebih mudah dipahami -->
    <section class="doc-filter-panel">
        <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
            <div class="min-w-0">
                <div class="flex items-center gap-2 text-primary text-xs font-extrabold uppercase tracking-[.09em]">
                    <i class="fa-solid fa-sliders"></i> Filter Dokumen
                </div>
                <h3 class="text-lg font-extrabold text-slate-900 mt-1">Cari dokumen yang ingin diproses</h3>
                <p class="text-sm text-slate-500 mt-1">Cari berdasarkan file, kategori, jenis, pengguna, jabatan, atau status.</p>
            </div>

            <?php if ($statusFilter !== '' || $q !== ''): ?>
                <a href="main.php?page=manajemen_dokumen" class="doc-btn doc-btn-soft shrink-0">
                    <i class="fa-solid fa-rotate-left"></i> Hapus Filter
                </a>
            <?php endif; ?>
        </div>

        <form method="GET" action="main.php" class="mt-4 grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_210px_auto] gap-3">
            <input type="hidden" name="page" value="manajemen_dokumen">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" name="q" value="<?= e($q) ?>" placeholder="Contoh: laporan produksi, Herwansyah, PDF..." class="w-full min-h-[44px] pl-11 pr-4 rounded-xl border text-sm">
            </div>
            <select name="status" class="w-full min-h-[44px] px-4 rounded-xl border text-sm">
                <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>Semua status</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="revision" <?= $statusFilter === 'revision' ? 'selected' : '' ?>>Perlu Revisi</option>
                <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Disetujui</option>
                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Ditolak</option>
            </select>
            <button type="submit" class="doc-btn doc-btn-primary min-h-[44px] px-5">
                <i class="fa-solid fa-magnifying-glass"></i> Tampilkan
            </button>
        </form>

        <?php if ($statusFilter !== '' || $q !== ''): ?>
            <div class="flex flex-wrap items-center gap-2 mt-3">
                <span class="text-xs font-semibold text-slate-500">Filter aktif:</span>
                <?php if ($q !== ''): ?><span class="doc-chip"><i class="fa-solid fa-magnifying-glass"></i><?= e($q) ?></span><?php endif; ?>
                <?php if ($statusFilter !== ''): ?><span class="doc-chip"><i class="fa-solid fa-filter"></i><?= e(ucfirst($statusFilter)) ?></span><?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- Daftar berbentuk card-row: informasi utama langsung terlihat, aksi detail tidak memenuhi layar -->
    <section class="doc-shell">
        <div class="px-4 sm:px-5 py-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h3 class="text-base font-extrabold text-slate-900">Daftar Dokumen</h3>
                <?php
                $shownStart = $totalData > 0 ? $offset + 1 : 0;
                $shownEnd = min($offset + count($documents), $totalData);
                ?>
                <p class="text-xs text-slate-500 mt-1">Menampilkan <?= (int) $shownStart ?>–<?= (int) $shownEnd ?> dari <?= (int) $totalData ?> dokumen</p>
            </div>
            <span class="doc-chip"><i class="fa-regular fa-circle-question"></i> Buka “Tindakan Admin” bila perlu</span>
        </div>

        <div class="p-3 sm:p-4 space-y-3">
            <?php if (empty($documents)): ?>
                <div class="doc-empty">
                    <div class="w-12 h-12 rounded-2xl bg-primarySoft text-primary flex items-center justify-center mx-auto text-lg">
                        <i class="fa-regular fa-folder-open"></i>
                    </div>
                    <h4 class="mt-3 font-extrabold text-slate-900">Dokumen tidak ditemukan</h4>
                    <p class="mt-1 text-sm text-slate-500">Coba ubah kata pencarian atau reset filter.</p>
                </div>
            <?php else: ?>
                <?php foreach ($documents as $index => $doc): ?>
                    <?php
                    $extension = strtolower((string) ($doc['file_extension'] ?? pathinfo((string) ($doc['file_path'] ?? ''), PATHINFO_EXTENSION)));
                    $absolutePath = appResolveDocumentAbsolutePath((string) ($doc['file_path'] ?? ''));
                    $fileExists = is_file($absolutePath);
                    $statusRaw = strtolower(trim((string) ($doc['status'] ?? 'pending')));
                    $state = appNormalizeDocumentState((string) ($doc['status'] ?? 'Pending'));
                    $revisionCount = (int) ($doc['revision_count'] ?? 0);
                    $lastRevisionAt = trim((string) ($doc['last_revision_at'] ?? ''));
                    $canRequestRevision = $revisionCount < appMaxDocumentRevisions();
                    $catatan = trim((string) ($doc['catatan_admin'] ?? ''));

                    if ($state === 'Approved') {
                        $statusLabel = 'Disetujui';
                        $statusClass = 'doc-status-approved';
                    } elseif ($state === 'Revision') {
                        $statusLabel = 'Perlu Revisi';
                        $statusClass = 'doc-status-revision';
                    } elseif ($state === 'Rejected') {
                        $statusLabel = 'Ditolak';
                        $statusClass = 'doc-status-rejected';
                    } else {
                        $statusLabel = 'Pending';
                        $statusClass = 'doc-status-pending';
                    }

                    $fileIcon = 'fa-file';
                    if ($extension === 'pdf') $fileIcon = 'fa-file-pdf';
                    elseif (in_array($extension, ['doc', 'docx'], true)) $fileIcon = 'fa-file-word';
                    elseif (in_array($extension, ['xls', 'xlsx'], true)) $fileIcon = 'fa-file-excel';
                    elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) $fileIcon = 'fa-file-image';
                    ?>
                    <article class="doc-card" style="animation-delay: <?= min($index * 35, 245) ?>ms">
                        <div class="flex flex-col xl:flex-row xl:items-start gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start gap-3">
                                    <div class="doc-file-icon"><i class="fa-regular <?= e($fileIcon) ?>"></i></div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="text-[11px] font-bold text-slate-400">#<?= $offset + $index + 1 ?> · <?= e(strtoupper($extension !== '' ? $extension : 'FILE')) ?></p>
                                                <h4 class="text-sm sm:text-[15px] leading-snug font-extrabold text-slate-900 mt-0.5 break-words">
                                                    <?= e(appDisplayFileName($doc['file_name'] ?? '')) ?>
                                                </h4>
                                            </div>
                                            <span class="doc-status <?= e($statusClass) ?> shrink-0"><?= e($statusLabel) ?></span>
                                        </div>

                                        <div class="flex flex-wrap gap-2 mt-3">
                                            <span class="doc-chip"><i class="fa-solid fa-layer-group"></i><?= e((string) ($doc['kategori_dokumen'] ?? '-')) ?></span>
                                            <span class="doc-chip"><i class="fa-regular fa-file-lines"></i><?= e(appDisplayDocumentType($doc)) ?></span>
                                            <?php if ($revisionCount > 0): ?>
                                                <span class="doc-chip"><i class="fa-solid fa-rotate"></i>Revisi ke-<?= $revisionCount ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="doc-meta-grid mt-4">
                                    <div class="doc-meta">
                                        <div class="doc-meta-label">Pengunggah</div>
                                        <div class="doc-meta-value"><?= e($doc['name'] ?: '-') ?></div>
                                    </div>
                                    <div class="doc-meta">
                                        <div class="doc-meta-label">Jabatan</div>
                                        <div class="doc-meta-value"><?= e($doc['jabatan'] ?: '-') ?></div>
                                    </div>
                                    <div class="doc-meta">
                                        <div class="doc-meta-label">Tanggal Upload</div>
                                        <div class="doc-meta-value"><?= e(!empty($doc['created_at']) ? date('d-m-Y H:i', strtotime((string) $doc['created_at'])) : '-') ?></div>
                                    </div>
                                    <div class="doc-meta">
                                        <div class="doc-meta-label">Riwayat</div>
                                        <div class="doc-meta-value">
                                            <?php if ($revisionCount > 0): ?>
                                                Revisi ke-<?= $revisionCount ?><?= $lastRevisionAt !== '' ? ' · ' . e(date('d-m-Y', strtotime($lastRevisionAt))) : '' ?>
                                            <?php else: ?>
                                                Upload awal
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($catatan !== ''): ?>
                                    <div class="doc-note">
                                        <span class="font-extrabold text-slate-700"><i class="fa-regular fa-note-sticky mr-1"></i> Catatan Admin:</span>
                                        <span><?= nl2br(e($catatan)) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="xl:w-[230px] shrink-0 flex xl:flex-col flex-wrap gap-2">
                                <?php if ($fileExists): ?>
                                    <a href="../preview.php?id=<?= (int) ($doc['id'] ?? 0) ?>" target="_blank" class="doc-btn doc-btn-soft flex-1 xl:flex-none">
                                        <i class="fa-regular fa-eye"></i> Preview
                                    </a>
                                    <a href="../download.php?id=<?= (int) ($doc['id'] ?? 0) ?>" class="doc-btn doc-btn-primary flex-1 xl:flex-none">
                                        <i class="fa-solid fa-download"></i> Download
                                    </a>
                                <?php else: ?>
                                    <span class="doc-btn doc-btn-danger cursor-not-allowed flex-1 xl:flex-none">
                                        <i class="fa-solid fa-triangle-exclamation"></i> File tidak ada
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="doc-actions">
                            <?php if ($state === 'Pending'): ?>
                                <div class="flex flex-col lg:flex-row lg:items-start gap-2">
                                    <form method="POST" onsubmit="return confirm('Yakin ingin menyetujui dokumen ini?')" class="lg:shrink-0">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="document_id" value="<?= (int) $doc['id'] ?>">
                                        <input type="hidden" name="new_status" value="Approved">
                                        <input type="hidden" name="q" value="<?= e($q) ?>">
                                        <input type="hidden" name="status_filter" value="<?= e($statusFilter) ?>">
                                        <input type="hidden" name="page_num" value="<?= (int) $pageNum ?>">
                                        <button type="submit" class="doc-btn doc-btn-success w-full lg:w-auto">
                                            <i class="fa-solid fa-check"></i> Setujui
                                        </button>
                                    </form>

                                    <?php if ($canRequestRevision): ?>
                                        <details class="doc-action-details flex-1">
                                            <summary><i class="fa-solid fa-pen-to-square text-primary"></i> Minta Revisi</summary>
                                            <div class="doc-action-body">
                                                <form method="POST" class="space-y-2" onsubmit="return confirm('Yakin ingin meminta revisi dokumen ini?')">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="document_id" value="<?= (int) $doc['id'] ?>">
                                                    <input type="hidden" name="new_status" value="Revision">
                                                    <input type="hidden" name="q" value="<?= e($q) ?>">
                                                    <input type="hidden" name="status_filter" value="<?= e($statusFilter) ?>">
                                                    <input type="hidden" name="page_num" value="<?= (int) $pageNum ?>">
                                                    <textarea name="admin_note" required rows="2" placeholder="Tuliskan bagian yang perlu diperbaiki..." class="w-full px-3 py-2.5 rounded-xl border text-xs"></textarea>
                                                    <button type="submit" class="doc-btn doc-btn-primary"><i class="fa-solid fa-paper-plane"></i> Kirim Permintaan Revisi</button>
                                                </form>
                                            </div>
                                        </details>
                                    <?php else: ?>
                                        <div class="flex-1 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                                            Batas <?= appMaxDocumentRevisions() ?> kali revisi tercapai.
                                        </div>
                                    <?php endif; ?>

                                    <details class="doc-action-details flex-1">
                                        <summary><i class="fa-solid fa-xmark text-red-500"></i> Tolak Dokumen</summary>
                                        <div class="doc-action-body">
                                            <form method="POST" class="space-y-2" onsubmit="return confirm('Yakin ingin menolak dokumen ini?')">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="document_id" value="<?= (int) $doc['id'] ?>">
                                                <input type="hidden" name="new_status" value="Rejected">
                                                <input type="hidden" name="q" value="<?= e($q) ?>">
                                                <input type="hidden" name="status_filter" value="<?= e($statusFilter) ?>">
                                                <input type="hidden" name="page_num" value="<?= (int) $pageNum ?>">
                                                <textarea name="admin_note" required rows="2" placeholder="Tuliskan alasan penolakan..." class="w-full px-3 py-2.5 rounded-xl border text-xs"></textarea>
                                                <button type="submit" class="doc-btn doc-btn-danger"><i class="fa-solid fa-ban"></i> Tolak Dokumen</button>
                                            </form>
                                        </div>
                                    </details>
                                </div>

                            <?php elseif ($state === 'Approved'): ?>
                                <details class="doc-action-details">
                                    <summary><i class="fa-solid fa-rotate-left text-amber-500"></i> Tindakan Admin · Koreksi status bila salah klik</summary>
                                    <div class="doc-action-body">
                                        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3">
                                            <form method="POST" onsubmit="return confirm('Batalkan persetujuan dan kembalikan dokumen ke Pending?')">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="document_id" value="<?= (int) $doc['id'] ?>">
                                                <input type="hidden" name="new_status" value="Pending">
                                                <input type="hidden" name="q" value="<?= e($q) ?>">
                                                <input type="hidden" name="status_filter" value="<?= e($statusFilter) ?>">
                                                <input type="hidden" name="page_num" value="<?= (int) $pageNum ?>">
                                                <button type="submit" class="doc-btn doc-btn-warning w-full"><i class="fa-solid fa-rotate-left"></i> Kembalikan ke Pending</button>
                                            </form>

                                            <?php if ($canRequestRevision): ?>
                                                <form method="POST" class="space-y-2" onsubmit="return confirm('Ubah dokumen yang sudah disetujui menjadi Perlu Revisi?')">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="document_id" value="<?= (int) $doc['id'] ?>">
                                                    <input type="hidden" name="new_status" value="Revision">
                                                    <input type="hidden" name="q" value="<?= e($q) ?>">
                                                    <input type="hidden" name="status_filter" value="<?= e($statusFilter) ?>">
                                                    <input type="hidden" name="page_num" value="<?= (int) $pageNum ?>">
                                                    <textarea name="admin_note" required rows="2" placeholder="Alasan revisi..." class="w-full px-3 py-2.5 rounded-xl border text-xs"></textarea>
                                                    <button type="submit" class="doc-btn doc-btn-primary w-full"><i class="fa-solid fa-pen-to-square"></i> Ubah ke Revisi</button>
                                                </form>
                                            <?php else: ?>
                                                <div class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">Batas revisi tercapai.</div>
                                            <?php endif; ?>

                                            <form method="POST" class="space-y-2" onsubmit="return confirm('Ubah dokumen yang sudah disetujui menjadi Ditolak?')">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="document_id" value="<?= (int) $doc['id'] ?>">
                                                <input type="hidden" name="new_status" value="Rejected">
                                                <input type="hidden" name="q" value="<?= e($q) ?>">
                                                <input type="hidden" name="status_filter" value="<?= e($statusFilter) ?>">
                                                <input type="hidden" name="page_num" value="<?= (int) $pageNum ?>">
                                                <textarea name="admin_note" required rows="2" placeholder="Alasan penolakan..." class="w-full px-3 py-2.5 rounded-xl border text-xs"></textarea>
                                                <button type="submit" class="doc-btn doc-btn-danger w-full"><i class="fa-solid fa-ban"></i> Ubah ke Ditolak</button>
                                            </form>
                                        </div>
                                    </div>
                                </details>
                            <?php else: ?>
                                <div class="flex items-center gap-2 text-xs text-slate-500">
                                    <i class="fa-solid fa-circle-check text-primary"></i>
                                    Dokumen sudah diproses. Status saat ini: <strong class="text-slate-700"><?= e($statusLabel) ?></strong>.
                                </div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <div class="doc-pagination px-4 sm:px-5 py-4 border-t border-slate-200 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div class="text-xs text-slate-500">Halaman <strong class="text-slate-700"><?= $pageNum ?></strong> dari <strong class="text-slate-700"><?= $totalPages ?></strong></div>
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <?php
                $prevDisabled = $pageNum <= 1;
                $nextDisabled = $pageNum >= $totalPages;
                $baseParams = ['page' => 'manajemen_dokumen'];
                if ($q !== '') $baseParams['q'] = $q;
                if ($statusFilter !== '') $baseParams['status'] = $statusFilter;
                ?>
                <?php if ($prevDisabled): ?>
                    <span class="doc-btn doc-btn-soft opacity-50 cursor-not-allowed flex-1 sm:flex-none"><i class="fa-solid fa-chevron-left"></i> Sebelumnya</span>
                <?php else: ?>
                    <a href="main.php?<?= http_build_query(array_merge($baseParams, ['page_num' => $pageNum - 1])) ?>" class="doc-btn doc-btn-soft flex-1 sm:flex-none"><i class="fa-solid fa-chevron-left"></i> Sebelumnya</a>
                <?php endif; ?>

                <?php if ($nextDisabled): ?>
                    <span class="doc-btn doc-btn-soft opacity-50 cursor-not-allowed flex-1 sm:flex-none">Berikutnya <i class="fa-solid fa-chevron-right"></i></span>
                <?php else: ?>
                    <a href="main.php?<?= http_build_query(array_merge($baseParams, ['page_num' => $pageNum + 1])) ?>" class="doc-btn doc-btn-primary flex-1 sm:flex-none">Berikutnya <i class="fa-solid fa-chevron-right"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>

<script>
/* Hanya satu panel tindakan per dokumen terbuka agar halaman tetap ringkas. */
document.querySelectorAll('.doc-card').forEach(function(card) {
    card.querySelectorAll('.doc-action-details').forEach(function(panel) {
        panel.addEventListener('toggle', function() {
            if (!panel.open) return;
            card.querySelectorAll('.doc-action-details').forEach(function(other) {
                if (other !== panel) other.removeAttribute('open');
            });
        });
    });
});
</script>
