<?php
/* =========================================================
   PUSTAKA USER
   Fungsi: Menampilkan dokumen milik user, memproses filter, dan mengatur status tombol download.
========================================================= */

declare(strict_types=1);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$flashMsg = '';
$flashType = '';

$db = appDb();

/* =========================================================
   BAGIAN FUNGSI DAN FILTER PUSTAKA
   Bagian ini menyiapkan fungsi bantu, filter pencarian, dan query daftar dokumen.
========================================================= */






function canUserDownloadDocument(string $status): bool
{
    return appNormalizeDocumentStatus($status) === 'approved';
}

function mapDocumentStatus(string $status): array
{
    return match (appNormalizeDocumentStatus($status)) {
        'approved' => [
            'label' => 'Disetujui',
            'icon'  => 'fa-circle-check',
            'class' => 'bg-green-50 text-green-700 border border-green-200',
        ],
        'rejected' => [
            'label' => 'Ditolak',
            'icon'  => 'fa-circle-xmark',
            'class' => 'bg-red-50 text-red-700 border border-red-200',
        ],
        'revision' => [
            'label' => 'Perlu Revisi',
            'icon'  => 'fa-pen-to-square',
            'class' => 'bg-blue-50 text-blue-700 border border-blue-200',
        ],
        default => [
            'label' => 'Pending',
            'icon'  => 'fa-hourglass-half',
            'class' => 'bg-yellow-50 text-yellow-700 border border-yellow-200',
        ],
    };
}

function getLockedDownloadLabel(string $status): string
{
    return match (appNormalizeDocumentStatus($status)) {
        'rejected' => 'Dokumen Ditolak',
        'revision' => 'Perlu Revisi',
        default => 'Menunggu Persetujuan',
    };
}

function resolveDocumentAbsolutePath(string $relativePath): ?string
{
    return appResolveDocumentAbsolutePath($relativePath, false);
}


function safeDownloadName(string $filename): string
{
    $filename = str_replace(["\r", "\n"], '', $filename);

    return basename($filename);
}

function displayPustakaFileName(?string $fileName): string
{
    return appDisplayFileName($fileName);
}

function buildPaginationUrl(array $params): string
{
    $query = array_merge(
        ['page' => 'pustaka'],
        $params
    );

    return 'main.php?' . http_build_query($query);
}

function countDocuments(mysqli $db, string $whereSql, string $types, array $params): int
{
    $sql = "SELECT COUNT(*) AS total FROM documents {$whereSql}";
    $stmt = $db->prepare($sql);

    if (!$stmt) {
        return 0;
    }

    appBindParams($stmt, $types, $params);

    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;

    $stmt->close();

    return (int) ($row['total'] ?? 0);
}

function fetchDocuments(
    mysqli $db,
    string $whereSql,
    string $types,
    array $params,
    string $selectCatatan,
    string $selectPeriode,
    int $perPage,
    int $offset
): array {
    $sql = "SELECT 
                id, 
                user_id, 
                file_name, 
                file_path, 
                kategori_dokumen, 
                jenis_dokumen,
                jenis_dokumen_lainnya, 
                tanggal_dokumen, 
                status, 
                created_at,
                revision_count
                {$selectCatatan}
                {$selectPeriode}
            FROM documents
            {$whereSql}
            ORDER BY created_at DESC, id DESC
            LIMIT ? OFFSET ?";

    $stmt = $db->prepare($sql);

    if (!$stmt) {
        return [];
    }

    $listTypes = $types . 'ii';
    $listParams = $params;
    $listParams[] = $perPage;
    $listParams[] = $offset;

    appBindParams($stmt, $listTypes, $listParams);

    $stmt->execute();

    $result = $stmt->get_result();
    $documents = [];

    while ($row = $result->fetch_assoc()) {
        $documents[] = $row;
    }

    $stmt->close();

    return $documents;
}

$requiredColumns = [
    'id',
    'user_id',
    'file_name',
    'file_path',
    'kategori_dokumen',
    'jenis_dokumen',
    'jenis_dokumen_lainnya',
    'tanggal_dokumen',
    'status',
    'created_at',
];

$searchFilter         = trim((string) ($_GET['search'] ?? ''));
$kategoriFilter       = trim((string) ($_GET['kategori'] ?? ''));
$jenisFilter          = trim((string) ($_GET['jenis'] ?? ''));
$periodeMulaiFilter   = trim((string) ($_GET['periode_mulai'] ?? ''));
$periodeSelesaiFilter = trim((string) ($_GET['periode_selesai'] ?? ''));
$action               = trim((string) ($_GET['action'] ?? ''));

$statusPage           = max(1, (int) ($_GET['sp'] ?? 1));
$approvedPage         = max(1, (int) ($_GET['ap'] ?? 1));
$perPage              = 10;

$kategoriOptions = [];
$jenisOptions = [];

$statusDocuments = [];
$approvedDocuments = [];

$hasCatatanAdmin = false;
$hasPeriodeBulan = false;
$hasOcrText = false;

$totalAllDocuments = 0;
$totalStatusDocuments = 0;
$totalApprovedDocuments = 0;

$totalStatusPages = 1;
$totalApprovedPages = 1;

if (!$userId) {
    $flashMsg = 'Session user tidak ditemukan. Silakan login ulang.';
    $flashType = 'error';

} elseif (!isset($db) || !($db instanceof mysqli)) {
    $flashMsg = 'Koneksi database tidak tersedia.';
    $flashType = 'error';

} elseif (!appTableExists($db, 'documents')) {
    $activeDb = appCurrentDatabaseName($db);
    $flashMsg = 'Tabel documents tidak ditemukan pada database aktif: ' . ($activeDb !== '' ? $activeDb : '(tidak ada database terpilih)');
    $flashType = 'error';

} elseif (!appTableHasRequiredColumns($db, 'documents', $requiredColumns)) {
    $activeDb = appCurrentDatabaseName($db);
    $flashMsg = 'Struktur tabel documents belum sesuai pada database aktif: ' . ($activeDb !== '' ? $activeDb : '(tidak ada database terpilih)');
    $flashType = 'error';

} else {
    if (!appTableHasColumn($db, 'documents', 'revision_count')) {
        @$db->query("ALTER TABLE documents ADD COLUMN revision_count INT NOT NULL DEFAULT 0 AFTER catatan_admin");
    }

    $hasCatatanAdmin = appTableHasColumn($db, 'documents', 'catatan_admin');
    appEnsureSimadaCoreSchema($db);
    $hasPeriodeBulan = appTableHasColumn($db, 'documents', 'periode_bulan');
    $hasOcrText = appTableHasColumn($db, 'documents', 'ocr_text');

    if ($periodeMulaiFilter !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodeMulaiFilter)) {
        $flashMsg = 'Format tanggal mulai harus YYYY-MM-DD.';
        $flashType = 'error';
    }

    if ($periodeSelesaiFilter !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $periodeSelesaiFilter)) {
        $flashMsg = 'Format tanggal selesai harus YYYY-MM-DD.';
        $flashType = 'error';
    }

    if ($flashMsg === '' && $periodeMulaiFilter !== '' && $periodeSelesaiFilter !== '' && $periodeMulaiFilter > $periodeSelesaiFilter) {
        $flashMsg = 'Tanggal mulai tidak boleh lebih besar dari tanggal selesai.';
        $flashType = 'error';
    }

    if ($flashMsg === '' && $action === 'download' && isset($_GET['id'])) {
        $documentId = (int) ($_GET['id'] ?? 0);

        if ($documentId <= 0) {
            $flashMsg = 'Dokumen tidak valid.';
            $flashType = 'error';
        } else {
            $selectCatatan = $hasCatatanAdmin ? ', catatan_admin' : '';
            $selectPeriode = $hasPeriodeBulan ? ', periode_bulan' : '';

            $sql = "SELECT 
                        id, 
                        user_id, 
                        file_name, 
                        file_path, 
                        file_extension,
                        kategori_dokumen, 
                        jenis_dokumen,
                        jenis_dokumen_lainnya, 
                        tanggal_dokumen, 
                        status, 
                        created_at
                        {$selectCatatan}
                        {$selectPeriode}
                    FROM documents
                    WHERE id = ? AND user_id = ?
                    LIMIT 1";

            $stmt = $db->prepare($sql);

            if (!$stmt) {
                $flashMsg = 'Gagal menyiapkan download dokumen.';
                $flashType = 'error';
            } else {
                $stmt->bind_param('ii', $documentId, $userId);
                $stmt->execute();

                $result = $stmt->get_result();
                $document = $result ? $result->fetch_assoc() : null;

                $stmt->close();

                if (!$document) {
                    $flashMsg = 'Dokumen tidak ditemukan.';
                    $flashType = 'error';
                } elseif (!canUserDownloadDocument((string) ($document['status'] ?? ''))) {
                    $flashMsg = 'Dokumen ini belum dapat didownload karena statusnya belum Disetujui.';
                    $flashType = 'error';
                } else {
                    $absolutePath = resolveDocumentAbsolutePath((string) ($document['file_path'] ?? ''));

                    if ($absolutePath === null || !is_file($absolutePath)) {
                        $flashMsg = 'File dokumen tidak ditemukan di server.';
                        $flashType = 'error';
                    } else {
                        $downloadName = displayPustakaFileName((string) ($document['file_name'] ?? basename($absolutePath)));

                        $extension = strtolower((string) ($document['file_extension'] ?? pathinfo($downloadName, PATHINFO_EXTENSION)));
                        $mimeTypes = [
                            'pdf' => 'application/pdf',
                            'doc' => 'application/msword',
                            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'xls' => 'application/vnd.ms-excel',
                            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ];
                        $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

                        appOutputDocumentBinary($db, $absolutePath, $document, safeDownloadName($downloadName), $mimeType, 'attachment');
                    }
                }
            }
        }
    }

    $sqlKategori = "SELECT DISTINCT kategori_dokumen
                    FROM documents
                    WHERE user_id = ?
                      AND kategori_dokumen IS NOT NULL
                      AND kategori_dokumen <> ''
                    ORDER BY kategori_dokumen ASC";

    $stmtKategori = $db->prepare($sqlKategori);

    if ($stmtKategori) {
        $stmtKategori->bind_param('i', $userId);
        $stmtKategori->execute();

        $resultKategori = $stmtKategori->get_result();

        while ($row = $resultKategori->fetch_assoc()) {
            $kategoriOptions[] = (string) $row['kategori_dokumen'];
        }

        $stmtKategori->close();
    }

    /*
     * Jenis dokumen USER harus mengikuti kategori yang sedang dipilih.
     * "Lainnya" tidak ditampilkan sebagai pilihan.
     */
    $sqlJenis = "SELECT DISTINCT
                    CASE
                        WHEN LOWER(TRIM(jenis_dokumen)) = 'lainnya'
                         AND jenis_dokumen_lainnya IS NOT NULL
                         AND TRIM(jenis_dokumen_lainnya) <> ''
                        THEN TRIM(jenis_dokumen_lainnya)
                        ELSE TRIM(jenis_dokumen)
                    END AS jenis_tampil
                 FROM documents
                 WHERE user_id = ?
                   AND kategori_dokumen = ?
                   AND jenis_dokumen IS NOT NULL
                   AND TRIM(jenis_dokumen) <> ''
                   AND NOT (
                        LOWER(TRIM(jenis_dokumen)) = 'lainnya'
                        AND (
                            jenis_dokumen_lainnya IS NULL
                            OR TRIM(jenis_dokumen_lainnya) = ''
                        )
                   )
                 ORDER BY jenis_tampil ASC";

    $stmtJenis = $db->prepare($sqlJenis);

   if ($stmtJenis) {
    $stmtJenis->bind_param('is', $userId, $kategoriFilter);
    $stmtJenis->execute();

    $resultJenis = $stmtJenis->get_result();

    while ($row = $resultJenis->fetch_assoc()) {
        $jenisTampil = trim((string) ($row['jenis_tampil'] ?? ''));

        if ($jenisTampil !== '') {
            $jenisOptions[] = $jenisTampil;
        }
    }

    $stmtJenis->close();
}

if ($flashType !== 'error' || $flashMsg === '') {
        $baseWhere = ['user_id = ?'];
        $baseTypes = 'i';
        $baseParams = [$userId];

        if ($searchFilter !== '') {
            $searchLike = '%' . $searchFilter . '%';
            $searchParts = [
                "REPLACE(file_name, '_', ' ') LIKE ?",
                "kategori_dokumen LIKE ?",
                "jenis_dokumen LIKE ?",
                "jenis_dokumen_lainnya LIKE ?",
                "status LIKE ?",
            ];

            $baseTypes .= 'sssss';
            $baseParams[] = $searchLike;
            $baseParams[] = $searchLike;
            $baseParams[] = $searchLike;
            $baseParams[] = $searchLike;
            $baseParams[] = $searchLike;
            if ($hasOcrText) {
                $searchParts[] = "ocr_text LIKE ?";
                $baseTypes .= 's';
                $baseParams[] = $searchLike;
            }

            $baseWhere[] = '(' . implode(' OR ', $searchParts) . ')';
        }

        if ($kategoriFilter !== '') {
            $baseWhere[] = "kategori_dokumen = ?";
            $baseTypes .= 's';
            $baseParams[] = $kategoriFilter;
        }

        if ($jenisFilter !== '') {
            $baseWhere[] = "(jenis_dokumen = ? OR jenis_dokumen_lainnya = ?)";
            $baseTypes .= 'ss';
            $baseParams[] = $jenisFilter;
            $baseParams[] = $jenisFilter;
        }

        if ($periodeMulaiFilter !== '') {
            $baseWhere[] = "tanggal_dokumen >= ?";
            $baseTypes .= 's';
            $baseParams[] = $periodeMulaiFilter;
        }

        if ($periodeSelesaiFilter !== '') {
            $baseWhere[] = "tanggal_dokumen <= ?";
            $baseTypes .= 's';
            $baseParams[] = $periodeSelesaiFilter;
        }

        $selectCatatan = $hasCatatanAdmin ? ', catatan_admin' : '';
        $selectPeriode = $hasPeriodeBulan ? ', periode_bulan' : '';

        $statusWhereParts = $baseWhere;
        $statusWhereParts[] = "(
            status IS NULL
            OR TRIM(status) = ''
            OR LOWER(TRIM(status)) NOT IN ('approved', 'disetujui')
        )";

        $approvedWhereParts = $baseWhere;
        $approvedWhereParts[] = "LOWER(TRIM(status)) IN ('approved', 'disetujui')";

        $statusWhereSql = 'WHERE ' . implode(' AND ', $statusWhereParts);
        $approvedWhereSql = 'WHERE ' . implode(' AND ', $approvedWhereParts);

        $totalStatusDocuments = countDocuments($db, $statusWhereSql, $baseTypes, $baseParams);
        $totalApprovedDocuments = countDocuments($db, $approvedWhereSql, $baseTypes, $baseParams);
        $totalAllDocuments = $totalStatusDocuments + $totalApprovedDocuments;

        $totalStatusPages = max(1, (int) ceil($totalStatusDocuments / $perPage));
        $totalApprovedPages = max(1, (int) ceil($totalApprovedDocuments / $perPage));

        if ($statusPage > $totalStatusPages) {
            $statusPage = $totalStatusPages;
        }

        if ($approvedPage > $totalApprovedPages) {
            $approvedPage = $totalApprovedPages;
        }

        $statusOffset = ($statusPage - 1) * $perPage;
        $approvedOffset = ($approvedPage - 1) * $perPage;

        $statusDocuments = fetchDocuments(
            $db,
            $statusWhereSql,
            $baseTypes,
            $baseParams,
            $selectCatatan,
            $selectPeriode,
            $perPage,
            $statusOffset
        );

        $approvedDocuments = fetchDocuments(
            $db,
            $approvedWhereSql,
            $baseTypes,
            $baseParams,
            $selectCatatan,
            $selectPeriode,
            $perPage,
            $approvedOffset
        );
    }
}

$paginationBaseParams = [
    'search' => $searchFilter,
    'kategori' => $kategoriFilter,
    'jenis' => $jenisFilter,
    'periode_mulai' => $periodeMulaiFilter,
    'periode_selesai' => $periodeSelesaiFilter,
];
/* =========================================================
   BAGIAN TAMPILAN
   Bagian setelah tanda penutup PHP berisi HTML, CSS, atau JavaScript
   yang membentuk tampilan halaman untuk pengguna.
========================================================= */
?>

<div class="space-y-6">

    <?php if (!empty($flashMsg)): ?>
        <div class="rounded-xl p-4 text-sm <?= $flashType === 'error' ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-yellow-50 border border-yellow-200 text-yellow-700' ?>">
            <?= e($flashMsg) ?>
        </div>
    <?php endif; ?>

    <!-- FILTER -->
    <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
        <div class="flex flex-col gap-1 mb-5">
            <h3 class="font-semibold text-slate-700 flex items-center gap-2 text-sm">
                <i class="fa-solid fa-filter text-primary"></i> Filter Dokumen Saya
            </h3>
            <p class="text-xs text-slate-400">
                Gunakan filter berikut untuk mencari dokumen lebih cepat.
            </p>
        </div>

        <form method="GET" class="space-y-6">
            <input type="hidden" name="page" value="pustaka">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Search
                    </label>
                    <input
                        type="text"
                        name="search"
                        value="<?= e($searchFilter) ?>"
                        placeholder="Cari nama file, kategori, jenis, status, atau isi OCR"
                        class="w-full p-3 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-primary">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Kategori Dokumen
                    </label>
                    <select
                        name="kategori"
                        id="filterKategori"
                        class="w-full p-3 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-primary">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($kategoriOptions as $kategoriOption): ?>
                            <option value="<?= e($kategoriOption) ?>" <?= $kategoriFilter === $kategoriOption ? 'selected' : '' ?>>
                                <?= e($kategoriOption) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">
                        Jenis Dokumen
                    </label>
                    <select
                        name="jenis"
                        id="filterJenis"
                        class="w-full p-3 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-primary"
                        <?= $kategoriFilter === '' ? 'disabled' : '' ?>>
                        <?php if ($kategoriFilter === ''): ?>
                            <option value="">Pilih kategori terlebih dahulu</option>
                        <?php else: ?>
                            <option value="">Semua Jenis</option>
                            <?php foreach ($jenisOptions as $jenisOption): ?>
                                <option value="<?= e($jenisOption) ?>" <?= $jenisFilter === $jenisOption ? 'selected' : '' ?>>
                                    <?= e($jenisOption) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="lg:col-span-3">
                    <label class="block text-sm font-medium text-slate-700 mb-2">
                        Tanggal Dokumen
                    </label>

                    <div class="grid grid-cols-1 md:grid-cols-[1fr_auto_1fr] gap-3 items-center">
                        <div>
                            <input
                                type="date"
                                name="periode_mulai"
                                value="<?= e($periodeMulaiFilter) ?>"
                                class="w-full p-3 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-primary">
                        </div>

                        <div class="text-center text-sm font-medium text-slate-500 px-2">
                            s/d
                        </div>

                        <div>
                            <input
                                type="date"
                                name="periode_selesai"
                                value="<?= e($periodeSelesaiFilter) ?>"
                                class="w-full p-3 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-primary">
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:justify-end gap-3 pt-1">
                <a
                    href="main.php?page=pustaka"
                    class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition text-sm">
                    Reset
                </a>

                <button
                    type="submit"
                    class="inline-flex items-center justify-center px-6 py-3 rounded-xl bg-primary text-white hover:bg-primaryDark transition font-medium text-sm">
                    <i class="fa-solid fa-magnifying-glass mr-2"></i> Cari
                </button>
            </div>
        </form>
    </div>

    <!-- RINGKASAN -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <p class="text-sm text-slate-500">Total Dokumen Saya</p>
            <h3 class="text-2xl font-semibold text-slate-800 mt-1">
                <?= number_format($totalAllDocuments) ?>
            </h3>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <p class="text-sm text-slate-500">Status Pengajuan</p>
            <h3 class="text-2xl font-semibold text-yellow-600 mt-1">
                <?= number_format($totalStatusDocuments) ?>
            </h3>
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl p-5 shadow-sm">
            <p class="text-sm text-slate-500">Dokumen Disetujui</p>
            <h3 class="text-2xl font-semibold text-green-600 mt-1">
                <?= number_format($totalApprovedDocuments) ?>
            </h3>
        </div>
    </div>

    <!-- TABLE 1: STATUS PENGAJUAN -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h3 class="font-semibold text-slate-700 flex items-center gap-2 text-sm">
                    <i class="fa-solid fa-hourglass-half text-primary"></i> Status Pengajuan Dokumen
                </h3>
                <p class="text-xs text-slate-400 mt-1">
                    Berisi dokumen yang masih menunggu persetujuan, ditolak, atau perlu revisi.
                </p>
            </div>

            <p class="text-xs text-slate-400">
                Total: <?= (int) $totalStatusDocuments ?>
            </p>
        </div>

        <?php if (empty($statusDocuments)): ?>
            <div class="text-center py-12 text-slate-300 px-6">
                <i class="fa-regular fa-folder-open text-5xl mb-3"></i>
                <p class="text-sm text-slate-400">Tidak ada dokumen dalam proses pengajuan.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr class="hover:bg-slate-50 transition text-left text-slate-600">
                            <th class="px-4 py-3 font-semibold border-b border-slate-200" data-sort>No</th>
                            <th class="px-4 py-3 font-semibold border-b border-slate-200" data-sort>Nama File</th>
                            <th class="px-4 py-3 font-semibold border-b border-slate-200" data-sort>Kategori</th>
                            <th class="px-4 py-3 font-semibold border-b border-slate-200" data-sort>Jenis</th>
                            <th class="px-4 py-3 font-semibold border-b border-slate-200" data-sort>Tanggal Dokumen</th>
                            <th class="px-4 py-3 font-semibold border-b border-slate-200" data-sort>Periode</th>
                            <th class="px-4 py-3 font-semibold border-b border-slate-200" data-sort>Tanggal Upload</th>
                            <th class="px-4 py-3 font-semibold border-b border-slate-200" data-sort>Status</th>
                            <th class="px-4 py-3 font-semibold border-b border-slate-200" data-sort>Catatan Admin</th>
                            <th class="px-4 py-3 font-semibold border-b border-slate-200 text-center" data-sort>Aksi</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($statusDocuments as $index => $doc): ?>
                            <?php
                            $statusInfo = mapDocumentStatus((string) ($doc['status'] ?? ''));
                            $catatanAdmin = trim((string) ($doc['catatan_admin'] ?? ''));
                            $rowNumber = (($statusPage - 1) * $perPage) + $index + 1;
                            ?>
                            <tr class="hover:bg-slate-50 transition hover:bg-slate-50 transition">
                                <td class="px-4 py-4 align-top text-slate-500">
                                    <?= $rowNumber ?>
                                </td>

                                <td class="px-4 py-4 align-top min-w-[220px]">
                                    <p class="font-medium text-slate-800 break-words">
                                        <?= e(displayPustakaFileName($doc['file_name'] ?? '')) ?>
                                    </p>
                                </td>

                                <td class="px-4 py-4 align-top min-w-[170px] text-slate-600">
                                    <?= e((string) ($doc['kategori_dokumen'] ?? '-')) ?>
                                </td>

                                <td class="px-4 py-4 align-top min-w-[170px] text-slate-600">
                                    <?= e(appDisplayDocumentType($doc)) ?>
                                </td>

                                <td class="px-4 py-4 align-top whitespace-nowrap text-slate-600">
                                    <?= !empty($doc['tanggal_dokumen']) ? e(date('d F Y', strtotime((string) $doc['tanggal_dokumen']))) : '-' ?>
                                </td>

                                <td class="px-4 py-4 align-top whitespace-nowrap text-slate-600">
                                    <?php if (!empty($doc['periode_bulan'])): ?>
                                        <?= e(date('F Y', strtotime((string) $doc['periode_bulan'] . '-01'))) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>

                                <td class="px-4 py-4 align-top whitespace-nowrap text-slate-500">
                                    <?= !empty($doc['created_at']) ? e(date('d F Y H:i', strtotime((string) $doc['created_at']))) : '-' ?>
                                </td>

                                <td class="px-4 py-4 align-top whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs <?= e($statusInfo['class']) ?>">
                                        <i class="fa-solid <?= e($statusInfo['icon']) ?>"></i>
                                        <?= e($statusInfo['label']) ?>
                                    </span>
                                </td>

                                <td class="px-4 py-4 align-top min-w-[240px] text-slate-600">
                                    <?php if ($catatanAdmin !== ''): ?>
                                        <div class="text-xs rounded-lg bg-slate-50 border border-slate-200 p-3">
                                            <?= nl2br(e($catatanAdmin)) ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-slate-400">-</span>
                                    <?php endif; ?>
                                </td>

                                <td class="px-4 py-4 align-top text-center whitespace-nowrap">
                                    <?php if (appNormalizeDocumentStatus((string) ($doc['status'] ?? '')) === 'revision'): ?>
                                        <?php if ((int) ($doc['revision_count'] ?? 0) < appMaxDocumentRevisions()): ?>
                                            <a
                                                href="main.php?page=upload&revision_id=<?= (int) ($doc['id'] ?? 0) ?>"
                                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 text-sm transition">
                                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                                Upload Ulang
                                            </a>
                                        <?php else: ?>
                                            <button
                                                type="button"
                                                disabled
                                                class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-orange-50 text-orange-700 border border-orange-200 cursor-not-allowed text-sm">
                                                <i class="fa-solid fa-circle-exclamation"></i>
                                                Batas Revisi
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button
                                            type="button"
                                            disabled
                                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-100 text-slate-400 cursor-not-allowed text-sm">
                                            <i class="fa-solid fa-lock"></i>
                                            <?= e(getLockedDownloadLabel((string) ($doc['status'] ?? ''))) ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalStatusPages > 1): ?>
                <div class="px-6 py-4 border-t border-slate-200 flex flex-col md:flex-row items-center justify-between gap-4">
                    <div class="text-sm text-slate-500">
                        Halaman <?= $statusPage ?> dari <?= $totalStatusPages ?>
                    </div>

                    <div class="flex items-center gap-3">
                        <?php
                        $prevStatusPage = max(1, $statusPage - 1);
                        $nextStatusPage = min($totalStatusPages, $statusPage + 1);
                        ?>

                        <?php if ($statusPage <= 1): ?>
                            <span class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-slate-100 text-slate-400 text-sm cursor-not-allowed">
                                <i class="fa-solid fa-chevron-left"></i> Sebelumnya
                            </span>
                        <?php else: ?>
                            <a
                                href="<?= e(buildPaginationUrl(array_merge($paginationBaseParams, ['sp' => $prevStatusPage, 'ap' => $approvedPage]))) ?>"
                                class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-slate-100 text-slate-700 text-sm hover:bg-slate-200 transition">
                                <i class="fa-solid fa-chevron-left"></i> Sebelumnya
                            </a>
                        <?php endif; ?>

                        <?php if ($statusPage >= $totalStatusPages): ?>
                            <span class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-slate-100 text-slate-400 text-sm cursor-not-allowed">
                                Berikutnya <i class="fa-solid fa-chevron-right"></i>
                            </span>
                        <?php else: ?>
                            <a
                                href="<?= e(buildPaginationUrl(array_merge($paginationBaseParams, ['sp' => $nextStatusPage, 'ap' => $approvedPage]))) ?>"
                                class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-primary text-white text-sm hover:bg-primaryDark transition">
                                Berikutnya <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- TABLE 2: PUSTAKA DISETUJUI -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
            <div>
                <h3 class="font-semibold text-slate-700 flex items-center gap-2 text-sm">
                    <i class="fa-solid fa-folder text-primary"></i> Pustaka Dokumen Disetujui
                </h3>
                <p class="text-xs text-slate-400 mt-1">
                    Berisi dokumen yang sudah disetujui dan dapat diunduh.
                </p>
            </div>

            <p class="text-xs text-slate-400">
                Total: <?= (int) $totalApprovedDocuments ?>
            </p>
        </div>

        <?php if (empty($approvedDocuments)): ?>
            <div class="text-center py-12 text-slate-300 px-6">
                <i class="fa-regular fa-folder-open text-5xl mb-3"></i>
                <p class="text-sm text-slate-400">Belum ada dokumen yang disetujui.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50">
                        <tr class="hover:bg-slate-50 transition text-left text-slate-600">
                            <th class="px-4 py-3 font-semibold border-b border-slate-200" data-sort>No</th>
                            <th class="px-4 py-3 font-semibold border-b border-slate-200" data-sort>Nama File</th>
                            <th class="px-4 py-3 font-semibold border-b border-slate-200" data-sort>Kategori</th>
                            <th class="px-4 py-3 font-semibold border-b border-slate-200" data-sort>Jenis</th>
                            <th class="px-4 py-3 font-semibold border-b border-slate-200" data-sort>Tanggal Dokumen</th>
                            <th class="px-4 py-3 font-semibold border-b border-slate-200" data-sort>Periode</th>
                            <th class="px-4 py-3 font-semibold border-b border-slate-200" data-sort>Tanggal Upload</th>
                            <th class="px-4 py-3 font-semibold border-b border-slate-200" data-sort>Status</th>
                            <th class="px-4 py-3 font-semibold border-b border-slate-200 text-center" data-sort>Aksi</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($approvedDocuments as $index => $doc): ?>
                            <?php
                            $statusInfo = mapDocumentStatus((string) ($doc['status'] ?? ''));
                            $rowNumber = (($approvedPage - 1) * $perPage) + $index + 1;
                            ?>
                            <tr class="hover:bg-slate-50 transition hover:bg-slate-50 transition">
                                <td class="px-4 py-4 align-top text-slate-500">
                                    <?= $rowNumber ?>
                                </td>

                                <td class="px-4 py-4 align-top min-w-[220px]">
                                    <p class="font-medium text-slate-800 break-words">
                                        <?= e(displayPustakaFileName($doc['file_name'] ?? '')) ?>
                                    </p>
                                </td>

                                <td class="px-4 py-4 align-top min-w-[170px] text-slate-600">
                                    <?= e((string) ($doc['kategori_dokumen'] ?? '-')) ?>
                                </td>

                                <td class="px-4 py-4 align-top min-w-[170px] text-slate-600">
                                    <?= e(appDisplayDocumentType($doc)) ?>
                                </td>

                                <td class="px-4 py-4 align-top whitespace-nowrap text-slate-600">
                                    <?= !empty($doc['tanggal_dokumen']) ? e(date('d F Y', strtotime((string) $doc['tanggal_dokumen']))) : '-' ?>
                                </td>

                                <td class="px-4 py-4 align-top whitespace-nowrap text-slate-600">
                                    <?php if (!empty($doc['periode_bulan'])): ?>
                                        <?= e(date('F Y', strtotime((string) $doc['periode_bulan'] . '-01'))) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>

                                <td class="px-4 py-4 align-top whitespace-nowrap text-slate-500">
                                    <?= !empty($doc['created_at']) ? e(date('d F Y H:i', strtotime((string) $doc['created_at']))) : '-' ?>
                                </td>

                                <td class="px-4 py-4 align-top whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs <?= e($statusInfo['class']) ?>">
                                        <i class="fa-solid <?= e($statusInfo['icon']) ?>"></i>
                                        <?= e($statusInfo['label']) ?>
                                    </span>
                                </td>

                                <td class="px-4 py-4 align-top text-center whitespace-nowrap">
                                    <div class="inline-flex flex-wrap items-center justify-center gap-2">
                                        <a
                                            href="../preview.php?id=<?= (int) ($doc['id'] ?? 0) ?>"
                                            target="_blank"
                                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 transition text-sm">
                                            <i class="fa-solid fa-eye"></i> Preview
                                        </a>
                                        <a
                                            href="../download.php?id=<?= (int) ($doc['id'] ?? 0) ?>"
                                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-primary text-white hover:bg-primaryDark transition text-sm">
                                            <i class="fa-solid fa-download"></i> Download
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalApprovedPages > 1): ?>
                <div class="px-6 py-4 border-t border-slate-200 flex flex-col md:flex-row items-center justify-between gap-4">
                    <div class="text-sm text-slate-500">
                        Halaman <?= $approvedPage ?> dari <?= $totalApprovedPages ?>
                    </div>

                    <div class="flex items-center gap-3">
                        <?php
                        $prevApprovedPage = max(1, $approvedPage - 1);
                        $nextApprovedPage = min($totalApprovedPages, $approvedPage + 1);
                        ?>

                        <?php if ($approvedPage <= 1): ?>
                            <span class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-slate-100 text-slate-400 text-sm cursor-not-allowed">
                                <i class="fa-solid fa-chevron-left"></i> Sebelumnya
                            </span>
                        <?php else: ?>
                            <a
                                href="<?= e(buildPaginationUrl(array_merge($paginationBaseParams, ['sp' => $statusPage, 'ap' => $prevApprovedPage]))) ?>"
                                class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-slate-100 text-slate-700 text-sm hover:bg-slate-200 transition">
                                <i class="fa-solid fa-chevron-left"></i> Sebelumnya
                            </a>
                        <?php endif; ?>

                        <?php if ($approvedPage >= $totalApprovedPages): ?>
                            <span class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-slate-100 text-slate-400 text-sm cursor-not-allowed">
                                Berikutnya <i class="fa-solid fa-chevron-right"></i>
                            </span>
                        <?php else: ?>
                            <a
                                href="<?= e(buildPaginationUrl(array_merge($paginationBaseParams, ['sp' => $statusPage, 'ap' => $nextApprovedPage]))) ?>"
                                class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-primary text-white text-sm hover:bg-primaryDark transition">
                                Berikutnya <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    const kategori = document.getElementById('filterKategori');
    const jenis = document.getElementById('filterJenis');

    if (kategori && jenis) {
        kategori.addEventListener('change', () => {
            jenis.value = '';
            jenis.disabled = true;
            const form = kategori.closest('form');
            if (form) form.submit();
        });
    }
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
