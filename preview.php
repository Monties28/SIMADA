<?php
/* =========================================================
   PREVIEW DOKUMEN SIMADA
   Fungsi: Membuka halaman preview, bukan memaksa browser download.
   PDF ditampilkan langsung. Word/Excel dikonversi otomatis ke PDF
   bila LibreOffice tersedia pada Windows/XAMPP atau Linux/Ubuntu Server.
========================================================= */

declare(strict_types=1);

require_once __DIR__ . '/config/security.php';
appSecureSessionStart();

require_once __DIR__ . '/config/koneksi.php';
require_once __DIR__ . '/config/helpers.php';

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$db = appDb();
$userId = (int) ($_SESSION['user_id'] ?? 0);
$role = (string) ($_SESSION['role'] ?? '');
$documentId = (int) ($_GET['id'] ?? 0);
$raw = (string) ($_GET['raw'] ?? '') === '1';

if (!$db) {
    http_response_code(500);
    exit('Koneksi database tidak tersedia.');
}
appEnsureSimadaCoreSchema($db);

if ($documentId <= 0) {
    http_response_code(400);
    exit('Parameter dokumen tidak valid.');
}

if ($role === 'admin') {
    $stmt = $db->prepare('SELECT id, user_id, file_name, file_path, file_extension, status FROM documents WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $documentId);
    }
} else {
    $stmt = $db->prepare("SELECT id, user_id, file_name, file_path, file_extension, status FROM documents WHERE id = ? AND user_id = ? AND LOWER(TRIM(status)) IN ('approved', 'disetujui') LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('ii', $documentId, $userId);
    }
}

if (!$stmt) {
    http_response_code(500);
    exit('Query gagal disiapkan.');
}

$stmt->execute();
$result = $stmt->get_result();
$document = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$document) {
    http_response_code(404);
    exit('Dokumen tidak ditemukan atau belum dapat dipreview.');
}

$relativePath = trim((string) ($document['file_path'] ?? ''));
$relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
if ($relativePath === '' || str_contains($relativePath, '..')) {
    http_response_code(400);
    exit('Path file tidak valid.');
}

$absolutePath = appResolveDocumentAbsolutePath($relativePath, false);
if (!$absolutePath || !is_file($absolutePath)) {
    http_response_code(404);
    exit('File tidak ditemukan.');
}

$fileName = appDisplayFileName((string) ($document['file_name'] ?? basename($absolutePath)));
$fileName = $fileName !== '' ? $fileName : basename($absolutePath);
$extension = appPreviewFileExtension($document, $fileName);
$baseName = pathinfo($fileName, PATHINFO_FILENAME);
$baseName = $baseName !== '' ? $baseName : 'dokumen';

$previewPdfPath = null;
$previewReady = false;
$previewKind = 'unsupported';
$previewMessage = '';

if ($extension === 'pdf') {
    $previewPdfPath = $absolutePath;
    $previewReady = true;
    $previewKind = 'pdf';
} elseif (appCanConvertOfficePreview($extension)) {
    $previewPdfPath = appOfficePreviewPdfPath($absolutePath, $document);
    if ($previewPdfPath && is_file($previewPdfPath)) {
        $previewReady = true;
        $previewKind = 'office_pdf';
    } else {
        $previewKind = 'office_need_converter';
        $previewMessage = appIsWindows()
            ? 'Preview Word/Excel membutuhkan LibreOffice. Install LibreOffice Desktop, lalu restart Apache XAMPP.'
            : 'Preview Word/Excel membutuhkan LibreOffice. Install paket libreoffice pada Ubuntu Server dan pastikan user www-data dapat menjalankannya.';
    }
} else {
    $previewMessage = 'Jenis file ini belum didukung untuk preview langsung.';
}

if ($raw) {
    appSendSecurityHeaders(false, 'SAMEORIGIN', "'self'");
    if (!$previewReady || !$previewPdfPath || !is_file($previewPdfPath)) {
        http_response_code(404);
        exit('Preview belum tersedia.');
    }

    $inlineName = $extension === 'pdf' ? $fileName : ($baseName . '.pdf');
    appOutputDocumentBinary($db, $previewPdfPath, $document, $inlineName, 'application/pdf', 'inline');
}

appSendSecurityHeaders(false, 'SAMEORIGIN', "'self'");
$rawUrl = 'preview.php?id=' . (int) $documentId . '&raw=1&v=' . rawurlencode((string) (@filemtime($previewPdfPath ?: $absolutePath) ?: time()));
$downloadUrl = 'download.php?id=' . (int) $documentId;
$status = appNormalizeDocumentState((string) ($document['status'] ?? ''));
$statusLabel = $status !== '' ? $status : '-';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preview Dokumen - <?= e($fileName) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        :root {
            color-scheme: light dark;
            --bg: #eef8f9;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #475569;
            --border: #cbd5e1;
            --primary: #0f766e;
            --primaryDark: #115e59;
            --soft: #e0f2fe;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #08111f;
                --card: #111827;
                --text: #f8fafc;
                --muted: #cbd5e1;
                --border: #334155;
                --primary: #2dd4bf;
                --primaryDark: #14b8a6;
                --soft: #0f2537;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: radial-gradient(circle at top left, rgba(45,212,191,.25), transparent 35%), var(--bg);
            color: var(--text);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .wrap { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .topbar {
            display: flex;
            gap: 16px;
            align-items: center;
            justify-content: space-between;
            padding: 18px 20px;
            border: 1px solid var(--border);
            background: color-mix(in srgb, var(--card) 92%, transparent);
            border-radius: 22px;
            box-shadow: 0 18px 45px rgba(15,23,42,.12);
            margin-bottom: 18px;
        }
        .title { min-width: 0; }
        .title h1 { margin: 0; font-size: 18px; line-height: 1.3; word-break: break-word; }
        .title p { margin: 8px 0 0; color: var(--muted); font-size: 13px; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: flex-end; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 800;
            font-size: 13px;
            border: 1px solid var(--border);
            color: var(--text);
            background: var(--card);
        }
        .btn.primary { background: var(--primary); border-color: var(--primary); color: #ffffff; }
        .viewer {
            height: calc(100vh - 145px);
            min-height: 520px;
            border: 1px solid var(--border);
            border-radius: 22px;
            overflow: hidden;
            background: var(--card);
            box-shadow: 0 18px 45px rgba(15,23,42,.10);
        }
        iframe { width: 100%; height: 100%; border: 0; background: #ffffff; }
        .empty {
            display: grid;
            place-items: center;
            min-height: 520px;
            padding: 34px;
            text-align: center;
            border: 1px solid var(--border);
            border-radius: 22px;
            background: var(--card);
        }
        .empty .icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 18px;
            display: grid;
            place-items: center;
            border-radius: 24px;
            background: var(--soft);
            color: var(--primary);
            font-size: 30px;
        }
        .empty h2 { margin: 0 0 10px; font-size: 21px; }
        .empty p { margin: 0 auto 18px; max-width: 720px; color: var(--muted); line-height: 1.7; }
        code { background: rgba(148,163,184,.22); padding: 3px 6px; border-radius: 8px; color: var(--text); }
        @media (max-width: 720px) {
            .wrap { padding: 14px; }
            .topbar { align-items: flex-start; flex-direction: column; }
            .actions { width: 100%; justify-content: stretch; }
            .btn { flex: 1; justify-content: center; }
            .viewer { height: calc(100vh - 210px); min-height: 420px; }
        }
    </style>
</head>
<body>
    <main class="wrap">
        <section class="topbar">
            <div class="title">
                <h1><i class="fa-solid fa-eye"></i> Preview: <?= e($fileName) ?></h1>
                <p>Ekstensi: <?= e(strtoupper($extension ?: '-')) ?> · Status: <?= e($statusLabel) ?><?= $previewKind === 'office_pdf' ? ' · Preview hasil konversi PDF' : '' ?></p>
            </div>
            <div class="actions">
                <a class="btn" href="javascript:window.close()"><i class="fa-solid fa-xmark"></i> Tutup</a>
                <a class="btn primary" href="<?= e($downloadUrl) ?>"><i class="fa-solid fa-download"></i> Download</a>
            </div>
        </section>

        <?php if ($previewReady): ?>
            <section class="viewer" aria-label="Area preview dokumen">
                <iframe src="<?= e($rawUrl) ?>" title="Preview <?= e($fileName) ?>"></iframe>
            </section>
        <?php else: ?>
            <section class="empty">
                <div>
                    <div class="icon"><i class="fa-solid fa-file-circle-exclamation"></i></div>
                    <h2>Preview belum tersedia</h2>
                    <p><?= e($previewMessage ?: 'File ini tidak dapat dipreview langsung oleh browser.') ?></p>
                    <?php if ($previewKind === 'office_need_converter'): ?>
                        <?php if (appIsWindows()): ?>
                            <p>Windows/XAMPP:</p>
                            <p><code>Install LibreOffice Desktop, lalu restart Apache XAMPP.</code></p>
                        <?php else: ?>
                            <p>Ubuntu Server:</p>
                            <p><code>sudo apt install libreoffice-writer libreoffice-calc -y</code></p>
                            <p>Jika tidak terdeteksi otomatis, isi <code>LIBREOFFICE_PATH=/usr/bin/libreoffice</code> pada <code>.env</code>.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                    <a class="btn primary" href="<?= e($downloadUrl) ?>"><i class="fa-solid fa-download"></i> Download Dokumen</a>
                </div>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
