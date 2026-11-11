<?php
/* =========================================================
   UPLOAD DOKUMEN USER
   Fungsi: Memvalidasi dokumen PDF, Word, dan Excel, menyimpan file ke storage, menangani revisi, mendeteksi duplikat, dan mencatat data upload ke database.
========================================================= */

declare(strict_types=1);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$currentUserName = trim((string) ($_SESSION['name'] ?? ($_SESSION['username'] ?? '')));
$flashMsg = '';
$flashType = '';

$db = appDb();

$revisionId = max(0, (int) ($_POST['revision_id'] ?? ($_GET['revision_id'] ?? 0)));
$revisionDocument = null;
$isRevisionUpload = false;

if ($db instanceof mysqli) {
    appEnsureSimadaCoreSchema($db);
}

$oldKategori = trim((string) ($_POST['kategori_dokumen'] ?? ''));
$oldJenis = trim((string) ($_POST['jenis_dokumen'] ?? ''));
$oldJenisLainnya = trim((string) ($_POST['jenis_dokumen_lainnya'] ?? ''));
$oldTanggal = date('Y-m-d');

/* =========================================================
   BAGIAN FUNGSI VALIDASI UPLOAD
   Bagian ini menyiapkan fungsi validasi tabel, status revisi, MIME file, dan path penyimpanan.
========================================================= */







if (!function_exists('uploadFindRevisionDocument')) {
    function uploadFindRevisionDocument(mysqli $db, int $revisionId, int $userId): ?array
    {
        if ($revisionId <= 0 || $userId <= 0) {
            return null;
        }

        $stmt = $db->prepare(
            "SELECT id, user_id, file_name, stored_file_name, file_path, kategori_dokumen, jenis_dokumen, jenis_dokumen_lainnya, tanggal_dokumen, periode_bulan, status, catatan_admin, revision_count
             FROM documents
             WHERE id = ? AND user_id = ?
             LIMIT 1"
        );

        if (!$stmt) {
            return null;
        }

        $stmt->bind_param('ii', $revisionId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if (!$row || appNormalizeDocumentStatus((string) ($row['status'] ?? '')) !== 'revision') {
            return null;
        }

        return $row;
    }
}










if (!function_exists('generateStorageToken')) {
    function generateStorageToken(int $bytes = 16): string
    {
        return bin2hex(random_bytes($bytes));
    }
}

if (!function_exists('uploadProcessOptionalOcr')) {
    function uploadProcessOptionalOcr(mysqli $db, int $documentId, int $userId, string $absolutePath, array $meta): string
    {
        if ($documentId <= 0 || $userId <= 0 || !is_file($absolutePath)) {
            return ' OCR tidak diproses karena dokumen belum valid.';
        }

        $document = [
            'id' => $documentId,
            'user_id' => $userId,
            'file_path' => (string) ($meta['file_path'] ?? ''),
        ];

        $extract = appExtractTextFromPdfOptional($db, $absolutePath, $document);
        $engine = (string) ($extract['engine'] ?? 'none');

        if (($extract['ok'] ?? false) && trim((string) ($extract['text'] ?? '')) !== '') {
            $text = trim((string) $extract['text']);
            $stmtOcr = $db->prepare("UPDATE documents SET ocr_text = ?, ocr_status = 'processed', ocr_engine = ?, ocr_processed_at = NOW(), updated_at = NOW() WHERE id = ? AND user_id = ?");
            if ($stmtOcr) {
                $stmtOcr->bind_param('ssii', $text, $engine, $documentId, $userId);
                $stmtOcr->execute();
                $stmtOcr->close();
            }
            $successMessage = trim((string) ($extract['message'] ?? 'Teks berhasil diproses otomatis.'));
            return ' ' . $successMessage . ' Isi dokumen dapat dicari melalui Pustaka.';
        }

        $message = trim((string) ($extract['message'] ?? 'Ekstraksi teks otomatis belum berhasil.'));
        $stmtOcr = $db->prepare("UPDATE documents SET ocr_status = 'failed', ocr_engine = ?, ocr_processed_at = NOW(), updated_at = NOW() WHERE id = ? AND user_id = ?");
        if ($stmtOcr) {
            $stmtOcr->bind_param('sii', $engine, $documentId, $userId);
            $stmtOcr->execute();
            $stmtOcr->close();
        }

        return ' OCR belum berhasil diproses otomatis. ' . $message;
    }
}

$requiredColumns = [
    'user_id',
    'file_name',
    'stored_file_name',
    'file_path',
    'file_extension',
    'mime_type',
    'file_size',
    'file_hash',
    'kategori_dokumen',
    'jenis_dokumen',
    'jenis_dokumen_lainnya',
    'tanggal_dokumen',
    'periode_bulan',
    'status',
    'created_at',
    'updated_at'
];

$kategoriJenisMap = [];

if ($db instanceof mysqli) {
    $kategoriJenisMap = appLoadDocumentCategoryTypeMap($db);
}


if ($db instanceof mysqli && appTableExists($db, 'documents')) {
    appEnsureDocumentRevisionColumns($db);

    if ($revisionId > 0) {
        $revisionDocument = uploadFindRevisionDocument($db, $revisionId, $userId);

        if ($revisionDocument) {
            if ((int) ($revisionDocument['revision_count'] ?? 0) >= appMaxDocumentRevisions()) {
                $flashMsg = 'Dokumen ini sudah mencapai batas maksimal ' . appMaxDocumentRevisions() . ' kali revisi. Silakan hubungi admin untuk keputusan Disetujui atau Ditolak.';
                $flashType = 'error';
                $revisionId = 0;
            } else {
                $isRevisionUpload = true;

                $oldKategori = (string) ($revisionDocument['kategori_dokumen'] ?? '');
                $oldJenis = (string) ($revisionDocument['jenis_dokumen'] ?? '');
                $oldJenisLainnya = (string) ($revisionDocument['jenis_dokumen_lainnya'] ?? '');
                $oldTanggal = (string) ($revisionDocument['tanggal_dokumen'] ?? date('Y-m-d'));
            }
        } elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $flashMsg = 'Dokumen revisi tidak ditemukan atau statusnya bukan Perlu Revisi.';
            $flashType = 'error';
            $revisionId = 0;
        }
    }
}

/* =========================================================
   BAGIAN PROSES UPLOAD
   Bagian ini memeriksa input, menyimpan dokumen, mencegah file duplikat, dan mencatat dokumen ke database.
========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_upload'])) {
    $kategori = trim((string) ($_POST['kategori_dokumen'] ?? ''));
    $jenis = trim((string) ($_POST['jenis_dokumen'] ?? ''));
    $jenisLainnya = trim((string) ($_POST['jenis_dokumen_lainnya'] ?? ''));
    $isJenisLainnya = strcasecmp($jenis, 'Lainnya') === 0;

    if (!$isJenisLainnya) {
        $jenisLainnya = '';
    }

    $jenisLainnyaDb = $isJenisLainnya && $jenisLainnya !== '' ? $jenisLainnya : null;

    // Tanggal dibuat otomatis dari sistem agar periode penyimpanan selalu konsisten.
    $tanggalDokumen = date('Y-m-d');

    if ($revisionId > 0 && $db instanceof mysqli) {
        $revisionDocument = uploadFindRevisionDocument($db, $revisionId, $userId);

        if ($revisionDocument) {
            // Mode revisi hanya mengganti file. Kategori, jenis, dan tanggal dikunci dari dokumen lama.
            $kategori = trim((string) ($revisionDocument['kategori_dokumen'] ?? ''));
            $jenis = trim((string) ($revisionDocument['jenis_dokumen'] ?? ''));
            $jenisLainnya = trim((string) ($revisionDocument['jenis_dokumen_lainnya'] ?? ''));
            $isJenisLainnya = strcasecmp($jenis, 'Lainnya') === 0;
            $jenisLainnyaDb = $isJenisLainnya && $jenisLainnya !== '' ? $jenisLainnya : null;

            $tanggalLama = trim((string) ($revisionDocument['tanggal_dokumen'] ?? ''));
            $tanggalDokumen = strtotime($tanggalLama) ? date('Y-m-d', strtotime($tanggalLama)) : date('Y-m-d');
        }
    }

    $oldKategori = $kategori;
    $oldJenis = $jenis;
    $oldJenisLainnya = $jenisLainnya;
    $oldTanggal = $tanggalDokumen;
    // OCR ditentukan otomatis dari tipe file. PDF = aktif, selain PDF = nonaktif.
    $useOcr = false;

    // Periode tetap disimpan untuk kebutuhan filter.
    // Struktur folder fisik: Status/Kategori/Jenis[/Jenis Lainnya]/Tanggal/User/File.
    // Jika jenis = Lainnya, folder manual user berada di dalam folder lainnya.
    $periodeBulan = date('Y-m', strtotime($tanggalDokumen) ?: time());
    $jenisUntukFolder = $jenis;

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

    } elseif ($revisionId > 0 && !($revisionDocument = uploadFindRevisionDocument($db, $revisionId, $userId))) {
        $flashMsg = 'Dokumen revisi tidak ditemukan atau statusnya sudah berubah. Silakan cek daftar dokumen kembali.';
        $flashType = 'error';

    } elseif ($revisionId > 0 && $revisionDocument && (int) ($revisionDocument['revision_count'] ?? 0) >= appMaxDocumentRevisions()) {
        $flashMsg = 'Batas maksimal revisi adalah ' . appMaxDocumentRevisions() . ' kali. Dokumen ini tidak dapat diupload ulang sebagai revisi.';
        $flashType = 'error';

    } elseif ($kategori === '' || $jenis === '') {
        $flashMsg = 'Kategori dokumen dan jenis dokumen wajib diisi.';
        $flashType = 'error';

    } elseif ($revisionId <= 0 && $isJenisLainnya && $jenisLainnya === '') {
        $flashMsg = 'Jenis dokumen lainnya wajib diisi jika memilih Lainnya.';
        $flashType = 'error';

    } elseif ($revisionId <= 0 && !appIsValidDocumentCategoryType($db, $kategori, $jenis)) {
        $flashMsg = 'Kategori atau jenis dokumen tidak valid. Data harus dipilih dari master database.';
        $flashType = 'error';

    } elseif (!isset($_FILES['dokumen'])) {
        $flashMsg = 'File dokumen wajib dipilih.';
        $flashType = 'error';

    } elseif ((int) ($_FILES['dokumen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        $flashMsg = appDescribeUploadError((int) ($_FILES['dokumen']['error'] ?? UPLOAD_ERR_NO_FILE));
        $flashType = 'error';

    } else {
        $file = $_FILES['dokumen'];
        $maxSize = 10 * 1024 * 1024;
        $originalName = appSanitizeOriginalFilename((string) ($file['name'] ?? 'document'));
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        // Aturan final OCR: PDF selalu OCR otomatis; Word/Excel tidak menjalankan OCR.
        $useOcr = ($extension === 'pdf');
        $fileSize = (int) ($file['size'] ?? 0);
        $mimeType = appDetectUploadedMimeType($file);
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $fileHash = '';

        if (!appIsAllowedDocumentUpload($extension, $mimeType)) {
            $flashMsg = 'Format file tidak valid. Sistem hanya menerima PDF, Word (.doc/.docx), dan Excel (.xls/.xlsx).';
            $flashType = 'error';

        } elseif ($fileSize <= 0 || $fileSize > $maxSize) {
            $flashMsg = 'Ukuran file maksimal 10MB.';
            $flashType = 'error';

        } elseif ($tmpName === '' || !is_uploaded_file($tmpName)) {
            $flashMsg = 'File upload tidak valid.';
            $flashType = 'error';

        } else {
            $fileHash = hash_file('sha256', $tmpName) ?: '';
            if ($fileHash === '') {
                $flashMsg = 'Gagal membaca identitas file untuk validasi hash.';
                $flashType = 'error';
            } else {
            $storedFileName = $originalName;
            $relativePath = appBuildStorageRelativePath(
                'Pending',
                $kategori,
                $jenisUntukFolder,
                $userId,
                $storedFileName,
                $tanggalDokumen,
                $currentUserName,
                $jenisLainnya
            );
            $absolutePath = appResolveDocumentAbsolutePath($relativePath);
            $storageDir = dirname($absolutePath);

            if (!appEnsureDirectoryExists($storageDir)) {
                $flashMsg = 'Folder penyimpanan gagal dibuat. Pastikan user web server memiliki izin menulis ke folder storage (Windows: Apache/XAMPP; Ubuntu: www-data).';
                $flashType = 'error';
            } else {
                $storedFileName = appMakeUniqueStorageFilename($storageDir, $storedFileName);
                $relativePath = appBuildStorageRelativePath(
                    'Pending',
                    $kategori,
                    $jenisUntukFolder,
                    $userId,
                    $storedFileName,
                    $tanggalDokumen,
                    $currentUserName,
                    $jenisLainnya
                );
                $absolutePath = appResolveDocumentAbsolutePath($relativePath);
                $written = move_uploaded_file($tmpName, $absolutePath);

                if (!$written) {
                    $flashMsg = 'File gagal disimpan ke storage.';
                    $flashType = 'error';
                } else {
                    if ($revisionId > 0 && $revisionDocument) {
                        $stmt = $db->prepare(
                            "UPDATE documents
                             SET file_name = ?,
                                 stored_file_name = ?,
                                 file_path = ?,
                                 file_extension = ?,
                                 mime_type = ?,
                                 file_size = ?,
                                 file_hash = ?,
                                 ocr_text = NULL,
                                 ocr_status = 'not_processed',
                                 ocr_engine = NULL,
                                 ocr_processed_at = NULL,
                                 kategori_dokumen = ?,
                                 jenis_dokumen = ?,
                                 jenis_dokumen_lainnya = ?,
                                 tanggal_dokumen = ?,
                                 periode_bulan = ?,
                                 status = 'Pending',
                                 catatan_admin = NULL,
                                 revision_count = revision_count + 1,
                                 last_revision_at = NOW(),
                                 updated_at = NOW()
                             WHERE id = ?
                               AND user_id = ?
                               AND revision_count < ' . appMaxDocumentRevisions() . '
                               AND LOWER(TRIM(status)) IN ('revision', 'revisi', 'perlu revisi')"
                        );

                        if (!$stmt) {
                            @unlink($absolutePath);
                            appRemoveEmptyParentDirectories(dirname($absolutePath));
                            $flashMsg = 'Gagal memperbarui dokumen revisi di database.';
                            $flashType = 'error';
                        } else {
                            $stmt->bind_param(
                                'sssssissssssii',
                                $originalName,
                                $storedFileName,
                                $relativePath,
                                $extension,
                                $mimeType,
                                $fileSize,
                                $fileHash,
                                $kategori,
                                $jenis,
                                $jenisLainnyaDb,
                                $tanggalDokumen,
                                $periodeBulan,
                                $revisionId,
                                $userId
                            );

                            if ($stmt->execute() && $stmt->affected_rows > 0) {
                                appDeleteStoredFile((string) ($revisionDocument['file_path'] ?? ''), $absolutePath);

                                $ocrNote = '';
                                if ($useOcr && $extension === 'pdf') {
                                    $ocrNote = uploadProcessOptionalOcr($db, $revisionId, $userId, $absolutePath, [
                                        'file_path' => $relativePath,
                                    ]);
                                } elseif ($useOcr) {
                                    $ocrNote = ' OCR hanya tersedia untuk file PDF.';
                                }

                                $flashMsg = 'Dokumen revisi berhasil diupload. Status kembali menjadi Pending.' . $ocrNote;
                                $flashType = 'pending';
                                appCreateNotification($db, null, 'admin', 'Revisi dokumen masuk', 'User ' . $currentUserName . ' mengupload revisi dokumen: ' . $originalName, 'main.php?page=manajemen_dokumen');
                                $revisionId = 0;
                                $revisionDocument = null;
                                $isRevisionUpload = false;

                                $oldKategori = '';
                                $oldJenis = '';
                                $oldJenisLainnya = '';
                                $oldTanggal = date('Y-m-d');

                            } else {
                                @unlink($absolutePath);
                                appRemoveEmptyParentDirectories(dirname($absolutePath));
                                $flashMsg = 'Dokumen revisi gagal diperbarui. Pastikan status dokumen masih Perlu Revisi dan belum melewati batas ' . appMaxDocumentRevisions() . ' kali revisi.';
                                $flashType = 'error';
                            }

                            $stmt->close();
                        }
                    } else {
                        $stmt = $db->prepare(
                            'INSERT INTO documents (
                                user_id,
                                file_name,
                                stored_file_name,
                                file_path,
                                file_extension,
                                mime_type,
                                file_size,
                                file_hash,
                                kategori_dokumen,
                                jenis_dokumen,
                                jenis_dokumen_lainnya,
                                tanggal_dokumen,
                                periode_bulan,
                                status,
                                created_at,
                                updated_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
                        );

                        if (!$stmt) {
                            @unlink($absolutePath);
                            appRemoveEmptyParentDirectories(dirname($absolutePath));
                            $flashMsg = 'Gagal menyimpan metadata dokumen ke database.';
                            $flashType = 'error';
                        } else {
                            $status = 'Pending';

                            $stmt->bind_param(
                                'isssssisssssss',
                                $userId,
                                $originalName,
                                $storedFileName,
                                $relativePath,
                                $extension,
                                $mimeType,
                                $fileSize,
                                $fileHash,
                                $kategori,
                                $jenis,
                                $jenisLainnyaDb,
                                $tanggalDokumen,
                                $periodeBulan,
                                $status
                            );

                            if ($stmt->execute()) {
                                $documentId = (int) $stmt->insert_id;
                                $ocrNote = '';
                                if ($useOcr && $extension === 'pdf') {
                                    $ocrNote = uploadProcessOptionalOcr($db, $documentId, $userId, $absolutePath, [
                                        'file_path' => $relativePath,
                                    ]);
                                } elseif ($useOcr) {
                                    $ocrNote = ' OCR hanya tersedia untuk file PDF.';
                                }

                                $flashMsg = 'Dokumen berhasil diupload. File masuk ke status Pending dan menunggu verifikasi admin.' . $ocrNote;
                                $flashType = 'pending';
                                appCreateNotification($db, null, 'admin', 'Dokumen baru masuk', 'User ' . $currentUserName . ' mengupload dokumen: ' . $originalName, 'main.php?page=manajemen_dokumen');

                                $oldKategori = '';
                                $oldJenis = '';
                                $oldJenisLainnya = '';
                                $oldTanggal = date('Y-m-d');

                            } else {
                                @unlink($absolutePath);
                                appRemoveEmptyParentDirectories(dirname($absolutePath));
                                error_log('SIMADA upload insert error: ' . $stmt->error);
                                $flashMsg = 'Dokumen gagal disimpan ke database. Silakan coba lagi atau hubungi admin.';
                                $flashType = 'error';
                            }

                            $stmt->close();
                        }
                    }
                }
            }
            }
        }
    }
}
/* =========================================================
   BAGIAN TAMPILAN
   Bagian setelah tanda penutup PHP berisi HTML, CSS, atau JavaScript
   yang membentuk tampilan halaman untuk pengguna.
========================================================= */
?>

<div class="space-y-6">
    <?php if ($flashMsg !== ''): ?>
        <div class="rounded-xl p-4 text-sm <?= $flashType === 'pending' ? 'bg-yellow-50 border border-yellow-200 text-yellow-700' : 'bg-red-50 border border-red-200 text-red-700' ?>">
            <?= nl2br(e($flashMsg)) ?>
        </div>
    <?php endif; ?>

    <?php if ($isRevisionUpload && $revisionDocument): ?>
        <div class="rounded-2xl border border-blue-200 bg-blue-50 text-blue-700 p-4 text-sm">
            <div class="font-semibold mb-1">Mode upload revisi</div>
            <p>Anda sedang memperbarui dokumen: <strong><?= e(appDisplayFileName((string) ($revisionDocument['file_name'] ?? '-'))) ?></strong>.</p>
            <?php if (!empty($revisionDocument['catatan_admin'])): ?>
                <p class="mt-2">Catatan admin: <?= e((string) $revisionDocument['catatan_admin']) ?></p>
            <?php endif; ?>
            <p class="mt-2">Mode revisi hanya mengganti file. Kategori, jenis dokumen, tanggal, dan folder penyimpanan mengikuti data dokumen lama. Batas revisi maksimal <?= appMaxDocumentRevisions() ?> kali.</p>
            <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-2 text-xs">
                <div class="rounded-xl bg-white/70 border border-blue-100 px-3 py-2">
                    <span class="block text-blue-400">Kategori</span>
                    <strong><?= e((string) ($revisionDocument['kategori_dokumen'] ?? '-')) ?></strong>
                </div>
                <div class="rounded-xl bg-white/70 border border-blue-100 px-3 py-2">
                    <span class="block text-blue-400">Jenis</span>
                    <strong><?= e(appDisplayDocumentType($revisionDocument)) ?></strong>
                </div>
                <div class="rounded-xl bg-white/70 border border-blue-100 px-3 py-2">
                    <span class="block text-blue-400">Tanggal dokumen</span>
                    <strong><?= e((string) ($revisionDocument['tanggal_dokumen'] ?? '-')) ?></strong>
                </div>
            </div>
            <p class="mt-3">Setelah dikirim, dokumen yang sama akan kembali berstatus <strong>Pending</strong>. Sistem tidak membuat data dokumen baru.</p>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="submit_upload" value="1">

        <div class="grid grid-cols-4 gap-2 mb-6 text-xs font-medium text-center">
            <button type="button" data-step-indicator onclick="goStep(1)" class="rounded-full bg-primary text-white px-3 py-2">1. Kategori</button>
            <button type="button" data-step-indicator onclick="goStep(2)" class="rounded-full bg-slate-100 text-slate-500 px-3 py-2">2. Detail</button>
            <button type="button" data-step-indicator onclick="goStep(3)" class="rounded-full bg-slate-100 text-slate-500 px-3 py-2">3. File</button>
            <button type="button" data-step-indicator onclick="goStep(4)" class="rounded-full bg-slate-100 text-slate-500 px-3 py-2">4. Konfirmasi</button>
        </div>

        <?php if ($isRevisionUpload && $revisionId > 0): ?>
            <input type="hidden" name="revision_id" value="<?= (int) $revisionId ?>">
        <?php endif; ?>

        <div id="dropzone"
            class="bg-white rounded-2xl p-10 text-center shadow-sm border-2 border-dashed border-primary/40 cursor-pointer hover:border-primary transition">

            <input type="file" name="dokumen" id="fileInput" class="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>

            <div id="dropzoneContent" class="flex flex-col items-center gap-2">
                <div class="w-16 h-16 bg-primarySoft rounded-full flex items-center justify-center mb-2">
                    <i class="fa-solid fa-cloud-arrow-up text-primary text-2xl"></i>
                </div>
                <p class="font-semibold text-slate-700">Klik atau drag file ke sini</p>
                <p class="text-sm text-slate-400">Format: PDF, Word, Excel • Maks. 10MB</p>
            </div>

            <div id="filePreviewContainer" class="hidden mt-2">
                <div class="w-full max-w-md mx-auto bg-[#6d6868] rounded-xl px-3 py-3 flex items-center gap-3 shadow-md">
                    <button type="button"
                        onclick="removeFile()"
                        class="w-10 h-10 shrink-0 rounded-full bg-[#2d2d2d] text-white flex items-center justify-center hover:bg-black transition"
                        aria-label="Hapus file">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>

                    <div class="min-w-0 text-left flex-1">
                        <p id="fileName" class="text-white text-sm font-medium truncate"></p>
                        <p id="fileSize" class="text-slate-300 text-xs mt-1"></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-blue-100 bg-blue-50 text-blue-700 px-5 py-4 text-sm">
            <i class="fa-solid fa-circle-info mr-1"></i>
            Sistem menolak file yang sama jika sudah pernah diupload. Jika dokumen berstatus Perlu Revisi, unggah file hasil perbaikan melalui tombol Upload Ulang.
        </div>

        <div id="ocrOptionBox" class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 shadow-sm transition-all duration-200">
            <input type="checkbox" id="ocrAutoCheckbox" aria-label="Status OCR otomatis" disabled class="mt-1 h-4 w-4 rounded border-slate-300 text-reef focus:ring-reef disabled:opacity-100">
            <span class="text-sm leading-relaxed text-slate-600">
                <strong id="ocrOptionTitle" class="block text-slate-800">OCR otomatis</strong>
                <span id="ocrOptionHelp">Pilih file terlebih dahulu. OCR otomatis hanya digunakan untuk file PDF.</span>
            </span>
        </div>

        <?php if ($isRevisionUpload && $revisionDocument): ?>
            <input type="hidden" name="kategori_dokumen" value="<?= e((string) ($revisionDocument['kategori_dokumen'] ?? '')) ?>">
            <input type="hidden" name="jenis_dokumen" value="<?= e((string) ($revisionDocument['jenis_dokumen'] ?? '')) ?>">

            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                <div class="flex items-start gap-3 rounded-xl bg-yellow-50 border border-yellow-200 text-yellow-800 px-4 py-3 text-sm mb-5">
                    <i class="fa-solid fa-lock mt-0.5"></i>
                    <div>
                        <div class="font-semibold">Data dokumen dikunci saat revisi</div>
                        <p class="mt-1">User hanya mengganti file. Kategori, jenis dokumen, dan tanggal tidak bisa diubah agar revisi tetap masuk ke dokumen yang sama.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Kategori Dokumen</label>
                        <div class="w-full p-3 rounded-xl border text-sm bg-slate-50 text-slate-600 min-h-[46px]">
                            <?= e((string) ($revisionDocument['kategori_dokumen'] ?? '-')) ?>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Jenis Dokumen</label>
                        <div class="w-full p-3 rounded-xl border text-sm bg-slate-50 text-slate-600 min-h-[46px]">
                            <?= e(appDisplayDocumentType($revisionDocument)) ?>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Tanggal Dokumen</label>
                        <div class="w-full p-3 rounded-xl border text-sm bg-slate-50 text-slate-600 min-h-[46px]">
                            <?= e((string) ($revisionDocument['tanggal_dokumen'] ?? '-')) ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="bg-white border border-slate-200 rounded-2xl p-6 shadow-sm">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            Kategori Dokumen <span class="text-red-500">*</span>
                        </label>

                        <select name="kategori_dokumen" id="kategoriDokumen"
                            class="w-full p-3 rounded-xl border text-sm focus:outline-none focus:border-primary"
                            required>
                            <option value=""><?= empty($kategoriJenisMap) ? 'Master kategori belum tersedia' : 'Pilih kategori dokumen...' ?></option>
                            <?php foreach ($kategoriJenisMap as $kategori => $daftarJenis): ?>
                                <option value="<?= e($kategori) ?>" <?= $oldKategori === $kategori ? 'selected' : '' ?>>
                                    <?= e($kategori) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            Jenis Dokumen <span class="text-red-500">*</span>
                        </label>

                        <select name="jenis_dokumen" id="jenisDokumen"
                            class="w-full p-3 rounded-xl border text-sm focus:outline-none focus:border-primary"
                            required disabled>
                            <option value="">Pilih kategori terlebih dahulu...</option>
                        </select>

                        <input type="text" name="jenis_dokumen_lainnya" id="jenisDokumenLainnya"
                            class="w-full mt-3 p-3 rounded-xl border text-sm focus:outline-none focus:border-primary hidden"
                            placeholder="Tulis jenis dokumen lain..."
                            value="<?= e($oldJenisLainnya) ?>">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">
                            Tanggal Upload <span class="text-red-500">*</span>
                        </label>

                        <input type="date" name="tanggal_dokumen" id="tanggalDokumen"
                            class="w-full p-3 rounded-xl border text-sm bg-slate-50 text-slate-600 focus:outline-none focus:border-primary"
                            value="<?= date('Y-m-d') ?>"
                            min="<?= date('Y-m-d') ?>"
                            max="<?= date('Y-m-d') ?>"
                            readonly
                            required>
                        <p class="text-xs text-slate-400 mt-1">Tanggal dibuat otomatis dari sistem agar penyimpanan dokumen konsisten.</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <div class="flex items-center gap-4">
            <button type="submit" name="submit_upload"
                class="flex-1 py-3 rounded-xl bg-primary text-white hover:bg-primaryDark transition font-medium text-sm">
                <i class="fa-solid fa-cloud-arrow-up mr-2"></i> <?= $isRevisionUpload ? 'Upload Revisi' : 'Upload' ?>
            </button>

            <button type="button" onclick="resetFormUpload()"
                class="px-10 py-3 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition text-sm">
                Reset
            </button>
        </div>
    </form>
</div>

<script>
let currentStep = 1;
function goStep(n) {
    document.querySelectorAll('[data-step]').forEach(function (el) {
        el.classList.toggle('hidden', parseInt(el.dataset.step, 10) !== n);
    });
    document.querySelectorAll('[data-step-indicator]').forEach(function (el, i) {
        const active = i + 1 <= n;
        el.classList.toggle('bg-primary', active);
        el.classList.toggle('text-white', active);
        el.classList.toggle('bg-slate-100', !active);
        el.classList.toggle('text-slate-500', !active);
    });
    currentStep = n;
}

const jenisDokumenMap = <?= json_encode($kategoriJenisMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const oldKategori = <?= json_encode($oldKategori, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
const oldJenis = <?= json_encode($oldJenis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

const kategoriSelect = document.getElementById('kategoriDokumen');
const jenisSelect = document.getElementById('jenisDokumen');
const jenisLainnyaInput = document.getElementById('jenisDokumenLainnya');
const hasMetadataForm = kategoriSelect && jenisSelect && jenisLainnyaInput;

const dropzone = document.getElementById('dropzone');
const fileInput = document.getElementById('fileInput');
const dropzoneContent = document.getElementById('dropzoneContent');
const previewContainer = document.getElementById('filePreviewContainer');
const fileNameEl = document.getElementById('fileName');
const fileSizeEl = document.getElementById('fileSize');
const ocrCheckbox = document.getElementById('ocrAutoCheckbox');
const ocrOptionBox = document.getElementById('ocrOptionBox');
const ocrOptionTitle = document.getElementById('ocrOptionTitle');
const ocrOptionHelp = document.getElementById('ocrOptionHelp');
const allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];

function syncOcrOption(file = null) {
    if (!ocrCheckbox || !ocrOptionBox || !ocrOptionTitle || !ocrOptionHelp) return;

    let extension = '';
    if (file && file.name) {
        extension = (file.name.split('.').pop() || '').toLowerCase();
    }

    const isPdf = extension === 'pdf';
    ocrCheckbox.checked = isPdf;
    ocrCheckbox.disabled = true;

    ocrOptionBox.classList.remove('border-reef/30', 'bg-primarySoft', 'border-slate-200', 'bg-slate-50');
    if (isPdf) {
        ocrOptionBox.classList.add('border-reef/30', 'bg-primarySoft');
        ocrOptionTitle.textContent = 'OCR otomatis aktif';
        ocrOptionHelp.textContent = 'File PDF terdeteksi. SIMADA akan mencoba ekstraksi teks PDF, lalu OCR Tesseract jika PDF berupa scan.';
    } else if (extension !== '') {
        ocrOptionBox.classList.add('border-slate-200', 'bg-slate-50');
        ocrOptionTitle.textContent = 'OCR otomatis tidak tersedia';
        ocrOptionHelp.textContent = 'File ' + extension.toUpperCase() + ' tidak menggunakan OCR. Opsi ini dinonaktifkan otomatis.';
    } else {
        ocrOptionBox.classList.add('border-slate-200', 'bg-slate-50');
        ocrOptionTitle.textContent = 'OCR otomatis';
        ocrOptionHelp.textContent = 'Pilih file terlebih dahulu. OCR otomatis hanya digunakan untuk file PDF.';
    }
}

function renderJenisDokumen(selectedValue = '') {
    if (!hasMetadataForm) {
        return;
    }

    const kategori = kategoriSelect.value;
    jenisSelect.innerHTML = '';

    if (!kategori || !jenisDokumenMap[kategori]) {
        jenisSelect.innerHTML = '<option value="">Pilih kategori terlebih dahulu...</option>';
        jenisSelect.disabled = true;
        toggleJenisLainnya();
        return;
    }

    jenisSelect.disabled = false;
    jenisSelect.innerHTML = '<option value="">Pilih jenis dokumen...</option>';

    jenisDokumenMap[kategori].forEach(function(item) {
        const option = document.createElement('option');
        option.value = item;
        option.textContent = item;
        if (selectedValue && selectedValue === item) {
            option.selected = true;
        }
        jenisSelect.appendChild(option);
    });


    toggleJenisLainnya();
}

function toggleJenisLainnya() {
    if (!hasMetadataForm) {
        return;
    }

    if (jenisSelect.value === 'Lainnya') {
        jenisLainnyaInput.classList.remove('hidden');
        jenisLainnyaInput.required = true;
    } else {
        jenisLainnyaInput.classList.add('hidden');
        jenisLainnyaInput.required = false;
        if (jenisSelect.value !== 'Lainnya') {
            jenisLainnyaInput.value = jenisSelect.value === '' ? jenisLainnyaInput.value : jenisLainnyaInput.value;
        }
    }
}

if (hasMetadataForm) {
    kategoriSelect.addEventListener('change', function () {
        renderJenisDokumen('');
    });

    jenisSelect.addEventListener('change', toggleJenisLainnya);
}

dropzone.addEventListener('click', function (e) {
    if (!e.target.closest('button')) {
        fileInput.click();
    }
});

dropzone.addEventListener('dragover', function (e) {
    e.preventDefault();
    dropzone.classList.add('border-primaryDark', 'bg-slate-50');
});

dropzone.addEventListener('dragleave', function (e) {
    e.preventDefault();
    dropzone.classList.remove('border-primaryDark', 'bg-slate-50');
});

dropzone.addEventListener('drop', function (e) {
    e.preventDefault();
    dropzone.classList.remove('border-primaryDark', 'bg-slate-50');

    if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        showFilePreview(fileInput.files[0]);
    }
});

fileInput.addEventListener('change', function () {
    if (this.files.length) {
        showFilePreview(this.files[0]);
    }
});

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
}

function showFilePreview(file) {
    const extension = (file.name.split('.').pop() || '').toLowerCase();
    if (!allowedExtensions.includes(extension)) {
        alert('Format file tidak valid. Gunakan PDF, Word (.doc/.docx), atau Excel (.xls/.xlsx).');
        removeFile();
        return;
    }
    if (file.size <= 0 || file.size > 10 * 1024 * 1024) {
        alert('Ukuran file maksimal 10MB.');
        removeFile();
        return;
    }

    fileNameEl.textContent = file.name;
    fileSizeEl.textContent = formatFileSize(file.size);
    syncOcrOption(file);

    previewContainer.classList.remove('hidden');
    dropzoneContent.classList.add('hidden');
}

function removeFile() {
    fileInput.value = '';
    previewContainer.classList.add('hidden');
    dropzoneContent.classList.remove('hidden');
    syncOcrOption(null);
}

function resetFormUpload() {
    document.querySelector('form').reset();
    removeFile();
    renderJenisDokumen('');
    toggleJenisLainnya();
}

if (hasMetadataForm) {
    renderJenisDokumen(oldJenis);
}
syncOcrOption(fileInput && fileInput.files && fileInput.files.length ? fileInput.files[0] : null);
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
    form.addEventListener('submit', function(event) {
        const btn = event.submitter || this.querySelector('[type=submit]:not([data-no-load])');
        if (btn && btn.name) {
            const mirror = document.createElement('input');
            mirror.type = 'hidden';
            mirror.name = btn.name;
            mirror.value = btn.value || '1';
            mirror.setAttribute('data-submit-mirror', '1');
            this.appendChild(mirror);
        }
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

