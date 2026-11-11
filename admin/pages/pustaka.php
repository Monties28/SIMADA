<?php
/* =========================================================
   PUSTAKA ADMIN
   Fungsi: Menampilkan dokumen yang tersimpan dengan filter user, kategori, jenis dokumen, status, dan tahun.
========================================================= */

declare(strict_types=1);
$db = appDb();

if (!$db) {
    echo "<div class='bg-red-50 border border-red-200 text-red-700 rounded-xl p-4 text-sm'>
            Koneksi database tidak tersedia.
          </div>";
    return;
}

/* =========================================================
   BAGIAN FUNGSI DAN FILTER PUSTAKA
   Bagian ini menyiapkan fungsi bantu, filter pencarian, dan query daftar dokumen.
========================================================= */


if (!function_exists('adminPustakaBuildUrl')) {
    function adminPustakaBuildUrl(array $params = []): string
    {
        $base = ['page' => 'pustaka'];
        return 'main.php?' . http_build_query(array_merge($base, $params));
    }
}






$kategoriJenisMap = [
    'DOKUMEN TEKNIS PRODUKSI & BUDIDAYA' => [
        'Laporan Produksi/Pemijahan Ikan & Udang',
        'Laporan Pemeliharaan Larva & Benih',
        'Laporan Pendederan Benih',
        'Laporan Pembesaran Ikan & Udang',
        'Laporan Panen Benih dan Ikan Konsumsi',
        'Laporan Pengelolaan Kualitas Air',
        'Laporan Pengapuran dan Pemupukan Kolam',
        'Laporan Pemberian Pakan, Vitamin, dan Obat',
    ],
    'DOKUMEN ADMINISTRASI & KESEKRETARIATAN' => [
        'Laporan Arsip Surat Masuk dan Keluar',
        'Laporan Pengetikan dan Penomoran Surat',
        'Surat Perintah Tugas (SPT)',
        'Surat Pengantar Resmi',
        'Nota Dinas dan Memorandum',
    ],
    'DOKUMEN KEUANGAN & ANGGARAN' => [
        'Laporan Realisasi Anggaran (SIRA)',
        'Laporan Rekapitulasi Belanja',
        'Laporan Penjualan Hasil Produksi',
        'Rekapitulasi Pengajuan SPM',
        'Laporan Barang Pakai Habis (BPH)',
    ],
    'DOKUMEN PENGADAAN BARANG/JASA' => [
        'Dokumen Tender dan Pengadaan',
        'Surat Pesanan Pembelian',
        'Surat Perubahan RUP',
        'Berita Acara Pemeriksaan Barang',
        'Kontrak dan Perjanjian Kerja',
    ],
    'DOKUMEN MANAJEMEN KINERJA' => [
        'Laporan E-Kinerja Pegawai',
        'Laporan E-SAKIP Triwulanan',
        'Laporan Survei Kepuasan Masyarakat',
        'Laporan Evaluasi Kinerja Instansi',
    ],
    'DOKUMEN KEPEGAWAIAN & SDM' => [
        'Daftar Induk dan Database Pegawai',
        'Laporan Rekapitulasi Absensi',
        'Laporan Perjalanan Dinas (PERDIN)',
        'Berkas Kepegawaian dan Penilaian Kinerja',
    ],
    'DOKUMEN ASET & LOGISTIK' => [
        'Laporan Inventarisasi Barang Milik Daerah (BMD)',
        'Laporan Pemeliharaan Sarana dan Prasarana',
        'Laporan Penggunaan Barang Inventaris',
        'Berita Acara Serah Terima Barang',
    ],
    'DOKUMEN PERENCANAAN & EVALUASI' => [
        'Laporan Rencana Strategis (RENSTRA)',
        'Laporan Rencana Kerja Tahunan',
        'Laporan Monitoring dan Evaluasi Program',
        'Laporan Capaian Kinerja Triwulanan/Tahunan',
    ],
    'DOKUMEN PEMASARAN & DISTRIBUSI' => [
        'Laporan Promosi dan Pemasaran',
        'Laporan Penjualan dan Distribusi Benih',
        'Dokumentasi Kegiatan Pameran/Promosi',
    ],
    'DOKUMEN TEKNIS PERAWATAN FASILITAS' => [
        'Laporan Pemeliharaan Listrik dan Genset',
        'Laporan Perawatan Alat dan Mesin Budidaya',
        'Laporan Kebersihan dan Perawatan Gedung',
    ],
];

$search = trim((string) ($_GET['search'] ?? ''));
$filterTahun = trim((string) ($_GET['tahun'] ?? ''));
$filterKategori = trim((string) ($_GET['kategori'] ?? ''));
$filterJenis = trim((string) ($_GET['jenis'] ?? ''));
$filterUser = trim((string) ($_GET['user'] ?? ''));
$tanggalDari = trim((string) ($_GET['tanggal_dari'] ?? ''));
$tanggalSampai = trim((string) ($_GET['tanggal_sampai'] ?? ''));

$hasUsersTable = appTableExists($db, 'users');
$hasDocumentsTable = appTableExists($db, 'documents');
$hasOcrText = $hasDocumentsTable && appTableHasColumn($db, 'documents', 'ocr_text');

$usersFilterList = [];
$usersCards = [];
$documentsRows = [];
$selectedUserName = 'Semua User';
$totalFilteredDocuments = 0;

if ($hasDocumentsTable) {
    $sqlDistinctKategoriJenis = "
        SELECT DISTINCT kategori_dokumen, jenis_dokumen, jenis_dokumen_lainnya
        FROM documents
        WHERE LOWER(TRIM(status)) IN ('approved','disetujui')
          AND kategori_dokumen IS NOT NULL
          AND kategori_dokumen <> ''
          AND jenis_dokumen IS NOT NULL
          AND jenis_dokumen <> ''
    ";
    $resultDistinct = $db->query($sqlDistinctKategoriJenis);
    if ($resultDistinct) {
        while ($row = $resultDistinct->fetch_assoc()) {
            $kategori = trim((string) ($row['kategori_dokumen'] ?? ''));
            $jenis = appDisplayDocumentType($row);
            if ($kategori === '' || $jenis === '') {
                continue;
            }
            if (!isset($kategoriJenisMap[$kategori])) {
                $kategoriJenisMap[$kategori] = [];
            }
            if (!in_array($jenis, $kategoriJenisMap[$kategori], true)) {
                $kategoriJenisMap[$kategori][] = $jenis;
            }
        }
    }
}

$kategoriList = array_keys($kategoriJenisMap);
sort($kategoriList, SORT_NATURAL | SORT_FLAG_CASE);
$allJenisList = appFlattenDocumentTypeMap($kategoriJenisMap);

if ($hasUsersTable) {
    $sqlUsers = "
        SELECT id, name
        FROM users
        WHERE role = 'user'
          AND is_active = 1
        ORDER BY name ASC
    ";
    $resultUsers = $db->query($sqlUsers);
    if ($resultUsers) {
        while ($row = $resultUsers->fetch_assoc()) {
            $usersFilterList[] = [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? '-'),
            ];
        }
    }
    if ($filterUser !== '') {
        foreach ($usersFilterList as $userItem) {
            if ((string) $userItem['id'] === $filterUser) {
                $selectedUserName = $userItem['name'];
                break;
            }
        }
    }
}

if ($hasUsersTable) {
    if ($hasDocumentsTable) {
        $sqlCards = "
            SELECT
                u.id,
                u.name,
                COUNT(d.id) AS documents
            FROM users u
            LEFT JOIN documents d ON d.user_id = u.id
             AND LOWER(TRIM(d.status)) IN ('approved','disetujui')
            WHERE u.role = 'user'
              AND u.is_active = 1
        ";
        $types = '';
        $params = [];
        $conditions = [];

        if ($search !== '') {
            $searchLike = '%' . $search . '%';
            $searchParts = ["u.name LIKE ?", "d.file_name LIKE ?", "d.kategori_dokumen LIKE ?", "d.jenis_dokumen LIKE ?", "d.jenis_dokumen_lainnya LIKE ?"];
            $types .= 'sssss';
            $params[] = $searchLike;
            $params[] = $searchLike;
            $params[] = $searchLike;
            $params[] = $searchLike;
            $params[] = $searchLike;
            if ($hasOcrText) {
                $searchParts[] = "d.ocr_text LIKE ?";
                $types .= 's';
                $params[] = $searchLike;
            }
            $conditions[] = '(' . implode(' OR ', $searchParts) . ')';
        }
        if ($filterTahun !== '') {
            $conditions[] = "YEAR(d.tanggal_dokumen) = ?";
            $types .= 'i';
            $params[] = (int) $filterTahun;
        }
        if ($filterKategori !== '') {
            $conditions[] = "d.kategori_dokumen = ?";
            $types .= 's';
            $params[] = $filterKategori;
        }
        if ($filterJenis !== '') {
            $conditions[] = "(d.jenis_dokumen = ? OR d.jenis_dokumen_lainnya = ?)";
            $types .= 'ss';
            $params[] = $filterJenis;
            $params[] = $filterJenis;
        }
        if ($filterUser !== '') {
            $conditions[] = "u.id = ?";
            $types .= 'i';
            $params[] = (int) $filterUser;
        }
        if ($tanggalDari !== '') {
            $conditions[] = "DATE(d.tanggal_dokumen) >= ?";
            $types .= 's';
            $params[] = $tanggalDari;
        }
        if ($tanggalSampai !== '') {
            $conditions[] = "DATE(d.tanggal_dokumen) <= ?";
            $types .= 's';
            $params[] = $tanggalSampai;
        }

        if (!empty($conditions)) {
            $sqlCards .= ' AND ' . implode(' AND ', $conditions);
        }
        $sqlCards .= "
            GROUP BY u.id, u.name
            ORDER BY u.name ASC
        ";

        $stmtCards = $db->prepare($sqlCards);
        if ($stmtCards) {
            if ($types !== '') {
                $stmtCards->bind_param($types, ...$params);
            }
            $stmtCards->execute();
            $resultCards = $stmtCards->get_result();
            while ($row = $resultCards->fetch_assoc()) {
                $docCount = (int) ($row['documents'] ?? 0);
                $usersCards[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => (string) ($row['name'] ?? '-'),
                    'documents' => $docCount,
                ];
                $totalFilteredDocuments += $docCount;
            }
            $stmtCards->close();
        }
    } else {
        $sqlCards = "
            SELECT id, name
            FROM users
            WHERE role = 'user'
              AND is_active = 1
            ORDER BY name ASC
        ";
        $resultCards = $db->query($sqlCards);
        if ($resultCards) {
            while ($row = $resultCards->fetch_assoc()) {
                $usersCards[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'name' => (string) ($row['name'] ?? '-'),
                    'documents' => 0,
                ];
            }
        }
    }
}

if ($hasDocumentsTable && $filterUser !== '') {
    $sqlDocs = "
        SELECT
            d.id,
            d.file_name,
            d.file_path,
            d.kategori_dokumen,
            d.jenis_dokumen,
            d.jenis_dokumen_lainnya,
            d.tanggal_dokumen,
            d.periode_bulan,
            d.status,
            d.created_at,
            d.file_extension,
            d.file_size,
            u.name AS user_name
        FROM documents d
        INNER JOIN users u ON u.id = d.user_id
        WHERE d.user_id = ?
          AND LOWER(TRIM(d.status)) IN ('approved','disetujui')
    ";
    $types = 'i';
    $params = [(int) $filterUser];

    if ($search !== '') {
        $searchLike = '%' . $search . '%';
        $searchParts = ["d.file_name LIKE ?", "d.kategori_dokumen LIKE ?", "d.jenis_dokumen LIKE ?", "d.jenis_dokumen_lainnya LIKE ?"];
        $types .= 'ssss';
        $params[] = $searchLike;
        $params[] = $searchLike;
        $params[] = $searchLike;
        $params[] = $searchLike;
        if ($hasOcrText) {
            $searchParts[] = "d.ocr_text LIKE ?";
            $types .= 's';
            $params[] = $searchLike;
        }
        $sqlDocs .= ' AND (' . implode(' OR ', $searchParts) . ')';
    }
    if ($filterTahun !== '') {
        $sqlDocs .= " AND YEAR(d.tanggal_dokumen) = ?";
        $types .= 'i';
        $params[] = (int) $filterTahun;
    }
    if ($filterKategori !== '') {
        $sqlDocs .= " AND d.kategori_dokumen = ?";
        $types .= 's';
        $params[] = $filterKategori;
    }
    if ($filterJenis !== '') {
        $sqlDocs .= " AND (d.jenis_dokumen = ? OR d.jenis_dokumen_lainnya = ?)";
        $types .= 'ss';
        $params[] = $filterJenis;
        $params[] = $filterJenis;
    }
    if ($tanggalDari !== '') {
        $sqlDocs .= " AND DATE(d.tanggal_dokumen) >= ?";
        $types .= 's';
        $params[] = $tanggalDari;
    }
    if ($tanggalSampai !== '') {
        $sqlDocs .= " AND DATE(d.tanggal_dokumen) <= ?";
        $types .= 's';
        $params[] = $tanggalSampai;
    }
    $sqlDocs .= " ORDER BY d.tanggal_dokumen DESC, d.id DESC";

    $stmtDocs = $db->prepare($sqlDocs);
    if ($stmtDocs) {
        appBindParams($stmtDocs, $types, $params);
        $stmtDocs->execute();
        $resultDocs = $stmtDocs->get_result();
        while ($row = $resultDocs->fetch_assoc()) {
            $documentsRows[] = $row;
        }
        $stmtDocs->close();
    }
}
/* =========================================================
   BAGIAN TAMPILAN
   Bagian setelah tanda penutup PHP berisi HTML, CSS, atau JavaScript
   yang membentuk tampilan halaman untuk pengguna.
========================================================= */
?>

<div class="space-y-6">
    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
        <h3 class="font-semibold text-slate-700 flex items-center gap-2 mb-6 text-sm">
            <i class="fa-solid fa-filter text-primary"></i> Filter Dokumen
        </h3>
        <form method="GET" action="main.php" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5 text-sm">
            <input type="hidden" name="page" value="pustaka">
            <div class="xl:col-span-2">
                <label class="font-medium text-slate-700">Pencarian Cepat</label>
                <div class="relative mt-2">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                    <input
                        type="text"
                        name="search"
                        value="<?= e($search) ?>"
                        placeholder="Cari nama file, kategori, jenis, user, atau isi OCR..."
                        class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:border-primary bg-white">
                </div>
            </div>
            <div>
                <label class="font-medium text-slate-700">Tahun Dokumen</label>
                <select
                    name="tahun"
                    class="w-full mt-2 py-2.5 px-4 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-primary bg-white">
                    <option value="">Semua Tahun</option>
                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                        <option value="<?= $y ?>" <?= $filterTahun === (string) $y ? 'selected' : '' ?>>
                            <?= $y ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label class="font-medium text-slate-700">Kategori Dokumen</label>
                <select
                    name="kategori"
                    id="kategoriFilterAdmin"
                    class="w-full mt-2 py-2.5 px-4 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-primary bg-white">
                    <option value="">Semua Kategori</option>
                    <?php foreach ($kategoriList as $kategori): ?>
                        <option value="<?= e($kategori) ?>" <?= $filterKategori === $kategori ? 'selected' : '' ?>>
                            <?= e($kategori) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="font-medium text-slate-700">Jenis Dokumen</label>
                <select
                    name="jenis"
                    id="jenisFilterAdmin"
                    class="w-full mt-2 py-2.5 px-4 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-primary bg-white">
                    <option value="">Semua Jenis</option>
                </select>
            </div>
            <div>
                <label class="font-medium text-slate-700">User</label>
                <select
                    name="user"
                    class="w-full mt-2 py-2.5 px-4 rounded-xl border border-slate-200 text-sm focus:outline-none focus:border-primary bg-white">
                    <option value="">Semua User</option>
                    <?php foreach ($usersFilterList as $user): ?>
                        <option value="<?= (int) $user['id'] ?>" <?= $filterUser === (string) $user['id'] ? 'selected' : '' ?>>
                            <?= e($user['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="font-medium text-slate-700">Tanggal Upload Dari</label>
                <div class="relative mt-2">
                    <i class="fa-regular fa-calendar absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input
                        type="date"
                        name="tanggal_dari"
                        value="<?= e($tanggalDari) ?>"
                        class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:border-primary bg-white">
                </div>
            </div>
            <div>
                <label class="font-medium text-slate-700">Tanggal Upload Sampai</label>
                <div class="relative mt-2">
                    <i class="fa-regular fa-calendar absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input
                        type="date"
                        name="tanggal_sampai"
                        value="<?= e($tanggalSampai) ?>"
                        class="w-full pl-9 pr-4 py-2.5 rounded-xl border border-slate-200 focus:outline-none focus:border-primary bg-white">
                </div>
            </div>
            <div class="xl:col-span-4 flex flex-wrap gap-3 pt-2">
                <button
                    type="submit"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary text-white hover:bg-primaryDark transition">
                    <i class="fa-solid fa-filter"></i> Terapkan Filter
                </button>
                <a
                    href="main.php?page=pustaka"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
            </div>
        </form>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
            <p class="text-xs uppercase tracking-wide text-slate-400">Lokasi</p>
            <p class="mt-2 text-sm font-semibold text-slate-700"><?= e($selectedUserName) ?></p>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
            <p class="text-xs uppercase tracking-wide text-slate-400">User Ditampilkan</p>
            <p class="mt-2 text-2xl font-semibold text-slate-800"><?= count($usersCards) ?></p>
        </div>
        <div class="bg-white rounded-2xl p-5 shadow-sm border border-slate-100">
            <p class="text-xs uppercase tracking-wide text-slate-400">Total Dokumen Disetujui</p>
            <p class="mt-2 text-2xl font-semibold text-slate-800"><?= (int) $totalFilteredDocuments ?></p>
        </div>
    </div>

    <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
        <h3 class="font-semibold text-slate-700 flex items-center gap-2 mb-5 text-sm">
            <i class="fa-solid fa-users text-primary"></i> Daftar User
        </h3>
        <?php if (!$hasUsersTable): ?>
            <div class="rounded-2xl border border-red-200 bg-red-50 py-8 px-6 text-center text-red-700 text-sm">
                Tabel users tidak ditemukan.
            </div>
        <?php elseif (empty($usersCards)): ?>
            <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 py-14 px-6 text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-primarySoft flex items-center justify-center text-primary">
                    <i class="fa-regular fa-folder-open text-2xl"></i>
                </div>
                <p class="text-base font-medium text-slate-500">Tidak ada hasil yang sesuai</p>
                <p class="text-sm text-slate-400 mt-1">Coba ubah filter kategori, jenis, user, atau tanggal upload</p>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
                <?php foreach ($usersCards as $user): ?>
                    <div class="border border-slate-200 rounded-2xl p-4 hover:bg-slate-50 transition">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-4 min-w-0">
                                <div class="w-12 h-12 rounded-xl bg-primarySoft flex items-center justify-center text-primary shrink-0">
                                    <i class="fa-regular fa-user text-lg"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-semibold text-slate-800 text-sm truncate">
                                        <?= e($user['name']) ?>
                                    </p>
                                    <p class="text-xs text-slate-500">
                                        <?= (int) $user['documents'] ?> dokumen disetujui
                                    </p>
                                </div>
                            </div>
                            <?php if ((int) $user['documents'] > 0): ?>
                                <a
                                    href="<?= e(adminPustakaBuildUrl([
                                        'search' => $search,
                                        'tahun' => $filterTahun,
                                        'kategori' => $filterKategori,
                                        'jenis' => $filterJenis,
                                        'tanggal_dari' => $tanggalDari,
                                        'tanggal_sampai' => $tanggalSampai,
                                        'user' => $user['id'],
                                    ])) ?>"
                                    title="Lihat dokumen user ini"
                                    class="w-9 h-9 rounded-lg bg-slate-50 border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-primary hover:text-white hover:border-primary transition">
                                    <i class="fa-solid fa-chevron-right text-xs"></i>
                                </a>
                            <?php else: ?>
                                <span class="w-9 h-9 rounded-lg bg-slate-100 border border-slate-200 flex items-center justify-center text-slate-300 cursor-not-allowed">
                                    <i class="fa-solid fa-chevron-right text-xs"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($filterUser !== ''): ?>
        <div class="bg-white rounded-2xl p-6 shadow-sm border border-slate-100">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-5">
                <div>
                    <h3 class="font-semibold text-slate-700 flex items-center gap-2 text-sm">
                        <i class="fa-regular fa-folder-open text-primary"></i> Dokumen Disetujui User
                    </h3>
                    <p class="text-sm text-slate-500 mt-1">
                        Menampilkan dokumen yang sudah disetujui dari <strong><?= e($selectedUserName) ?></strong>
                    </p>
                </div>
                <a
                    href="<?= e(adminPustakaBuildUrl([
                        'search' => $search,
                        'tahun' => $filterTahun,
                        'kategori' => $filterKategori,
                        'jenis' => $filterJenis,
                        'tanggal_dari' => $tanggalDari,
                        'tanggal_sampai' => $tanggalSampai,
                    ])) ?>"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-50 transition text-sm">
                    <i class="fa-solid fa-xmark"></i> Tutup Tabel User
                </a>
            </div>
            <?php if (empty($documentsRows)): ?>
                <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 py-12 px-6 text-center">
                    <p class="text-base font-medium text-slate-500">Tidak ada dokumen yang sesuai</p>
                    <p class="text-sm text-slate-400 mt-1">User ini belum memiliki dokumen disetujui pada filter yang dipilih</p>
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
                                <th class="px-4 py-3 font-semibold border-b border-slate-200" data-sort>Tanggal Upload</th>
                                <th class="px-4 py-3 font-semibold border-b border-slate-200" data-sort>Periode</th>
                                <th class="px-4 py-3 font-semibold border-b border-slate-200" data-sort>Upload</th>
                                <th class="px-4 py-3 font-semibold border-b border-slate-200" data-sort>Status</th>
                                <th class="px-4 py-3 font-semibold border-b border-slate-200 text-center" data-sort>Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($documentsRows as $index => $doc): ?>
                                <?php
                                $statusInfo = appPustakaStatusInfo((string) ($doc['status'] ?? ''));
                                $extension = strtolower((string) ($doc['file_extension'] ?? ''));
                                $absolutePath = appResolveDocumentAbsolutePath((string) ($doc['file_path'] ?? ''));
                                $fileExists = $absolutePath !== null && is_file($absolutePath);
                                ?>
                                <tr class="hover:bg-slate-50 transition hover:bg-slate-50 transition">
                                    <td class="px-4 py-4 align-top text-slate-500"><?= $index + 1 ?></td>
                                    <td class="px-4 py-4 align-top min-w-[220px]">
                                        <div class="font-medium text-slate-800 break-words">
                                            <?= e(appDisplayFileName($doc['file_name'] ?? '-')) ?>
                                        </div>
                                        <div class="text-xs text-slate-500 mt-1">
                                            <?= strtoupper(e($extension !== '' ? $extension : '-')) ?>,
                                            <?= number_format(((int) ($doc['file_size'] ?? 0)) / 1024, 2) ?> KB
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 align-top min-w-[180px] text-slate-600">
                                        <?= e((string) ($doc['kategori_dokumen'] ?? '-')) ?>
                                    </td>
                                    <td class="px-4 py-4 align-top min-w-[180px] text-slate-600">
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
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs <?= $statusInfo['class'] ?>">
                                            <?= e($statusInfo['label']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-4 align-top text-center whitespace-nowrap">
                                        <?php if ($fileExists): ?>
                                            <div class="inline-flex flex-wrap items-center justify-center gap-2">
                                                <a
                                                    href="../preview.php?id=<?= (int) ($doc['id'] ?? 0) ?>"
                                                    target="_blank"
                                                    class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100 transition text-sm">
                                                    <i class="fa-solid fa-eye"></i> Preview
                                                </a>
                                                <a
                                                    href="../download.php?id=<?= (int) ($doc['id'] ?? 0) ?>"
                                                    class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-primary text-white hover:bg-primaryDark transition text-sm">
                                                    <i class="fa-solid fa-download"></i> Download
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-slate-100 text-slate-400 cursor-not-allowed text-sm">
                                                <i class="fa-solid fa-ban"></i> File tidak ada
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<script>
const kategoriJenisMapAdmin = <?= json_encode($kategoriJenisMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const allJenisListAdmin = <?= json_encode($allJenisList, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const selectedJenisAdmin = <?= json_encode($filterJenis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const kategoriFilterAdmin = document.getElementById('kategoriFilterAdmin');
const jenisFilterAdmin = document.getElementById('jenisFilterAdmin');

function renderJenisFilterAdmin() {
    const kategori = kategoriFilterAdmin.value;
    jenisFilterAdmin.innerHTML = '<option value="">Semua Jenis</option>';
    let jenisList = [];
    if (kategori && kategoriJenisMapAdmin[kategori]) {
        jenisList = kategoriJenisMapAdmin[kategori];
    } else {
        jenisList = allJenisListAdmin;
    }
    jenisList.forEach(function (jenis) {
        const option = document.createElement('option');
        option.value = jenis;
        option.textContent = jenis;
        if (selectedJenisAdmin === jenis) {
            option.selected = true;
        }
        jenisFilterAdmin.appendChild(option);
    });
}

kategoriFilterAdmin.addEventListener('change', function () {
    const currentKategori = kategoriFilterAdmin.value;
    jenisFilterAdmin.innerHTML = '<option value="">Semua Jenis</option>';
    let jenisList = [];
    if (currentKategori && kategoriJenisMapAdmin[currentKategori]) {
        jenisList = kategoriJenisMapAdmin[currentKategori];
    } else {
        jenisList = allJenisListAdmin;
    }
    jenisList.forEach(function (jenis) {
        const option = document.createElement('option');
        option.value = jenis;
        option.textContent = jenis;
        jenisFilterAdmin.appendChild(option);
    });
    jenisFilterAdmin.value = '';
});

renderJenisFilterAdmin();
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

