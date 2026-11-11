<?php
/* =========================================================
   HELPER APLIKASI
   Fungsi: Menyediakan fungsi bantu untuk status dokumen, kategori, path storage, alert, dan tampilan.
========================================================= */

declare(strict_types=1);

$simadaStorageConfig = is_file(__DIR__ . '/storage.php') ? require __DIR__ . '/storage.php' : [];

/* =========================================================
   BAGIAN HELPER GLOBAL
   Bagian ini berisi fungsi bantu yang dipakai oleh halaman admin dan user.
========================================================= */
if (!function_exists('appDb')) {
    function appDb(): ?mysqli
    {
        global $conn, $koneksi;

        if (isset($conn) && $conn instanceof mysqli) {
            return $conn;
        }

        if (isset($koneksi) && $koneksi instanceof mysqli) {
            return $koneksi;
        }

        return null;
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}


if (!function_exists('appDisplayFileName')) {
    function appDisplayFileName(?string $fileName): string
    {
        $fileName = trim((string) $fileName);

        if ($fileName === '') {
            return '-';
        }

        $fileName = basename($fileName);
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $nameOnly = $extension !== '' ? substr($fileName, 0, -(strlen($extension) + 1)) : $fileName;

        $nameOnly = str_replace('_', ' ', $nameOnly);
        $nameOnly = preg_replace('/\s+/', ' ', $nameOnly) ?? $nameOnly;
        $nameOnly = trim($nameOnly);

        if ($nameOnly === '') {
            $nameOnly = 'document';
        }

        return $extension !== '' ? $nameOnly . '.' . $extension : $nameOnly;
    }
}

if (!function_exists('appPasswordPolicyDescription')) {
    function appPasswordPolicyDescription(): string
    {
        return 'Password minimal 8 karakter dan wajib mengandung huruf besar, angka, serta simbol.';
    }
}

if (!function_exists('appIsStrongPassword')) {
    function appIsStrongPassword(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[^A-Za-z0-9]/', $password) === 1;
    }
}

if (!function_exists('appDocumentHashExists')) {
    function appDocumentHashExists(mysqli $db, int $userId, string $fileHash): bool
    {
        // SIMADA sekarang mengizinkan file yang sama diunggah pada folder/kategori berbeda.
        return false;
    }
}

if (!function_exists('appCurrentDatabaseName')) {
    function appCurrentDatabaseName(mysqli $db): string
    {
        $result = $db->query('SELECT DATABASE() AS db_name');

        if (!$result) {
            return '';
        }

        $row = $result->fetch_assoc();

        return (string) ($row['db_name'] ?? '');
    }
}

if (!function_exists('appTableExists')) {
    function appTableExists(mysqli $db, string $table): bool
    {
        $sql = "SELECT COUNT(*) AS total
                FROM information_schema.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?";

        $stmt = $db->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('s', $table);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;

        $stmt->close();

        return (int) ($row['total'] ?? 0) > 0;
    }
}

if (!function_exists('appTableHasColumn')) {
    function appTableHasColumn(mysqli $db, string $table, string $column): bool
    {
        $sql = "SELECT COUNT(*) AS total
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME = ?";

        $stmt = $db->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();

        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;

        $stmt->close();

        return (int) ($row['total'] ?? 0) > 0;
    }
}

if (!function_exists('appNormalizeDocumentStatus')) {
    function appNormalizeDocumentStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'approved', 'disetujui' => 'approved',
            'rejected', 'ditolak' => 'rejected',
            'revision', 'revisi', 'perlu revisi' => 'revision',
            default => 'pending',
        };
    }
}

if (!function_exists('appDocumentStatusLabel')) {
    function appDocumentStatusLabel(string $status): string
    {
        return match (appNormalizeDocumentStatus($status)) {
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'revision' => 'Perlu Revisi',
            default => 'Pending',
        };
    }
}

if (!function_exists('appDocumentStatusBadgeClass')) {
    function appDocumentStatusBadgeClass(string $status): string
    {
        return match (appNormalizeDocumentStatus($status)) {
            'approved' => 'bg-green-50 text-green-600 border border-green-200',
            'rejected' => 'bg-red-50 text-red-600 border border-red-200',
            'revision' => 'bg-blue-50 text-blue-600 border border-blue-200',
            default => 'bg-yellow-50 text-yellow-700 border border-yellow-200',
        };
    }
}

if (!function_exists('appDocumentStatusProgress')) {
    function appDocumentStatusProgress(string $status): int
    {
        return match (appNormalizeDocumentStatus($status)) {
            'approved', 'rejected' => 100,
            'revision' => 80,
            default => 60,
        };
    }
}

if (!function_exists('appAlertClass')) {
    function appAlertClass(string $type): string
    {
        return $type === 'error'
            ? 'bg-red-50 border border-red-200 text-red-700'
            : 'bg-yellow-50 border border-yellow-200 text-yellow-700';
    }
}

if (!function_exists('appFormatDateTime')) {
    function appFormatDateTime(?string $value): string
    {
        if (!$value) {
            return '-';
        }

        $timestamp = strtotime($value);

        if (!$timestamp) {
            return '-';
        }

        return date('d-m-Y H:i', $timestamp);
    }
}

if (!function_exists('appSlugifyStorageSegment')) {
    function appSlugifyStorageSegment(string $value, string $fallback = 'lainnya'): string
    {
        $value = strtolower(trim($value));
        $value = str_replace('&', ' ', $value);
        $value = str_replace(['/', '\\'], ' ', $value);
        $value = preg_replace('/\((.*?)\)/', ' $1 ', $value) ?? $value;
        $value = preg_replace('/[^a-z0-9]+/', '_', $value) ?? $value;
        $value = trim($value, '_');
        $value = preg_replace('/_+/', '_', $value) ?? $value;

        return $value !== '' ? $value : $fallback;
    }
}

if (!function_exists('appStorageStatusFolder')) {
    function appStorageStatusFolder(string $status): string
    {
        return match (appNormalizeDocumentStatus($status)) {
            'approved' => 'Document',
            'rejected' => 'Rejected',
            default => 'Pending',
        };
    }
}

if (!function_exists('appProjectRoot')) {
    function appProjectRoot(): string
    {
        return dirname(__DIR__);
    }
}


if (!function_exists('appIsWindows')) {
    function appIsWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }
}

if (!function_exists('appPlatformLabel')) {
    function appPlatformLabel(): string
    {
        return appIsWindows() ? 'Windows/XAMPP' : 'Linux/Ubuntu Server';
    }
}

if (!function_exists('appExecutableNullRedirect')) {
    function appExecutableNullRedirect(): string
    {
        return appIsWindows() ? ' 2>NUL' : ' 2>/dev/null';
    }
}

if (!function_exists('appResolveExecutable')) {
    function appResolveExecutable(string $envKey, array $candidateNames, array $knownPaths = []): string
    {
        $configured = trim((string) simadaEnv($envKey, ''));
        if ($configured !== '') {
            $configured = trim($configured, " \t\n\r\0\x0B\"'");
            if (is_file($configured)) {
                return appNormalizePath($configured);
            }
        }

        foreach ($knownPaths as $path) {
            $path = trim((string) $path);
            if ($path !== '' && is_file($path)) {
                return appNormalizePath($path);
            }
        }

        if (!function_exists('shell_exec')) {
            return '';
        }

        foreach ($candidateNames as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }

            $cmd = appIsWindows()
                ? 'where ' . escapeshellarg($name) . ' 2>NUL'
                : 'command -v ' . escapeshellarg($name) . ' 2>/dev/null';

            $result = trim((string) @shell_exec($cmd));
            if ($result === '') {
                continue;
            }

            $first = preg_split('/\R/', $result)[0] ?? '';
            $first = trim($first, " \t\n\r\0\x0B\"'");
            if ($first !== '' && (is_file($first) || !appIsWindows())) {
                return appNormalizePath($first);
            }
        }

        return '';
    }
}

if (!function_exists('appPathToFileUri')) {
    function appPathToFileUri(string $path): string
    {
        $path = appNormalizePath($path);
        if ($path === '') {
            return '';
        }

        if (preg_match('/^[A-Za-z]:\//', $path) === 1) {
            return 'file:///' . str_replace(' ', '%20', $path);
        }

        return 'file://' . str_replace(' ', '%20', $path);
    }
}

if (!function_exists('appNormalizePath')) {
    function appNormalizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = preg_replace('#/+#', '/', $path) ?? $path;
        return rtrim($path, '/');
    }
}

if (!function_exists('appStorageBasePath')) {
    function appStorageBasePath(): string
    {
        global $simadaStorageConfig;

        $configuredPath = trim((string) ($simadaStorageConfig['base_path'] ?? ''));

        if ($configuredPath === '') {
            $configuredPath = appProjectRoot() . '/storage_simada';
        }

        return appNormalizePath($configuredPath);
    }
}

if (!function_exists('appStorageSubdir')) {
    function appStorageSubdir(string $key, string $fallback): string
    {
        global $simadaStorageConfig;

        $dir = trim((string) ($simadaStorageConfig[$key] ?? $fallback));
        $dir = str_replace(['\\', '\\'], '/', $dir);
        $dir = preg_replace('#/+#', '/', $dir) ?? $dir;
        $dir = trim($dir, '/');

        return $dir !== '' ? $dir : $fallback;
    }
}

if (!function_exists('appDocumentsBasePath')) {
    function appDocumentsBasePath(): string
    {
        return appNormalizePath(appStorageBasePath() . '/' . appStorageSubdir('documents_dir', 'Documents'));
    }
}

if (!function_exists('appProfilePhotosBasePath')) {
    function appProfilePhotosBasePath(): string
    {
        return appNormalizePath(appStorageBasePath() . '/' . appStorageSubdir('profile_photos_dir', 'Profile_Photos'));
    }
}

if (!function_exists('appTempStorageBasePath')) {
    function appTempStorageBasePath(): string
    {
        return appNormalizePath(appStorageBasePath() . '/' . appStorageSubdir('temp_dir', 'Temp'));
    }
}

if (!function_exists('appIsAbsolutePath')) {
    function appIsAbsolutePath(string $path): bool
    {
        $path = str_replace('\\', '/', trim($path));
        return $path !== '' && ($path[0] === '/' || preg_match('/^[A-Za-z]:\//', $path) === 1);
    }
}

if (!function_exists('appSanitizeStoredRelativePath')) {
    function appSanitizeStoredRelativePath(string $relativePath): string
    {
        $relativePath = trim(str_replace('\\', '/', $relativePath));
        $relativePath = str_replace([chr(0), '../', '..\\'], '', $relativePath);
        $relativePath = preg_replace('#/+#', '/', $relativePath) ?? $relativePath;
        return ltrim($relativePath, '/');
    }
}


if (!function_exists('appResolveDocumentAbsolutePath')) {
    function appResolveDocumentAbsolutePath(string $storedPath, bool $forWrite = false): ?string
    {
        $storedPath = appSanitizeStoredRelativePath($storedPath);

        if ($storedPath === '') {
            return null;
        }

        if (appIsAbsolutePath($storedPath)) {
            return $storedPath;
        }

        $normalized = str_replace('\\', '/', $storedPath);
        $baseStorage = appDocumentsBasePath();
        $projectRoot = appProjectRoot();
        $legacyStorage = appNormalizePath($projectRoot . '/storage');
        $withoutStoragePrefix = preg_replace('#^storage/#i', '', $normalized);

        $candidates = [];

        if (preg_match('#^storage/#i', $normalized)) {
            $candidates[] = appNormalizePath($projectRoot . '/' . $normalized);
            $candidates[] = appNormalizePath($baseStorage . '/' . $withoutStoragePrefix);
        } else {
            $candidates[] = appNormalizePath($baseStorage . '/' . $normalized);
            $candidates[] = appNormalizePath($legacyStorage . '/' . $normalized);
            $candidates[] = appNormalizePath($projectRoot . '/' . $normalized);
        }

        if ($forWrite) {
            return $candidates[0] ?? null;
        }

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $candidates[0] ?? null;
    }
}

if (!function_exists('appEnsureDirectoryExists')) {
    function appEnsureDirectoryExists(string $directory): bool
    {
        return is_dir($directory) || mkdir($directory, 0755, true);
    }
}

if (!function_exists('appDirectoryIsEmpty')) {
    function appDirectoryIsEmpty(string $directory): bool
    {
        if (!is_dir($directory)) {
            return false;
        }

        $items = scandir($directory);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item !== '.' && $item !== '..') {
                return false;
            }
        }

        return true;
    }
}

if (!function_exists('appRemoveEmptyParentDirectories')) {
    function appRemoveEmptyParentDirectories(string $startDirectory): void
    {
        $startDirectory = appNormalizePath($startDirectory);
        $stopRoots = array_filter(array_unique([
            appNormalizePath(appDocumentsBasePath()),
            appNormalizePath(appStorageBasePath()),
            appNormalizePath(appProjectRoot() . '/storage'),
        ]));

        $current = $startDirectory;

        while ($current !== '' && is_dir($current)) {
            $parent = appNormalizePath(dirname($current));

            if (in_array($current, $stopRoots, true) || in_array($parent, $stopRoots, true)) {
                break;
            }

            if (!appDirectoryIsEmpty($current)) {
                break;
            }

            @rmdir($current);
            $current = $parent;
        }
    }
}

if (!function_exists('appDisplayDocumentType')) {
    function appDisplayDocumentType(array $doc): string
    {
        $jenis = trim((string) ($doc['jenis_dokumen'] ?? ''));
        $jenisLainnya = trim((string) ($doc['jenis_dokumen_lainnya'] ?? ''));

        if (strcasecmp($jenis, 'Lainnya') === 0 && $jenisLainnya !== '') {
            return $jenisLainnya;
        }

        return $jenis !== '' ? $jenis : '-';
    }
}

if (!function_exists('appStorageMonthSegment')) {
    function appStorageMonthSegment(int $timestamp): string
    {
        $months = [
            1 => 'januari',
            2 => 'februari',
            3 => 'maret',
            4 => 'april',
            5 => 'mei',
            6 => 'juni',
            7 => 'juli',
            8 => 'agustus',
            9 => 'september',
            10 => 'oktober',
            11 => 'november',
            12 => 'desember',
        ];

        $monthNumber = (int) date('n', $timestamp);

        return date('m', $timestamp) . '_' . ($months[$monthNumber] ?? strtolower(date('F', $timestamp)));
    }
}


if (!function_exists('appStorageUserFolder')) {
    function appStorageUserFolder(string $userName, int $userId): string
    {
        $userName = trim($userName);

        if ($userName !== '') {
            $userName = preg_split('/,/', $userName, 2)[0] ?? $userName;
            $userName = trim($userName);
            return appSlugifyStorageSegment($userName, 'user_' . max(0, $userId));
        }

        return 'user_' . max(0, $userId);
    }
}

if (!function_exists('appMakeUniqueStorageFilename')) {
    function appMakeUniqueStorageFilename(string $directory, string $fileName): string
    {
        $fileName = basename(str_replace('\\', '/', $fileName));
        $fileName = trim($fileName, " ._-");

        if ($fileName === '') {
            $fileName = 'document';
        }

        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $baseName = trim($baseName, " ._-");

        if ($baseName === '') {
            $baseName = 'document';
        }

        $candidate = $extension !== '' ? $baseName . '.' . $extension : $baseName;
        $counter = 2;

        while (is_file(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $candidate)) {
            $candidate = $extension !== ''
                ? $baseName . '_' . $counter . '.' . $extension
                : $baseName . '_' . $counter;
            $counter++;
        }

        return $candidate;
    }
}

if (!function_exists('appBuildStorageRelativePath')) {
    function appBuildStorageRelativePath(
        string $status,
        string $kategoriDokumen,
        string $jenisDokumen,
        int $userId,
        string $storedFileName,
        ?string $tanggalDokumen = null,
        string $userName = '',
        string $jenisDokumenLainnya = ''
    ): string {
        $timestamp = $tanggalDokumen ? strtotime($tanggalDokumen) : false;

        if (!$timestamp) {
            $timestamp = time();
        }

        $statusFolder = appStorageStatusFolder($status);
        $categoryFolder = appSlugifyStorageSegment($kategoriDokumen, 'kategori_lainnya');
        $typeFolder = appSlugifyStorageSegment($jenisDokumen, 'jenis_lainnya');
        $otherTypeFolder = appSlugifyStorageSegment($jenisDokumenLainnya, 'jenis_lainnya');
        $dateFolder = date('Y-m-d', $timestamp);
        $userFolder = appStorageUserFolder($userName, $userId);
        $safeFileName = basename(str_replace('\\', '/', $storedFileName));

        $segments = [
            $statusFolder,
            $categoryFolder,
            $typeFolder,
        ];

        // Jika jenis dokumen adalah "Lainnya", folder manual user dibuat di dalam folder "lainnya".
        // Contoh: Pending/kategori/lainnya/motor/2026-05-08/nama_user/file.pdf
        if (strcasecmp(trim($jenisDokumen), 'Lainnya') === 0 && trim($jenisDokumenLainnya) !== '') {
            $segments[] = $otherTypeFolder;
        }

        $segments[] = $dateFolder;
        $segments[] = $userFolder;
        $segments[] = $safeFileName;

        return implode('/', $segments);
    }
}



if (!function_exists('appBindParams')) {
    function appBindParams(mysqli_stmt $stmt, string $types, array &$params): void
    {
        if ($types === '' || empty($params)) {
            return;
        }

        $refs = [];
        $refs[] = &$types;

        foreach ($params as $key => &$value) {
            $refs[] = &$value;
        }

        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
}

if (!function_exists('appTableHasRequiredColumns')) {
    function appTableHasRequiredColumns(mysqli $db, string $table, array $requiredColumns): bool
    {
        if (!$requiredColumns) {
            return true;
        }

        $placeholders = implode(',', array_fill(0, count($requiredColumns), '?'));
        $sql = "SELECT COLUMN_NAME
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = ?
                  AND COLUMN_NAME IN ($placeholders)";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $types = 's' . str_repeat('s', count($requiredColumns));
        $params = array_merge([$table], $requiredColumns);
        appBindParams($stmt, $types, $params);

        $stmt->execute();
        $result = $stmt->get_result();
        $found = [];

        while ($result && ($row = $result->fetch_assoc())) {
            $found[] = (string) ($row['COLUMN_NAME'] ?? '');
        }

        $stmt->close();

        return count(array_intersect($requiredColumns, $found)) === count($requiredColumns);
    }
}

if (!function_exists('appEnsureDocumentRevisionColumns')) {
    function appEnsureDocumentRevisionColumns(mysqli $db): void
    {
        appEnsureSimadaCoreSchema($db);

        if (!appTableHasColumn($db, 'documents', 'jenis_dokumen_lainnya')) {
            @$db->query("ALTER TABLE documents ADD COLUMN jenis_dokumen_lainnya VARCHAR(255) NULL AFTER jenis_dokumen");
        }

        if (!appTableHasColumn($db, 'documents', 'catatan_admin')) {
            @$db->query("ALTER TABLE documents ADD COLUMN catatan_admin TEXT NULL AFTER status");
        }

        if (!appTableHasColumn($db, 'documents', 'revision_count')) {
            @$db->query("ALTER TABLE documents ADD COLUMN revision_count INT NOT NULL DEFAULT 0 AFTER catatan_admin");
        }

        if (!appTableHasColumn($db, 'documents', 'last_revision_at')) {
            @$db->query("ALTER TABLE documents ADD COLUMN last_revision_at DATETIME NULL AFTER revision_count");
        }
    }
}

if (!function_exists('appNormalizeDocumentState')) {
    function appNormalizeDocumentState(string $status): string
    {
        return match (appNormalizeDocumentStatus($status)) {
            'approved' => 'Approved',
            'revision' => 'Revision',
            'rejected' => 'Rejected',
            default => 'Pending',
        };
    }
}

if (!function_exists('appCanChangeDocumentState')) {
    function appCanChangeDocumentState(string $currentStatus, string $newStatus): bool
    {
        $currentStatus = appNormalizeDocumentState($currentStatus);
        $newStatus = appNormalizeDocumentState($newStatus);

        if ($currentStatus === $newStatus) {
            return false;
        }

        $transitions = [
            'Pending' => ['Approved', 'Revision', 'Rejected'],
            'Revision' => ['Pending', 'Approved', 'Rejected'],
            'Approved' => ['Pending', 'Revision', 'Rejected'],
            'Rejected' => ['Pending', 'Revision', 'Approved'],
        ];

        return in_array($newStatus, $transitions[$currentStatus] ?? [], true);
    }
}

if (!function_exists('appMoveDocumentByState')) {
    function appMoveDocumentByState(array $doc, string $newStatus): array
    {
        $newStatus = appNormalizeDocumentState($newStatus);
        $currentPath = trim((string) ($doc['file_path'] ?? ''));

        if ($currentPath === '') {
            return ['ok' => false, 'path' => $currentPath, 'message' => 'empty_path'];
        }

        $source = (string) (appResolveDocumentAbsolutePath($currentPath, false) ?? '');
        if ($source === '' || !is_file($source)) {
            return ['ok' => false, 'path' => $currentPath, 'message' => 'source_not_found'];
        }

        $userId = (int) ($doc['user_id'] ?? 0);
        $storedFileName = trim((string) ($doc['stored_file_name'] ?? ''));
        if ($storedFileName === '') {
            $storedFileName = trim((string) ($doc['file_name'] ?? ''));
        }
        if ($storedFileName === '') {
            $storedFileName = basename($source);
        }

        $tanggalDokumen = trim((string) ($doc['tanggal_dokumen'] ?? ''));
        if ($tanggalDokumen === '') {
            $tanggalDokumen = trim((string) ($doc['created_at'] ?? ''));
        }

        $newRelativePath = appBuildStorageRelativePath(
            $newStatus,
            (string) ($doc['kategori_dokumen'] ?? ''),
            (string) ($doc['jenis_dokumen'] ?? ''),
            $userId,
            $storedFileName,
            $tanggalDokumen,
            (string) ($doc['user_name'] ?? ''),
            (string) ($doc['jenis_dokumen_lainnya'] ?? '')
        );

        $target = (string) (appResolveDocumentAbsolutePath($newRelativePath, true) ?? '');
        if ($target === '') {
            return ['ok' => false, 'path' => $currentPath, 'message' => 'target_invalid'];
        }

        $targetDir = dirname($target);
        if (!appEnsureDirectoryExists($targetDir)) {
            return ['ok' => false, 'path' => $currentPath, 'message' => 'target_dir_failed'];
        }

        if (is_file($target) && realpath($target) !== realpath($source)) {
            $storedFileName = appMakeUniqueStorageFilename($targetDir, $storedFileName);
            $newRelativePath = appBuildStorageRelativePath(
                $newStatus,
                (string) ($doc['kategori_dokumen'] ?? ''),
                (string) ($doc['jenis_dokumen'] ?? ''),
                $userId,
                $storedFileName,
                $tanggalDokumen,
                (string) ($doc['user_name'] ?? ''),
                (string) ($doc['jenis_dokumen_lainnya'] ?? '')
            );
            $target = (string) (appResolveDocumentAbsolutePath($newRelativePath, true) ?? '');
        }

        if (realpath($source) === realpath($target)) {
            return ['ok' => true, 'path' => $newRelativePath, 'message' => 'same_path'];
        }

        $moved = @rename($source, $target);
        if (!$moved) {
            $moved = @copy($source, $target);
            if ($moved) {
                @unlink($source);
            }
        }

        if (!$moved) {
            return ['ok' => false, 'path' => $currentPath, 'message' => 'move_failed'];
        }

        appRemoveEmptyParentDirectories(dirname($source));

        return ['ok' => true, 'path' => $newRelativePath, 'message' => 'moved'];
    }
}

if (!function_exists('appPustakaStatusInfo')) {
    function appPustakaStatusInfo(string $status): array
    {
        return match (appNormalizeDocumentStatus($status)) {
            'approved' => [
                'label' => 'Disetujui',
                'class' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
                'icon' => 'fa-circle-check',
            ],
            'rejected' => [
                'label' => 'Ditolak',
                'class' => 'bg-red-50 text-red-700 border border-red-200',
                'icon' => 'fa-circle-xmark',
            ],
            'revision' => [
                'label' => 'Perlu Revisi',
                'class' => 'bg-blue-50 text-blue-700 border border-blue-200',
                'icon' => 'fa-pen-to-square',
            ],
            default => [
                'label' => 'Pending',
                'class' => 'bg-amber-50 text-amber-700 border border-amber-200',
                'icon' => 'fa-clock',
                'pulse' => true,
            ],
        };
    }
}

if (!function_exists('appFlattenDocumentTypeMap')) {
    function appFlattenDocumentTypeMap(array $kategoriJenisMap): array
    {
        $result = [];
        foreach ($kategoriJenisMap as $jenisList) {
            foreach ((array) $jenisList as $jenis) {
                $jenis = trim((string) $jenis);
                if ($jenis !== '') {
                    $result[$jenis] = $jenis;
                }
            }
        }

        ksort($result, SORT_NATURAL | SORT_FLAG_CASE);
        return array_values($result);
    }
}

if (!function_exists('appDescribeUploadError')) {
    function appDescribeUploadError(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas upload server/PHP.',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian. Silakan coba lagi.',
            UPLOAD_ERR_NO_FILE => 'File dokumen wajib dipilih.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary upload di server tidak tersedia.',
            UPLOAD_ERR_CANT_WRITE => 'Server gagal menulis file upload ke disk.',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP/server.',
            default => 'Terjadi kesalahan saat upload file.',
        };
    }
}

if (!function_exists('appDetectUploadedMimeType')) {
    function appDetectUploadedMimeType(array $file): string
    {
        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_file($tmpName)) {
            return 'application/octet-stream';
        }

        if (class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = (string) $finfo->file($tmpName);
            if ($mimeType !== '') {
                return $mimeType;
            }
        }

        return 'application/octet-stream';
    }
}

if (!function_exists('appIsAllowedDocumentUpload')) {
    function appIsAllowedDocumentUpload(string $extension, string $mimeType): bool
    {
        $extension = strtolower(trim($extension));
        $mimeType = strtolower(trim($mimeType));

        $allowedMimeTypes = [
            'pdf' => [
                'application/pdf',
                'application/x-pdf',
                'application/acrobat',
                'applications/vnd.pdf',
                'text/pdf',
            ],
            'doc' => [
                'application/msword',
                'application/vnd.ms-office',
                'application/cdfv2',
                'application/x-ole-storage',
            ],
            'docx' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
            ],
            'xls' => [
                'application/vnd.ms-excel',
                'application/vnd.ms-office',
                'application/cdfv2',
                'application/x-ole-storage',
            ],
            'xlsx' => [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
            ],
        ];

        if (!isset($allowedMimeTypes[$extension])) {
            return false;
        }

        return in_array($mimeType, $allowedMimeTypes[$extension], true)
            || $mimeType === ''
            || $mimeType === 'application/octet-stream';
    }
}

if (!function_exists('appSanitizeOriginalFilename')) {
    function appSanitizeOriginalFilename(string $filename): string
    {
        $filename = basename($filename);
        $filename = preg_replace('/[^a-zA-Z0-9 ._-]/', ' ', $filename) ?? '';
        $filename = preg_replace('/\s+/', ' ', $filename) ?? $filename;
        $filename = trim((string) $filename, ' ._-');

        return $filename !== '' ? $filename : 'document';
    }
}

if (!function_exists('appDeleteStoredFile')) {
    function appDeleteStoredFile(?string $relativePath, ?string $exceptAbsolutePath = null): void
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '') {
            return;
        }

        $absolutePath = (string) (appResolveDocumentAbsolutePath($relativePath, false) ?? '');
        if ($absolutePath === '' || !is_file($absolutePath)) {
            return;
        }

        if ($exceptAbsolutePath !== null && realpath($absolutePath) === realpath($exceptAbsolutePath)) {
            return;
        }

        @unlink($absolutePath);
        appRemoveEmptyParentDirectories(dirname($absolutePath));
    }
}


if (!defined('SIMADA_MAX_REVISIONS')) {
    define('SIMADA_MAX_REVISIONS', 3);
}

if (!function_exists('appMaxDocumentRevisions')) {
    function appMaxDocumentRevisions(): int
    {
        return (int) SIMADA_MAX_REVISIONS;
    }
}

if (!function_exists('appDefaultDocumentMasterData')) {
    function appDefaultDocumentMasterData(): array
    {
        return [
            'DOKUMEN TEKNIS PRODUKSI & BUDIDAYA' => [
                'Laporan Produksi/Pemijahan Ikan & Udang',
                'Laporan Pemeliharaan Larva & Benih',
                'Laporan Pendederan Benih',
                'Laporan Pembesaran Ikan & Udang',
                'Laporan Panen Benih dan Ikan Konsumsi',
                'Laporan Pengelolaan Kualitas Air',
                'Laporan Pengapuran dan Pemupukan Kolam',
                'Laporan Pemberian Pakan, Vitamin, dan Obat',
                'Lainnya',
            ],
            'DOKUMEN ADMINISTRASI & KESEKRETARIATAN' => [
                'Laporan Arsip Surat Masuk dan Keluar',
                'Laporan Pengetikan dan Penomoran Surat',
                'Surat Perintah Tugas (SPT)',
                'Surat Pengantar Resmi',
                'Nota Dinas dan Memorandum',
                'Lainnya',
            ],
            'DOKUMEN KEUANGAN & ANGGARAN' => [
                'Laporan Realisasi Anggaran (SIRA)',
                'Laporan Rekapitulasi Belanja',
                'Laporan Penjualan Hasil Produksi',
                'Rekapitulasi Pengajuan SPM',
                'Laporan Barang Pakai Habis (BPH)',
                'Lainnya',
            ],
            'DOKUMEN PENGADAAN BARANG/JASA' => [
                'Dokumen Tender dan Pengadaan',
                'Surat Pesanan Pembelian',
                'Surat Perubahan RUP',
                'Berita Acara Pemeriksaan Barang',
                'Kontrak dan Perjanjian Kerja',
                'Lainnya',
            ],
            'DOKUMEN MANAJEMEN KINERJA' => [
                'Laporan E-Kinerja Pegawai',
                'Laporan E-SAKIP Triwulanan',
                'Laporan Survei Kepuasan Masyarakat',
                'Laporan Evaluasi Kinerja Instansi',
                'Lainnya',
            ],
            'DOKUMEN KEPEGAWAIAN & SDM' => [
                'Daftar Induk dan Database Pegawai',
                'Laporan Rekapitulasi Absensi',
                'Laporan Perjalanan Dinas (PERDIN)',
                'Berkas Kepegawaian dan Penilaian Kinerja',
                'Lainnya',
            ],
            'DOKUMEN ASET & LOGISTIK' => [
                'Laporan Inventarisasi Barang Milik Daerah (BMD)',
                'Laporan Pemeliharaan Sarana dan Prasarana',
                'Laporan Penggunaan Barang Inventaris',
                'Berita Acara Serah Terima Barang',
                'Lainnya',
            ],
            'DOKUMEN PERENCANAAN & EVALUASI' => [
                'Laporan Rencana Strategis (RENSTRA)',
                'Laporan Rencana Kerja Tahunan',
                'Laporan Monitoring dan Evaluasi Program',
                'Laporan Capaian Kinerja Triwulanan/Tahunan',
                'Lainnya',
            ],
            'DOKUMEN PEMASARAN & DISTRIBUSI' => [
                'Laporan Promosi dan Pemasaran',
                'Laporan Penjualan dan Distribusi Benih',
                'Dokumentasi Kegiatan Pameran/Promosi',
                'Lainnya',
            ],
            'DOKUMEN TEKNIS PERAWATAN FASILITAS' => [
                'Laporan Pemeliharaan Listrik dan Genset',
                'Laporan Perawatan Alat dan Mesin Budidaya',
                'Laporan Kebersihan dan Perawatan Gedung',
                'Lainnya',
            ],
        ];
    }
}

if (!function_exists('appEnsureDocumentMasterTables')) {
    function appEnsureDocumentMasterTables(mysqli $db): void
    {
        $db->query("CREATE TABLE IF NOT EXISTS document_categories (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            nama_kategori VARCHAR(150) NOT NULL,
            slug_kategori VARCHAR(180) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_document_categories_slug (slug_kategori)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->query("CREATE TABLE IF NOT EXISTS document_types (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT,
            category_id INT UNSIGNED NOT NULL,
            nama_jenis VARCHAR(180) NOT NULL,
            slug_jenis VARCHAR(220) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_document_types_category_slug (category_id, slug_jenis),
            KEY idx_document_types_category (category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $categoryCountResult = $db->query('SELECT COUNT(*) AS total FROM document_categories');
        $categoryCountRow = $categoryCountResult ? $categoryCountResult->fetch_assoc() : null;
        $typeCountResult = $db->query('SELECT COUNT(*) AS total FROM document_types');
        $typeCountRow = $typeCountResult ? $typeCountResult->fetch_assoc() : null;

        // Tetap lanjutkan proses upsert master.
        // Tujuannya agar jenis dokumen default baru, termasuk "Lainnya",
        // tetap masuk walaupun tabel master sudah pernah terisi.

        $categoryStmt = $db->prepare("INSERT INTO document_categories (nama_kategori, slug_kategori, is_active, sort_order)
            VALUES (?, ?, 1, ?)
            ON DUPLICATE KEY UPDATE
                nama_kategori = VALUES(nama_kategori),
                is_active = VALUES(is_active),
                sort_order = VALUES(sort_order)");
        $categoryLookup = $db->prepare('SELECT id FROM document_categories WHERE slug_kategori = ? LIMIT 1');
        $typeStmt = $db->prepare("INSERT INTO document_types (category_id, nama_jenis, slug_jenis, is_active, sort_order)
            VALUES (?, ?, ?, 1, ?)
            ON DUPLICATE KEY UPDATE
                nama_jenis = VALUES(nama_jenis),
                is_active = VALUES(is_active),
                sort_order = VALUES(sort_order)");

        if (!$categoryStmt || !$categoryLookup || !$typeStmt) {
            return;
        }

        $categoryOrder = 10;
        foreach (appDefaultDocumentMasterData() as $categoryName => $types) {
            $categorySlug = appSlugifyStorageSegment($categoryName, 'kategori');
            $categoryStmt->bind_param('ssi', $categoryName, $categorySlug, $categoryOrder);
            $categoryStmt->execute();

            $categoryLookup->bind_param('s', $categorySlug);
            $categoryLookup->execute();
            $categoryResult = $categoryLookup->get_result();
            $categoryRow = $categoryResult ? $categoryResult->fetch_assoc() : null;
            $categoryId = (int) ($categoryRow['id'] ?? 0);

            if ($categoryId > 0) {
                $typeOrder = 10;
                foreach ($types as $typeName) {
                    $typeSlug = appSlugifyStorageSegment($typeName, 'jenis');
                    $typeStmt->bind_param('issi', $categoryId, $typeName, $typeSlug, $typeOrder);
                    $typeStmt->execute();
                    $typeOrder += 10;
                }
            }

            $categoryOrder += 10;
        }

        $categoryStmt->close();
        $categoryLookup->close();
        $typeStmt->close();
    }
}

if (!function_exists('appLoadDocumentCategoryTypeMap')) {
    function appLoadDocumentCategoryTypeMap(mysqli $db): array
    {
        appEnsureDocumentMasterTables($db);

        $sql = "SELECT c.nama_kategori, t.nama_jenis
                FROM document_categories c
                INNER JOIN document_types t ON t.category_id = c.id
                WHERE c.is_active = 1
                  AND t.is_active = 1
                ORDER BY c.sort_order ASC, c.nama_kategori ASC, t.sort_order ASC, t.nama_jenis ASC";

        $result = $db->query($sql);
        $map = [];

        if (!$result) {
            return $map;
        }

        while ($row = $result->fetch_assoc()) {
            $category = (string) ($row['nama_kategori'] ?? '');
            $type = (string) ($row['nama_jenis'] ?? '');

            if ($category === '' || $type === '') {
                continue;
            }

            if (!isset($map[$category])) {
                $map[$category] = [];
            }

            $map[$category][] = $type;
        }

        // Pastikan setiap kategori selalu punya pilihan "Lainnya".
        // Ini menjaga struktur folder Pending, Document, dan Rejected tetap seragam.
        foreach ($map as $category => $types) {
            $hasLainnya = false;

            foreach ($types as $type) {
                if (strcasecmp((string) $type, 'Lainnya') === 0) {
                    $hasLainnya = true;
                    break;
                }
            }

            if (!$hasLainnya) {
                $map[$category][] = 'Lainnya';
            }
        }

        return $map;
    }
}

if (!function_exists('appIsValidDocumentCategoryType')) {
    function appIsValidDocumentCategoryType(mysqli $db, string $categoryName, string $typeName): bool
    {
        appEnsureDocumentMasterTables($db);

        // "Lainnya" diperlakukan sebagai jenis bawaan untuk setiap kategori aktif.
        // Detail manualnya disimpan di kolom jenis_dokumen_lainnya.
        if (strcasecmp(trim($typeName), 'Lainnya') === 0) {
            $categorySql = "SELECT id
                            FROM document_categories
                            WHERE nama_kategori = ?
                              AND is_active = 1
                            LIMIT 1";

            $categoryStmt = $db->prepare($categorySql);

            if (!$categoryStmt) {
                return false;
            }

            $categoryStmt->bind_param('s', $categoryName);
            $categoryStmt->execute();
            $categoryResult = $categoryStmt->get_result();
            $isCategoryValid = $categoryResult && $categoryResult->num_rows > 0;
            $categoryStmt->close();

            return $isCategoryValid;
        }

        $sql = "SELECT t.id
                FROM document_categories c
                INNER JOIN document_types t ON t.category_id = c.id
                WHERE c.nama_kategori = ?
                  AND t.nama_jenis = ?
                  AND c.is_active = 1
                  AND t.is_active = 1
                LIMIT 1";

        $stmt = $db->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('ss', $categoryName, $typeName);
        $stmt->execute();
        $result = $stmt->get_result();
        $isValid = $result && $result->num_rows > 0;
        $stmt->close();

        return $isValid;
    }
}


/* =========================================================
   HELPER FOTO PROFIL
   Foto profil disimpan di storage SIMADA terpisah, bukan di storage SIMDO.
========================================================= */
if (!function_exists('appUserInitials')) {
    function appUserInitials(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'U';
        }

        $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $firstPart = (string) ($parts[0] ?? 'U');
        $lastPart = count($parts) > 1 ? (string) end($parts) : '';

        $first = function_exists('mb_substr') ? mb_substr($firstPart, 0, 1) : substr($firstPart, 0, 1);
        $second = $lastPart !== '' ? (function_exists('mb_substr') ? mb_substr($lastPart, 0, 1) : substr($lastPart, 0, 1)) : '';
        $initials = $first . $second;

        return function_exists('mb_strtoupper') ? mb_strtoupper($initials) : strtoupper($initials);
    }
}

if (!function_exists('appProfilePhotoRelativePath')) {
    function appProfilePhotoRelativePath(int $userId, string $extension): string
    {
        $extension = strtolower(trim($extension));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $extension = 'jpg';
        }

        return 'user_' . $userId . '/profile_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    }
}

if (!function_exists('appResolveProfilePhotoAbsolutePath')) {
    function appResolveProfilePhotoAbsolutePath(string $relativePath, bool $forWrite = false): ?string
    {
        $relativePath = appSanitizeStoredRelativePath($relativePath);
        if ($relativePath === '') {
            return null;
        }

        $base = appProfilePhotosBasePath();
        $absolute = appNormalizePath($base . '/' . $relativePath);

        if (strpos($absolute . '/', appNormalizePath($base) . '/') !== 0) {
            return null;
        }

        if ($forWrite) {
            return $absolute;
        }

        return is_file($absolute) ? $absolute : null;
    }
}

if (!function_exists('appDeleteProfilePhoto')) {
    function appDeleteProfilePhoto(?string $relativePath): void
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '') {
            return;
        }

        $absolute = appResolveProfilePhotoAbsolutePath($relativePath, false);
        if ($absolute && is_file($absolute)) {
            @unlink($absolute);
            appRemoveEmptyParentDirectories(dirname($absolute));
        }
    }
}

if (!function_exists('appProfilePhotoUrl')) {
    function appProfilePhotoUrl(int $userId, ?string $profilePhoto = null): string
    {
        $version = $profilePhoto ? substr(md5($profilePhoto), 0, 10) : (string) time();
        return '../profile_photo.php?id=' . $userId . '&v=' . $version;
    }
}

if (!function_exists('appSaveUploadedProfilePhoto')) {
    function appSaveUploadedProfilePhoto(array $file, int $userId, ?string $oldPhoto = null): array
    {
        if ($userId <= 0) {
            return ['ok' => false, 'path' => '', 'message' => 'User tidak valid.'];
        }

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['ok' => true, 'path' => (string) $oldPhoto, 'message' => 'Tidak ada foto baru.'];
        }

        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'path' => '', 'message' => 'Upload foto gagal.'];
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);

        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            return ['ok' => false, 'path' => '', 'message' => 'File foto tidak valid.'];
        }

        if ($size <= 0 || $size > 2 * 1024 * 1024) {
            return ['ok' => false, 'path' => '', 'message' => 'Ukuran foto maksimal 2 MB.'];
        }

        $imageInfo = @getimagesize($tmpName);
        if (!$imageInfo || empty($imageInfo['mime'])) {
            return ['ok' => false, 'path' => '', 'message' => 'File harus berupa gambar JPG, PNG, atau WEBP.'];
        }

        $mime = strtolower((string) $imageInfo['mime']);
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!isset($map[$mime])) {
            return ['ok' => false, 'path' => '', 'message' => 'Format foto hanya JPG, PNG, atau WEBP.'];
        }

        $relativePath = appProfilePhotoRelativePath($userId, $map[$mime]);
        $absolutePath = appResolveProfilePhotoAbsolutePath($relativePath, true);

        if (!$absolutePath) {
            return ['ok' => false, 'path' => '', 'message' => 'Path foto tidak valid.'];
        }

        if (!appEnsureDirectoryExists(dirname($absolutePath))) {
            return ['ok' => false, 'path' => '', 'message' => 'Folder foto profil gagal dibuat.'];
        }

        if (!move_uploaded_file($tmpName, $absolutePath)) {
            return ['ok' => false, 'path' => '', 'message' => 'Foto profil gagal disimpan.'];
        }

        appDeleteProfilePhoto($oldPhoto);

        return ['ok' => true, 'path' => $relativePath, 'message' => 'Foto profil berhasil diperbarui.'];
    }
}

/* =========================================================
   FITUR LANJUTAN SIMADA
   Profil, notifikasi, OCR opsional, dan pengelolaan dokumen.
========================================================= */
if (!function_exists('appSafeDbQuery')) {
    function appSafeDbQuery(mysqli $db, string $sql): void
    {
        @$db->query($sql);
    }
}

if (!function_exists('appEnsureSimadaCoreSchema')) {
    function appEnsureSimadaCoreSchema(mysqli $db): void
    {
        $documentColumns = [
            'file_hash' => "ALTER TABLE documents ADD COLUMN file_hash CHAR(64) NULL AFTER file_size",
            'ocr_text' => "ALTER TABLE documents ADD COLUMN ocr_text MEDIUMTEXT NULL AFTER file_hash",
            'ocr_status' => "ALTER TABLE documents ADD COLUMN ocr_status VARCHAR(30) NOT NULL DEFAULT 'not_processed' AFTER ocr_text",
            'ocr_engine' => "ALTER TABLE documents ADD COLUMN ocr_engine VARCHAR(80) NULL AFTER ocr_status",
            'ocr_processed_at' => "ALTER TABLE documents ADD COLUMN ocr_processed_at DATETIME NULL AFTER ocr_engine",
        ];

        foreach ($documentColumns as $column => $sql) {
            if (!appTableHasColumn($db, 'documents', $column)) {
                appSafeDbQuery($db, $sql);
            }
        }

        $userColumns = [
            'phone' => "ALTER TABLE users ADD COLUMN phone VARCHAR(30) NULL AFTER jabatan",
            'profile_photo' => "ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL AFTER phone",
            'updated_at' => "ALTER TABLE users ADD COLUMN updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
        ];

        foreach ($userColumns as $column => $sql) {
            if (!appTableHasColumn($db, 'users', $column)) {
                appSafeDbQuery($db, $sql);
            }
        }

        appSafeDbQuery($db, "CREATE TABLE IF NOT EXISTS notifications (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            target_role VARCHAR(20) NULL,
            title VARCHAR(160) NOT NULL,
            message TEXT NOT NULL,
            link_url VARCHAR(255) NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_notifications_user_read (user_id, is_read),
            KEY idx_notifications_role_read (target_role, is_read),
            KEY idx_notifications_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        appSafeDbQuery($db, "CREATE TABLE IF NOT EXISTS notification_user_states (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            notification_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            is_deleted TINYINT(1) NOT NULL DEFAULT 0,
            read_at DATETIME NULL,
            deleted_at DATETIME NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_notification_user (notification_id, user_id),
            KEY idx_notification_user_flags (user_id, is_read, is_deleted),
            KEY idx_notification_state_notification (notification_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        appSafeDbQuery($db, "CREATE TABLE IF NOT EXISTS attendance_records (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            employee_name VARCHAR(150) NOT NULL,
            periode_bulan CHAR(7) NOT NULL,
            hadir INT NOT NULL DEFAULT 0,
            izin INT NOT NULL DEFAULT 0,
            sakit INT NOT NULL DEFAULT 0,
            alpha INT NOT NULL DEFAULT 0,
            terlambat INT NOT NULL DEFAULT 0,
            cluster_label VARCHAR(60) NULL,
            cluster_score DECIMAL(8,2) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_attendance_period (periode_bulan),
            KEY idx_attendance_user (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }
}


if (!function_exists('appCreateNotification')) {
    function appCreateNotification(mysqli $db, ?int $userId, ?string $targetRole, string $title, string $message, string $linkUrl = ''): void
    {
        appEnsureSimadaCoreSchema($db);
        $stmt = $db->prepare('INSERT INTO notifications (user_id, target_role, title, message, link_url, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
        if (!$stmt) {
            return;
        }
        $targetRole = $targetRole !== null && trim($targetRole) !== '' ? trim($targetRole) : null;
        $linkUrl = trim($linkUrl) !== '' ? trim($linkUrl) : null;
        $stmt->bind_param('issss', $userId, $targetRole, $title, $message, $linkUrl);
        @$stmt->execute();
        $stmt->close();
    }
}

if (!function_exists('appNotificationVisibleConditionSql')) {
    function appNotificationVisibleConditionSql(): string
    {
        return "(n.user_id = ? OR (n.user_id IS NULL AND n.target_role = ?))";
    }
}

if (!function_exists('appNotificationStateNotDeletedSql')) {
    function appNotificationStateNotDeletedSql(): string
    {
        return "COALESCE(ns.is_deleted, 0) = 0";
    }
}


if (!function_exists('appNotificationNotPopupNoiseSql')) {
    function appNotificationNotPopupNoiseSql(): string
    {
        return "NOT (LOWER(TRIM(COALESCE(n.title, ''))) IN ('notifikasi baru', 'ada notifikasi baru') AND LOWER(COALESCE(n.message, '')) LIKE '%buka halaman notifikasi%')";
    }
}

if (!function_exists('appClearLegacyPopupNotificationNoise')) {
    function appClearLegacyPopupNotificationNoise(mysqli $db, int $userId, string $role): void
    {
        if ($userId <= 0 || !in_array($role, ['admin', 'user'], true)) {
            return;
        }

        appEnsureSimadaCoreSchema($db);

        $sql = "SELECT n.id
                FROM notifications n
                LEFT JOIN notification_user_states ns
                    ON ns.notification_id = n.id
                   AND ns.user_id = ?
                WHERE " . appNotificationVisibleConditionSql() . "
                  AND " . appNotificationStateNotDeletedSql() . "
                  AND NOT " . appNotificationNotPopupNoiseSql() . "
                LIMIT 100";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return;
        }

        $stmt->bind_param('iis', $userId, $userId, $role);
        $stmt->execute();
        $result = $stmt->get_result();
        $ids = [];
        while ($row = $result ? $result->fetch_assoc() : null) {
            if (!$row) {
                break;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        $stmt->close();

        foreach ($ids as $notificationId) {
            if (function_exists('appSetNotificationState')) {
                appSetNotificationState($db, $notificationId, $userId, true, true);
            }
        }
    }
}

if (!function_exists('appUnreadNotificationCount')) {
    function appUnreadNotificationCount(mysqli $db, int $userId, string $role): int
    {
        appEnsureSimadaCoreSchema($db);

        $sql = "SELECT COUNT(*) AS total
                FROM notifications n
                LEFT JOIN notification_user_states ns
                    ON ns.notification_id = n.id
                   AND ns.user_id = ?
                WHERE " . appNotificationVisibleConditionSql() . "
                  AND " . appNotificationStateNotDeletedSql() . "
                  AND " . appNotificationNotPopupNoiseSql() . "
                  AND COALESCE(ns.is_read, n.is_read, 0) = 0";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return 0;
        }

        $stmt->bind_param('iis', $userId, $userId, $role);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        return (int) ($row['total'] ?? 0);
    }
}

if (!function_exists('appVisibleNotificationIds')) {
    function appVisibleNotificationIds(mysqli $db, int $userId, string $role, bool $onlyUnread = false): array
    {
        appEnsureSimadaCoreSchema($db);

        $sql = "SELECT n.id
                FROM notifications n
                LEFT JOIN notification_user_states ns
                    ON ns.notification_id = n.id
                   AND ns.user_id = ?
                WHERE " . appNotificationVisibleConditionSql() . "
                  AND " . appNotificationStateNotDeletedSql() . "
                  AND " . appNotificationNotPopupNoiseSql();

        if ($onlyUnread) {
            $sql .= " AND COALESCE(ns.is_read, n.is_read, 0) = 0";
        }

        $sql .= " ORDER BY n.created_at DESC";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('iis', $userId, $userId, $role);
        $stmt->execute();
        $result = $stmt->get_result();
        $ids = [];

        while ($row = $result ? $result->fetch_assoc() : null) {
            if (!$row) {
                break;
            }
            $ids[] = (int) ($row['id'] ?? 0);
        }

        $stmt->close();

        return array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
    }
}

if (!function_exists('appCanAccessNotification')) {
    function appCanAccessNotification(mysqli $db, int $notificationId, int $userId, string $role, bool $allowDeleted = false): bool
    {
        if ($notificationId <= 0 || $userId <= 0) {
            return false;
        }

        appEnsureSimadaCoreSchema($db);

        $sql = "SELECT n.id
                FROM notifications n
                LEFT JOIN notification_user_states ns
                    ON ns.notification_id = n.id
                   AND ns.user_id = ?
                WHERE n.id = ?
                  AND " . appNotificationVisibleConditionSql();

        if (!$allowDeleted) {
            $sql .= " AND " . appNotificationStateNotDeletedSql() . " AND " . appNotificationNotPopupNoiseSql();
        }

        $sql .= " LIMIT 1";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('iiis', $userId, $notificationId, $userId, $role);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return (bool) $row;
    }
}

if (!function_exists('appSetNotificationState')) {
    function appSetNotificationState(mysqli $db, int $notificationId, int $userId, bool $read, bool $deleted): bool
    {
        if ($notificationId <= 0 || $userId <= 0) {
            return false;
        }

        appEnsureSimadaCoreSchema($db);

        $readValue = $read ? 1 : 0;
        $deletedValue = $deleted ? 1 : 0;
        $readAt = $read ? date('Y-m-d H:i:s') : null;
        $deletedAt = $deleted ? date('Y-m-d H:i:s') : null;

        $stmt = $db->prepare("INSERT INTO notification_user_states
            (notification_id, user_id, is_read, is_deleted, read_at, deleted_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                is_read = VALUES(is_read),
                is_deleted = VALUES(is_deleted),
                read_at = COALESCE(VALUES(read_at), read_at),
                deleted_at = VALUES(deleted_at),
                updated_at = NOW()");

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param('iiiiss', $notificationId, $userId, $readValue, $deletedValue, $readAt, $deletedAt);
        $ok = @$stmt->execute();
        $stmt->close();

        return (bool) $ok;
    }
}

if (!function_exists('appMarkNotificationRead')) {
    function appMarkNotificationRead(mysqli $db, int $notificationId, int $userId, string $role): bool
    {
        if (!appCanAccessNotification($db, $notificationId, $userId, $role)) {
            return false;
        }

        $ok = appSetNotificationState($db, $notificationId, $userId, true, false);

        // Untuk notifikasi personal, kolom lama tetap ikut diperbarui agar kompatibel dengan data lama.
        $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?');
        if ($stmt) {
            $stmt->bind_param('ii', $notificationId, $userId);
            @$stmt->execute();
            $stmt->close();
        }

        return $ok;
    }
}

if (!function_exists('appDeleteNotification')) {
    function appDeleteNotification(mysqli $db, int $notificationId, int $userId, string $role): bool
    {
        if (!appCanAccessNotification($db, $notificationId, $userId, $role)) {
            return false;
        }

        return appSetNotificationState($db, $notificationId, $userId, true, true);
    }
}

if (!function_exists('appMarkAllNotificationsRead')) {
    function appMarkAllNotificationsRead(mysqli $db, int $userId, string $role): int
    {
        $ids = appVisibleNotificationIds($db, $userId, $role, true);
        $success = 0;

        foreach ($ids as $id) {
            if (appMarkNotificationRead($db, (int) $id, $userId, $role)) {
                $success++;
            }
        }

        // Jaga kompatibilitas untuk notifikasi personal yang memakai kolom lama.
        $stmt = $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0');
        if ($stmt) {
            $stmt->bind_param('i', $userId);
            @$stmt->execute();
            $stmt->close();
        }

        return $success;
    }
}

if (!function_exists('appDeleteAllNotifications')) {
    function appDeleteAllNotifications(mysqli $db, int $userId, string $role): int
    {
        $ids = appVisibleNotificationIds($db, $userId, $role, false);
        $success = 0;

        foreach ($ids as $id) {
            if (appDeleteNotification($db, (int) $id, $userId, $role)) {
                $success++;
            }
        }

        return $success;
    }
}

if (!function_exists('appBulkNotificationAction')) {
    function appBulkNotificationAction(mysqli $db, array $ids, int $userId, string $role, string $action): int
    {
        $success = 0;
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $id): bool => $id > 0)));

        foreach ($ids as $id) {
            if ($action === 'delete') {
                $success += appDeleteNotification($db, $id, $userId, $role) ? 1 : 0;
            } elseif ($action === 'read') {
                $success += appMarkNotificationRead($db, $id, $userId, $role) ? 1 : 0;
            }
        }

        return $success;
    }
}

if (!function_exists('appNotificationSafeRedirect')) {
    function appNotificationSafeRedirect(string $linkUrl, string $fallback = 'main.php?page=notifikasi'): string
    {
        $linkUrl = trim($linkUrl);

        if ($linkUrl === '' || preg_match('/[\r\n]/', $linkUrl)) {
            return $fallback;
        }

        // Hindari open redirect dan skema berbahaya. Link notifikasi SIMADA harus relatif/internal.
        if (preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $linkUrl) || preg_match('#^(?:javascript|data|vbscript):#i', $linkUrl)) {
            return $fallback;
        }

        return $linkUrl;
    }
}

if (!function_exists('appNotificationLinkForUser')) {
    function appNotificationLinkForUser(mysqli $db, int $notificationId, int $userId, string $role): string
    {
        appEnsureSimadaCoreSchema($db);

        $sql = "SELECT n.link_url
                FROM notifications n
                LEFT JOIN notification_user_states ns
                    ON ns.notification_id = n.id
                   AND ns.user_id = ?
                WHERE n.id = ?
                  AND " . appNotificationVisibleConditionSql() . "
                  AND " . appNotificationStateNotDeletedSql() . "
                LIMIT 1";

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return 'main.php?page=notifikasi';
        }

        $stmt->bind_param('iiis', $userId, $notificationId, $userId, $role);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return appNotificationSafeRedirect((string) ($row['link_url'] ?? ''), 'main.php?page=notifikasi');
    }
}

if (!function_exists('appParseNotificationIds')) {
    function appParseNotificationIds(mixed $raw): array
    {
        if (is_array($raw)) {
            $parts = $raw;
        } else {
            $parts = preg_split('/[,\s]+/', (string) $raw) ?: [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $parts), static fn (int $id): bool => $id > 0)));
    }
}


if (!function_exists('appEnsureUserDocumentKey')) {
    function appEnsureUserDocumentKey(mysqli $db, int $userId): string
    {
        return '';
    }
}

if (!function_exists('appUserDocumentKey')) {
    function appUserDocumentKey(mysqli $db, int $userId): string
    {
        return '';
    }
}

if (!function_exists('appUserDocumentKeyFingerprint')) {
    function appUserDocumentKeyFingerprint(mysqli $db, int $userId): string
    {
        return '-';
    }
}


if (!function_exists('appDocumentPreviewCacheDir')) {
    function appDocumentPreviewCacheDir(): string
    {
        $dir = appNormalizePath(appTempStorageBasePath() . '/previews');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        return $dir;
    }
}

if (!function_exists('appFindLibreOfficeBinary')) {
    function appFindLibreOfficeBinary(): ?string
    {
        $knownPaths = appIsWindows()
            ? [
                'C:/Program Files/LibreOffice/program/soffice.exe',
                'C:/Program Files (x86)/LibreOffice/program/soffice.exe',
            ]
            : [
                '/usr/bin/libreoffice',
                '/usr/bin/soffice',
                '/snap/bin/libreoffice',
            ];

        $candidates = appIsWindows()
            ? ['soffice.exe', 'soffice', 'libreoffice.exe', 'libreoffice']
            : ['libreoffice', 'soffice'];

        $resolved = appResolveExecutable('LIBREOFFICE_PATH', $candidates, $knownPaths);
        return $resolved !== '' ? $resolved : null;
    }
}

if (!function_exists('appPreviewFileExtension')) {
    function appPreviewFileExtension(array $document, string $fileName = ''): string
    {
        $extension = strtolower(trim((string) ($document['file_extension'] ?? '')));
        if ($extension === '' && $fileName !== '') {
            $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        }
        if ($extension === '' && !empty($document['file_path'])) {
            $extension = strtolower(pathinfo((string) $document['file_path'], PATHINFO_EXTENSION));
        }
        return preg_replace('/[^a-z0-9]+/', '', $extension) ?? '';
    }
}

if (!function_exists('appCanConvertOfficePreview')) {
    function appCanConvertOfficePreview(string $extension): bool
    {
        return in_array(strtolower($extension), ['doc', 'docx', 'xls', 'xlsx'], true);
    }
}

if (!function_exists('appOfficePreviewPdfPath')) {
    function appOfficePreviewPdfPath(string $absolutePath, array $document): ?string
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }

        $extension = appPreviewFileExtension($document, basename($absolutePath));
        if (!appCanConvertOfficePreview($extension)) {
            return null;
        }

        $office = appFindLibreOfficeBinary();
        if ($office === null) {
            return null;
        }

        $cacheDir = appDocumentPreviewCacheDir();
        if (!is_dir($cacheDir) || !is_writable($cacheDir)) {
            return null;
        }

        $docId = (int) ($document['id'] ?? 0);
        $sourceStat = @stat($absolutePath) ?: [];
        $sourceKey = sha1($absolutePath . '|' . ($sourceStat['mtime'] ?? '') . '|' . ($sourceStat['size'] ?? ''));
        $targetPath = $cacheDir . '/document-' . $docId . '-' . $sourceKey . '.pdf';

        if (is_file($targetPath) && filesize($targetPath) > 0) {
            return $targetPath;
        }

        $workDir = appNormalizePath(sys_get_temp_dir() . '/simada_preview_' . bin2hex(random_bytes(8)));
        if (!@mkdir($workDir, 0770, true) && !is_dir($workDir)) {
            return null;
        }

        $inputPath = $workDir . '/source.' . $extension;
        if (!@copy($absolutePath, $inputPath)) {
            appRecursiveRemoveDir($workDir);
            return null;
        }

        $homeDir = $workDir . '/home';
        @mkdir($homeDir, 0770, true);
        $profileDir = $homeDir . '/libreoffice-profile';
        @mkdir($profileDir, 0770, true);
        $profileUri = appPathToFileUri($profileDir);

        // Gunakan profil LibreOffice sementara agar konversi headless stabil pada
        // Apache Windows maupun user www-data di Ubuntu Server.
        $cmd = escapeshellarg($office)
            . ($profileUri !== '' ? ' -env:UserInstallation=' . escapeshellarg($profileUri) : '')
            . ' --headless --nologo --nolockcheck --nodefault --nofirststartwizard'
            . ' --convert-to pdf --outdir ' . escapeshellarg($workDir)
            . ' ' . escapeshellarg($inputPath) . ' 2>&1';

        $output = [];
        $code = 1;
        @exec($cmd, $output, $code);

        $convertedPath = $workDir . '/source.pdf';
        if ($code === 0 && is_file($convertedPath) && filesize($convertedPath) > 0) {
            @rename($convertedPath, $targetPath);
            @chmod($targetPath, 0660);
            appRecursiveRemoveDir($workDir);
            return is_file($targetPath) ? $targetPath : null;
        }

        appRecursiveRemoveDir($workDir);
        return null;
    }
}

if (!function_exists('appRecursiveRemoveDir')) {
    function appRecursiveRemoveDir(string $dir): void
    {
        if ($dir === '' || !is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path) && !is_link($path)) {
                appRecursiveRemoveDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}

if (!function_exists('appReadDocumentBinary')) {
    function appReadDocumentBinary(mysqli $db, string $absolutePath, array $document): ?string
    {
        if (!is_file($absolutePath)) {
            return null;
        }

        $data = file_get_contents($absolutePath);
        return $data === false ? null : $data;
    }
}

if (!function_exists('appOutputDocumentBinary')) {
    function appOutputDocumentBinary(mysqli $db, string $absolutePath, array $document, string $downloadName, string $mimeType, string $disposition = 'attachment'): void
    {
        $binary = appReadDocumentBinary($db, $absolutePath, $document);
        if ($binary === null) {
            http_response_code(500);
            exit('File tidak dapat dibaca.');
        }

        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . strlen($binary));
        header('Content-Disposition: ' . $disposition . '; filename="' . str_replace(["\r", "\n", '"'], '', $downloadName) . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private');
        header('Pragma: public');
        echo $binary;
        exit();
    }
}

if (!function_exists('appResolveOcrExecutable')) {
    function appResolveOcrExecutable(string $envKey, array $candidateNames): string
    {
        $knownPaths = [];
        if (!appIsWindows()) {
            $linuxDefaults = [
                'PDFTOTEXT_PATH' => ['/usr/bin/pdftotext', '/usr/local/bin/pdftotext'],
                'PDFTOPPM_PATH' => ['/usr/bin/pdftoppm', '/usr/local/bin/pdftoppm'],
                'TESSERACT_PATH' => ['/usr/bin/tesseract', '/usr/local/bin/tesseract'],
            ];
            $knownPaths = $linuxDefaults[$envKey] ?? [];
        }

        return appResolveExecutable($envKey, $candidateNames, $knownPaths);
    }
}

if (!function_exists('appOcrNullRedirect')) {
    function appOcrNullRedirect(): string
    {
        return appExecutableNullRedirect();
    }
}

if (!function_exists('appCleanupDirectory')) {
    function appCleanupDirectory(string $path): void
    {
        if ($path === '' || !is_dir($path)) {
            return;
        }

        $items = glob(rtrim($path, '/\\') . DIRECTORY_SEPARATOR . '*') ?: [];
        foreach ($items as $item) {
            if (is_dir($item)) {
                appCleanupDirectory($item);
            } else {
                @unlink($item);
            }
        }
        @rmdir($path);
    }
}

if (!function_exists('appTesseractLanguageOption')) {
    function appTesseractLanguageOption(string $tesseractPath): string
    {
        if ($tesseractPath === '' || !function_exists('shell_exec')) {
            return '';
        }

        $requested = trim((string) simadaEnv('TESSERACT_LANG', 'ind+eng'));
        $listCmd = escapeshellarg($tesseractPath) . ' --list-langs' . appOcrNullRedirect();
        $raw = (string) @shell_exec($listCmd);
        $available = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || stripos($line, 'list of available languages') !== false) {
                continue;
            }
            $available[$line] = true;
        }

        if ($requested !== '') {
            $wanted = preg_split('/[+,]/', $requested, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $usable = [];
            foreach ($wanted as $lang) {
                $lang = trim($lang);
                if ($lang !== '' && isset($available[$lang])) {
                    $usable[] = $lang;
                }
            }
            if ($usable !== []) {
                return implode('+', $usable);
            }
        }

        if (isset($available['ind'])) {
            return 'ind';
        }
        if (isset($available['eng'])) {
            return 'eng';
        }

        return '';
    }
}

if (!function_exists('appExtractTextWithTesseractFallback')) {
    function appExtractTextWithTesseractFallback(string $tmpPdf): array
    {
        $pdftoppm = appResolveOcrExecutable('PDFTOPPM_PATH', ['pdftoppm.exe', 'pdftoppm']);
        $tesseract = appResolveOcrExecutable('TESSERACT_PATH', ['tesseract.exe', 'tesseract']);

        $missing = [];
        if ($pdftoppm === '') {
            $missing[] = 'pdftoppm';
        }
        if ($tesseract === '') {
            $missing[] = 'Tesseract';
        }
        if ($missing !== []) {
            return [
                'ok' => false,
                'text' => '',
                'engine' => 'none',
                'message' => 'Fallback OCR belum siap: ' . implode(' dan ', $missing) . ' belum ditemukan.',
            ];
        }

        $workDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'simada_ocr_' . bin2hex(random_bytes(6));
        if (!@mkdir($workDir, 0700, true) && !is_dir($workDir)) {
            return ['ok' => false, 'text' => '', 'engine' => 'tesseract', 'message' => 'Gagal membuat folder temporary OCR.'];
        }

        $prefix = $workDir . DIRECTORY_SEPARATOR . 'page';
        $maxPages = max(1, min(100, (int) simadaEnv('OCR_MAX_PAGES', 30)));
        $dpi = max(120, min(300, (int) simadaEnv('OCR_DPI', 200)));

        $convertCmd = escapeshellarg($pdftoppm)
            . ' -png -r ' . $dpi
            . ' -f 1 -l ' . $maxPages . ' '
            . escapeshellarg($tmpPdf) . ' '
            . escapeshellarg($prefix)
            . appOcrNullRedirect();
        @shell_exec($convertCmd);

        $images = glob($prefix . '-*.png') ?: [];
        natsort($images);
        $images = array_values($images);
        if ($images === []) {
            appCleanupDirectory($workDir);
            return ['ok' => false, 'text' => '', 'engine' => 'tesseract', 'message' => 'PDF tidak dapat dikonversi menjadi gambar untuk OCR.'];
        }

        $lang = appTesseractLanguageOption($tesseract);
        $parts = [];
        foreach ($images as $image) {
            $cmd = escapeshellarg($tesseract) . ' ' . escapeshellarg($image) . ' stdout';
            if ($lang !== '') {
                $cmd .= ' -l ' . escapeshellarg($lang);
            }
            $cmd .= ' --psm 6' . appOcrNullRedirect();
            $pageText = trim((string) @shell_exec($cmd));
            if ($pageText !== '') {
                $parts[] = $pageText;
            }
        }

        appCleanupDirectory($workDir);
        $text = trim(implode("\n\n", $parts));
        if ($text === '') {
            return ['ok' => false, 'text' => '', 'engine' => 'tesseract', 'message' => 'Tesseract berjalan, tetapi tidak menemukan teks yang dapat dibaca pada PDF.'];
        }

        return [
            'ok' => true,
            'text' => $text,
            'engine' => $lang !== '' ? 'tesseract-' . $lang : 'tesseract',
            'message' => 'PDF hasil scan berhasil dibaca dengan OCR Tesseract.',
        ];
    }
}

if (!function_exists('appExtractTextFromPdfOptional')) {
    function appExtractTextFromPdfOptional(mysqli $db, string $absolutePath, array $document): array
    {
        $binary = appReadDocumentBinary($db, $absolutePath, $document);
        if ($binary === null) {
            return ['ok' => false, 'text' => '', 'engine' => 'none', 'message' => 'Gagal membaca dokumen.'];
        }

        if (!function_exists('shell_exec')) {
            return ['ok' => false, 'text' => '', 'engine' => 'none', 'message' => 'Fungsi shell_exec PHP tidak tersedia sehingga ekstraksi otomatis tidak dapat dijalankan.'];
        }

        $tempDir = sys_get_temp_dir();
        $tmpPdf = tempnam($tempDir, 'simada_pdf_');
        if ($tmpPdf === false) {
            return ['ok' => false, 'text' => '', 'engine' => 'none', 'message' => 'Gagal membuat file temporary.'];
        }

        if (@file_put_contents($tmpPdf, $binary) === false) {
            @unlink($tmpPdf);
            return ['ok' => false, 'text' => '', 'engine' => 'none', 'message' => 'Gagal menyiapkan PDF untuk ekstraksi teks.'];
        }

        // Tahap 1: PDF digital. Ini paling ringan dan cepat.
        $pdftotext = appResolveOcrExecutable('PDFTOTEXT_PATH', ['pdftotext.exe', 'pdftotext']);
        if ($pdftotext !== '') {
            $tmpTxt = tempnam($tempDir, 'simada_txt_');
            if ($tmpTxt !== false) {
                $cmd = escapeshellarg($pdftotext)
                    . ' -layout -enc UTF-8 '
                    . escapeshellarg($tmpPdf) . ' '
                    . escapeshellarg($tmpTxt)
                    . appOcrNullRedirect();
                @shell_exec($cmd);
                $text = is_file($tmpTxt) ? trim((string) @file_get_contents($tmpTxt)) : '';
                @unlink($tmpTxt);

                if ($text !== '') {
                    @unlink($tmpPdf);
                    return [
                        'ok' => true,
                        'text' => $text,
                        'engine' => 'pdftotext',
                        'message' => 'Teks PDF digital berhasil diekstrak otomatis.',
                    ];
                }
            }
        }

        // Tahap 2: jika lapisan teks kosong, anggap PDF scan/gambar dan gunakan OCR.
        $fallback = appExtractTextWithTesseractFallback($tmpPdf);
        @unlink($tmpPdf);

        if (($fallback['ok'] ?? false) === true) {
            return $fallback;
        }

        $missingInfo = $pdftotext === '' ? 'pdftotext belum ditemukan. ' : '';
        $fallbackMessage = trim((string) ($fallback['message'] ?? 'OCR Tesseract belum tersedia.'));
        return [
            'ok' => false,
            'text' => '',
            'engine' => (string) ($fallback['engine'] ?? 'none'),
            'message' => trim($missingInfo . $fallbackMessage . ' Dokumen tetap tersimpan dan teks dapat diisi manual.'),
        ];
    }
}

if (!function_exists('appTokenizeForIr')) {
    function appTokenizeForIr(string $text): array
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\p{L}\s]+/u', ' ', $text) ?? $text;
        $parts = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = array_flip(['dan','atau','yang','di','ke','dari','untuk','dengan','pada','ini','itu','the','a','an','of','to','in','is','are']);
        $tokens = [];
        foreach ($parts as $part) {
            if (strlen($part) < 3 || isset($stop[$part])) {
                continue;
            }
            $tokens[] = $part;
        }
        return $tokens;
    }
}

if (!function_exists('appTfIdfRankDocuments')) {
    function appTfIdfRankDocuments(string $query, array $documents): array
    {
        $queryTokens = array_count_values(appTokenizeForIr($query));
        if (!$queryTokens || !$documents) {
            return [];
        }

        $docTokens = [];
        $df = [];
        foreach ($documents as $idx => $doc) {
            $tokens = array_count_values(appTokenizeForIr((string) ($doc['search_text'] ?? '')));
            $docTokens[$idx] = $tokens;
            foreach (array_keys($tokens) as $token) {
                $df[$token] = ($df[$token] ?? 0) + 1;
            }
        }

        $n = max(1, count($documents));
        $ranked = [];
        foreach ($documents as $idx => $doc) {
            $score = 0.0;
            $tokens = $docTokens[$idx] ?? [];
            $docLength = max(1, array_sum($tokens));
            foreach ($queryTokens as $token => $qtf) {
                if (!isset($tokens[$token])) {
                    continue;
                }
                $tf = $tokens[$token] / $docLength;
                $idf = log(($n + 1) / (($df[$token] ?? 0) + 1)) + 1;
                $score += $tf * $idf * $qtf;
            }
            if ($score > 0) {
                $doc['ir_score'] = round($score * 100, 4);
                $ranked[] = $doc;
            }
        }

        usort($ranked, fn(array $a, array $b) => ($b['ir_score'] <=> $a['ir_score']));
        return $ranked;
    }
}

if (!function_exists('appAttendanceClusterLabel')) {
    function appAttendanceClusterLabel(array $row): array
    {
        $hadir = (int) ($row['hadir'] ?? 0);
        $izin = (int) ($row['izin'] ?? 0);
        $sakit = (int) ($row['sakit'] ?? 0);
        $alpha = (int) ($row['alpha'] ?? 0);
        $terlambat = (int) ($row['terlambat'] ?? 0);
        $total = max(1, $hadir + $izin + $sakit + $alpha);
        $score = ($hadir / $total * 100) - ($alpha * 8) - ($terlambat * 2) - ($izin * 1.5) - ($sakit * 1);
        if ($score >= 85) {
            $label = 'Disiplin Tinggi';
        } elseif ($score >= 70) {
            $label = 'Stabil';
        } elseif ($score >= 55) {
            $label = 'Perlu Pemantauan';
        } else {
            $label = 'Risiko Absensi';
        }
        return [$label, round($score, 2)];
    }
}
