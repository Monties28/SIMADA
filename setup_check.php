<?php
/* =========================================================
   CEK INSTALASI SIMADA - WINDOWS/XAMPP & LINUX/UBUNTU
   Hapus file ini setelah aplikasi berhasil berjalan.
========================================================= */

declare(strict_types=1);

require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/helpers.php';
simadaLoadEnv();

function checkRow(string $label, bool $ok, string $note = ''): string
{
    $status = $ok ? 'OK' : 'GAGAL';
    $color = $ok ? '#16a34a' : '#dc2626';
    return '<tr><td>' . e($label) . '</td><td style="color:' . $color . ';font-weight:700">' . $status . '</td><td>' . e($note) . '</td></tr>';
}

function checkShellAvailable(): bool
{
    if (!function_exists('shell_exec')) {
        return false;
    }
    $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));
    return !in_array('shell_exec', $disabled, true);
}

$windows = appIsWindows();
$platform = $windows ? 'Windows / XAMPP' : 'Linux / Ubuntu Server target';
$rows = [];
$rows[] = checkRow('Sistem operasi', true, $platform . ' · ' . PHP_OS_FAMILY);
$rows[] = checkRow('Versi PHP', version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION . ' · ' . PHP_SAPI);
foreach (['mysqli', 'openssl', 'fileinfo', 'mbstring'] as $ext) {
    $rows[] = checkRow('Ekstensi ' . $ext, extension_loaded($ext), extension_loaded($ext) ? 'Aktif' : 'Belum aktif');
}
$rows[] = checkRow('File .env', is_file(__DIR__ . '/.env'), is_file(__DIR__ . '/.env') ? 'Ditemukan' : 'Belum dibuat');

$dbOk = false;
$dbNote = '';
$tablesNote = '';
$host = (string) simadaEnv('DB_HOST', '');
$user = (string) simadaEnv('DB_USERNAME', '');
$pass = (string) simadaEnv('DB_PASSWORD', '');
$db = (string) simadaEnv('DB_DATABASE', '');
$port = (int) simadaEnv('DB_PORT', 3306);
if (!class_exists('mysqli')) {
    $dbNote = 'Ekstensi mysqli belum aktif.';
} elseif ($host !== '' && $user !== '' && $db !== '' && !str_contains($host, 'XXX')) {
    $mysqli = @new mysqli($host, $user, $pass, $db, $port);
    if ($mysqli->connect_error) {
        $dbNote = $mysqli->connect_error;
    } else {
        $dbOk = true;
        $mysqli->set_charset('utf8mb4');
        $requiredTables = ['users', 'documents', 'document_categories', 'document_types', 'notifications', 'attendance_records'];
        $missing = [];
        foreach ($requiredTables as $table) {
            $safeTable = $mysqli->real_escape_string($table);
            $result = $mysqli->query("SHOW TABLES LIKE '{$safeTable}'");
            if (!$result || $result->num_rows === 0) {
                $missing[] = $table;
            }
        }
        $tablesNote = empty($missing) ? 'Semua tabel utama tersedia' : 'Tabel belum ada: ' . implode(', ', $missing);
        $mysqli->close();
    }
} else {
    $dbNote = 'Isi DB_HOST, DB_USERNAME, DB_PASSWORD, dan DB_DATABASE pada file .env';
}
$rows[] = checkRow('Koneksi database', $dbOk, $dbNote ?: 'Tersambung');
$rows[] = checkRow('Tabel database', $dbOk && $tablesNote === 'Semua tabel utama tersedia', $tablesNote ?: 'Belum dicek');

$documentsPath = appDocumentsBasePath();
$storageOk = is_dir($documentsPath) || @mkdir($documentsPath, $windows ? 0775 : 0750, true);
$writable = $storageOk && is_writable($documentsPath);
$storageFailNote = $windows
    ? 'Periksa permission folder storage dan hak tulis Apache/XAMPP.'
    : 'Periksa ownership/permission. Umumnya: sudo chown -R www-data:www-data /srv/documents && sudo chmod -R 750 /srv/documents';
$rows[] = checkRow('Folder storage', $storageOk, $documentsPath);
$rows[] = checkRow('Hak tulis storage', $writable, $writable ? 'Bisa menulis file upload' : $storageFailNote);

$shellOk = checkShellAvailable();
$rows[] = checkRow('PHP shell_exec', $shellOk, $shellOk ? 'Aktif untuk ekstraksi PDF/OCR' : 'Tidak tersedia; Smart OCR tidak dapat dijalankan');

$pdfToTextPath = $shellOk ? appResolveOcrExecutable('PDFTOTEXT_PATH', ['pdftotext.exe', 'pdftotext']) : '';
$pdfToPpmPath = $shellOk ? appResolveOcrExecutable('PDFTOPPM_PATH', ['pdftoppm.exe', 'pdftoppm']) : '';
$tesseractPath = $shellOk ? appResolveOcrExecutable('TESSERACT_PATH', ['tesseract.exe', 'tesseract']) : '';
$libreOfficePath = appFindLibreOfficeBinary() ?? '';

$pathHint = $windows ? 'Atur path pada .env atau PATH Windows.' : 'Install paket Ubuntu atau atur path /usr/bin/... pada .env.';
$rows[] = checkRow('pdftotext (PDF digital)', $pdfToTextPath !== '', $pdfToTextPath !== '' ? $pdfToTextPath : 'Belum ditemukan. ' . $pathHint);
$rows[] = checkRow('pdftoppm (PDF scan)', $pdfToPpmPath !== '', $pdfToPpmPath !== '' ? $pdfToPpmPath : 'Belum ditemukan. ' . $pathHint);
$rows[] = checkRow('Tesseract OCR', $tesseractPath !== '', $tesseractPath !== '' ? $tesseractPath : 'Belum ditemukan. ' . $pathHint);

$langNote = 'Belum dicek';
$langOk = false;
if ($tesseractPath !== '' && $shellOk) {
    $raw = (string) @shell_exec(escapeshellarg($tesseractPath) . ' --list-langs' . appExecutableNullRedirect());
    $langs = [];
    foreach (preg_split('/\R/', $raw) ?: [] as $line) {
        $line = trim($line);
        if ($line !== '' && stripos($line, 'list of available languages') === false) {
            $langs[] = $line;
        }
    }
    $langOk = in_array('ind', $langs, true) && in_array('eng', $langs, true);
    $langNote = $langs ? implode(', ', $langs) : 'Tidak ada bahasa yang terbaca';
}
$rows[] = checkRow('Bahasa OCR ind + eng', $langOk, $langNote);
$rows[] = checkRow('LibreOffice (preview Word/Excel)', $libreOfficePath !== '', $libreOfficePath !== '' ? $libreOfficePath : ($windows ? 'Install LibreOffice Desktop.' : 'sudo apt install libreoffice-writer libreoffice-calc -y'));

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cek Instalasi SIMADA</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f8fafc;color:#0f172a;margin:0;padding:24px}main{max-width:1050px;margin:auto;background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:24px;box-shadow:0 12px 35px rgba(15,23,42,.08)}table{width:100%;border-collapse:collapse;margin-top:18px}td,th{border-bottom:1px solid #e2e8f0;padding:12px;text-align:left;vertical-align:top}code{background:#f1f5f9;padding:3px 6px;border-radius:6px}.warn{background:#fff7ed;border:1px solid #fed7aa;padding:14px;border-radius:12px;margin-top:18px}.info{background:#ecfeff;border:1px solid #a5f3fc;padding:14px;border-radius:12px;margin-top:18px;line-height:1.65}@media(max-width:760px){body{padding:10px}main{padding:15px;overflow-x:auto}table{min-width:760px}}
    </style>
</head>
<body>
<main>
    <h1>Cek Instalasi SIMADA</h1>
    <p>Halaman ini mendeteksi platform secara otomatis dan dapat dipakai pada <strong>Windows/XAMPP</strong> maupun <strong>Ubuntu Server</strong>.</p>
    <table>
        <thead><tr><th>Komponen</th><th>Status</th><th>Catatan</th></tr></thead>
        <tbody><?= implode('', $rows) ?></tbody>
    </table>
    <?php if ($windows): ?>
        <div class="info"><strong>Windows:</strong> Poppler dan Tesseract boleh memakai path dari <code>.env</code> atau PATH Windows. LibreOffice Desktop dipakai untuk preview Word/Excel.</div>
    <?php else: ?>
        <div class="info"><strong>Ubuntu Server:</strong> komponen yang direkomendasikan: <code>sudo apt install poppler-utils tesseract-ocr tesseract-ocr-ind tesseract-ocr-eng libreoffice-writer libreoffice-calc -y</code>. Storage production disarankan berada di <code>/srv/documents</code> dan writable oleh <code>www-data</code>.</div>
    <?php endif; ?>
    <div class="warn"><strong>Penting:</strong> hapus atau batasi akses file <code>setup_check.php</code> setelah instalasi selesai.</div>
</main>
</body>
</html>
