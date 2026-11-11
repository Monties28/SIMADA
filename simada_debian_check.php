<?php
/* =========================================================
   SIMADA Linux/Ubuntu Readiness Check
   Hapus file ini setelah deployment berhasil.
========================================================= */
declare(strict_types=1);

require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/helpers.php';
simadaLoadEnv();

function h(mixed $v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function row(string $label, bool $ok, string $note = ''): string {
    $status = $ok ? 'OK' : 'CEK';
    $color = $ok ? '#16a34a' : '#dc2626';
    return '<tr><td>'.h($label).'</td><td style="font-weight:700;color:'.$color.'">'.$status.'</td><td><code>'.h($note).'</code></td></tr>';
}
function iniBytes(string $value): int {
    $value = trim($value);
    if ($value === '') return 0;
    $unit = strtolower(substr($value, -1));
    $num = (float) $value;
    return match ($unit) {
        'g' => (int) ($num * 1024 * 1024 * 1024),
        'm' => (int) ($num * 1024 * 1024),
        'k' => (int) ($num * 1024),
        default => (int) $num,
    };
}
function canShellExec(): bool {
    if (!function_exists('shell_exec')) return false;
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    return !in_array('shell_exec', $disabled, true);
}

$rows = [];
$rows[] = row('PHP >= 8.0', version_compare(PHP_VERSION, '8.0.0', '>='), PHP_VERSION . ' / ' . PHP_SAPI);
$rows[] = row('User proses PHP', true, trim((string) @shell_exec('whoami 2>/dev/null')) ?: get_current_user());
$rows[] = row('file_uploads', filter_var(ini_get('file_uploads'), FILTER_VALIDATE_BOOLEAN), (string) ini_get('file_uploads'));
$rows[] = row('upload_max_filesize >= 10M', iniBytes((string) ini_get('upload_max_filesize')) >= 10*1024*1024, (string) ini_get('upload_max_filesize'));
$rows[] = row('post_max_size >= 12M', iniBytes((string) ini_get('post_max_size')) >= 12*1024*1024, (string) ini_get('post_max_size'));
$rows[] = row('memory_limit cukup', ini_get('memory_limit') === '-1' || iniBytes((string) ini_get('memory_limit')) >= 128*1024*1024, (string) ini_get('memory_limit'));
$uploadTmp = (string) ini_get('upload_tmp_dir');
$uploadTmp = $uploadTmp !== '' ? $uploadTmp : sys_get_temp_dir();
$rows[] = row('Upload tmp writable', is_dir($uploadTmp) && is_writable($uploadTmp), $uploadTmp);
$sessionPath = (string) session_save_path();
$rows[] = row('Session path writable', $sessionPath === '' || (is_dir($sessionPath) && is_writable($sessionPath)), $sessionPath ?: '(default)');

foreach (['mysqli','openssl','fileinfo','mbstring','pdo_mysql'] as $ext) {
    $rows[] = row('PHP extension '.$ext, extension_loaded($ext), extension_loaded($ext) ? 'loaded' : 'missing');
}

$rows[] = row('APP_MASTER_KEY set', trim((string) simadaEnv('APP_MASTER_KEY', '')) !== '', trim((string) simadaEnv('APP_MASTER_KEY', '')) !== '' ? 'set' : 'kosong/default; sebaiknya isi tetap');

$storageBase = appStorageBasePath();
$docs = appDocumentsBasePath();
$profile = appProfilePhotosBasePath();
$temp = appTempStorageBasePath();
foreach ([$storageBase, $docs, $profile, $temp] as $dir) {
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    @chmod($dir, 0775);
    $rows[] = row('Folder writable: '.$dir, is_dir($dir) && is_writable($dir), 'owner='.@fileowner($dir).' perms='.substr(sprintf('%o', @fileperms($dir)), -4));
}

$rows[] = row('shell_exec available', canShellExec(), (string) ini_get('disable_functions'));
$pdftotext = canShellExec() ? appResolveOcrExecutable('PDFTOTEXT_PATH', ['pdftotext.exe','pdftotext']) : '';
$pdftoppm = canShellExec() ? appResolveOcrExecutable('PDFTOPPM_PATH', ['pdftoppm.exe','pdftoppm']) : '';
$tesseract = canShellExec() ? appResolveOcrExecutable('TESSERACT_PATH', ['tesseract.exe','tesseract']) : '';
$libreoffice = appFindLibreOfficeBinary() ?? '';
$rows[] = row('pdftotext tersedia', $pdftotext !== '', $pdftotext ?: 'sudo apt install poppler-utils -y');
$rows[] = row('pdftoppm tersedia', $pdftoppm !== '', $pdftoppm ?: 'sudo apt install poppler-utils -y');
$rows[] = row('Tesseract tersedia', $tesseract !== '', $tesseract ?: 'sudo apt install tesseract-ocr tesseract-ocr-ind tesseract-ocr-eng -y');
$rows[] = row('LibreOffice tersedia', $libreoffice !== '', $libreoffice ?: 'sudo apt install libreoffice-writer libreoffice-calc -y');

$dbOk = false; $dbNote = '';
$host = (string) simadaEnv('DB_HOST', '');
$user = (string) simadaEnv('DB_USERNAME', '');
$pass = (string) simadaEnv('DB_PASSWORD', '');
$dbName = (string) simadaEnv('DB_DATABASE', '');
$port = (int) simadaEnv('DB_PORT', 3306);
if ($host && $user && $dbName) {
    $mysqli = @new mysqli($host, $user, $pass, $dbName, $port);
    if ($mysqli->connect_error) {
        $dbNote = $mysqli->connect_error;
    } else {
        $dbOk = true;
        $mysqli->set_charset('utf8mb4');
        appEnsureSimadaCoreSchema($mysqli);
        appEnsureDocumentRevisionColumns($mysqli);
        $needed = ['users','documents','document_categories','document_types','notifications','attendance_records'];
        $missing = [];
        foreach ($needed as $table) {
            $safe = $mysqli->real_escape_string($table);
            $r = $mysqli->query("SHOW TABLES LIKE '{$safe}'");
            if (!$r || $r->num_rows === 0) $missing[] = $table;
        }
        $dbNote = $missing ? 'missing: '.implode(', ', $missing) : 'connected; core tables OK';
        $mysqli->close();
    }
} else {
    $dbNote = 'Lengkapi .env DB_HOST/DB_USERNAME/DB_PASSWORD/DB_DATABASE';
}
$rows[] = row('Database', $dbOk, $dbNote);
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SIMADA Linux/Ubuntu Check</title><style>body{font-family:Arial,sans-serif;background:#f8fafc;margin:0;padding:24px;color:#0f172a}main{max-width:1100px;margin:auto;background:#fff;border:1px solid #e2e8f0;border-radius:18px;padding:24px;box-shadow:0 12px 35px rgba(15,23,42,.08)}table{width:100%;border-collapse:collapse}td,th{border-bottom:1px solid #e2e8f0;padding:10px;text-align:left;vertical-align:top}code{white-space:pre-wrap}.warn{background:#fff7ed;border:1px solid #fed7aa;border-radius:12px;padding:14px;margin:16px 0}</style></head><body><main><h1>SIMADA Linux/Ubuntu Readiness Check</h1><div class="warn"><b>Penting:</b> hapus file <code>simada_debian_check.php</code> setelah upload, OCR, enkripsi, dan revisi sudah normal.</div><table><thead><tr><th>Komponen</th><th>Status</th><th>Catatan</th></tr></thead><tbody><?= implode('', $rows) ?></tbody></table></main></body></html>
