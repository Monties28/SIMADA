<?php

declare(strict_types=1);

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

function appStorageStatusFolder(string $status): string
{
    return match (appNormalizeDocumentStatus($status)) {
        'approved' => 'Document',
        'rejected' => 'Rejected',
        default => 'Pending',
    };
}

function appProjectRoot(): string
{
    return dirname(__DIR__);
}

function appNormalizePath(string $path): string
{
    $path = str_replace('\\', '/', trim($path));
    $path = preg_replace('#/+#', '/', $path) ?? $path;
    return rtrim($path, '/');
}

function appStorageBasePath(): string
{
    global $simdoStorageConfig;

    $configuredPath = trim((string) ($simdoStorageConfig['base_path'] ?? ''));

    if ($configuredPath === '') {
        $configuredPath = appProjectRoot() . '/storage';
    }

    return appNormalizePath($configuredPath);
}

function appIsAbsolutePath(string $path): bool
{
    $path = str_replace('\\', '/', trim($path));
    return $path !== '' && ($path[0] === '/' || preg_match('/^[A-Za-z]:\//', $path) === 1);
}

function appSanitizeStoredRelativePath(string $relativePath): string
{
    $relativePath = trim(str_replace('\\', '/', $relativePath));
    $relativePath = str_replace([chr(0), '../', '..\\'], '', $relativePath);
    $relativePath = preg_replace('#/+#', '/', $relativePath) ?? $relativePath;
    return ltrim($relativePath, '/');
}

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
    $baseStorage = appStorageBasePath();
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

function appEnsureDirectoryExists(string $directory): bool
{
    return is_dir($directory) || mkdir($directory, 0755, true);
}

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

function appRemoveEmptyParentDirectories(string $startDirectory): void
{
    $startDirectory = appNormalizePath($startDirectory);
    $stopRoots = array_filter(array_unique([
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

function appMakeUniqueStorageFilename(string $directory, string $fileName): string
{
    $fileName = basename(str_replace('\\', '/', $fileName));
    $fileName = trim($fileName, ' ._-');

    if ($fileName === '') {
        $fileName = 'document';
    }

    $extension = pathinfo($fileName, PATHINFO_EXTENSION);
    $baseName = pathinfo($fileName, PATHINFO_FILENAME);
    $baseName = trim($baseName, ' ._-');

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

    if (strcasecmp(trim($jenisDokumen), 'Lainnya') === 0 && trim($jenisDokumenLainnya) !== '') {
        $segments[] = $otherTypeFolder;
    }

    $segments[] = $dateFolder;
    $segments[] = $userFolder;
    $segments[] = $safeFileName;

    return implode('/', $segments);
}

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

function appIsAllowedDocumentUpload(string $extension, string $mimeType): bool
{
    $extension = strtolower(trim($extension));
    $mimeType = strtolower(trim($mimeType));

    if ($extension !== 'pdf') {
        return false;
    }

    $allowedPdfMimeTypes = [
        'application/pdf',
        'application/x-pdf',
        'application/acrobat',
        'applications/vnd.pdf',
        'text/pdf',
    ];

    if (in_array($mimeType, $allowedPdfMimeTypes, true)) {
        return true;
    }

    return $mimeType === '' || $mimeType === 'application/octet-stream';
}

function appSanitizeOriginalFilename(string $filename): string
{
    $filename = basename($filename);
    $filename = preg_replace('/[^a-zA-Z0-9 ._-]/', ' ', $filename) ?? '';
    $filename = preg_replace('/\s+/', ' ', $filename) ?? $filename;
    $filename = trim((string) $filename, ' ._-');

    return $filename !== '' ? $filename : 'document';
}

function appDeleteFileByAbsolutePath(string $absolutePath): void
{
    $absolutePath = trim($absolutePath);

    if ($absolutePath !== '' && is_file($absolutePath)) {
        @unlink($absolutePath);
        appRemoveEmptyParentDirectories(dirname($absolutePath));
    }
}

function appDeleteOldRevisionFile(?string $oldRelativePath, string $newAbsolutePath): void
{
    $oldRelativePath = trim((string) $oldRelativePath);
    if ($oldRelativePath === '') {
        return;
    }

    $oldAbsolutePath = (string) (appResolveDocumentAbsolutePath($oldRelativePath, false) ?? '');
    if (is_file($oldAbsolutePath) && realpath($oldAbsolutePath) !== realpath($newAbsolutePath)) {
        appDeleteFileByAbsolutePath($oldAbsolutePath);
    }
}

function appStoreUploadedPdf(array $file, array $context): array
{
    $maxSize = (int) ($context['max_size'] ?? (10 * 1024 * 1024));
    $originalName = appSanitizeOriginalFilename((string) ($file['name'] ?? 'document'));
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $fileSize = (int) ($file['size'] ?? 0);
    $mimeType = appDetectUploadedMimeType($file);
    $tmpName = (string) ($file['tmp_name'] ?? '');

    if (!appIsAllowedDocumentUpload($extension, $mimeType)) {
        return ['ok' => false, 'message' => 'Format file tidak valid. Sistem hanya menerima PDF, Word, dan Excel.'];
    }

    if ($fileSize <= 0 || $fileSize > $maxSize) {
        return ['ok' => false, 'message' => 'Ukuran file maksimal 10MB.'];
    }

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['ok' => false, 'message' => 'File upload tidak valid.'];
    }

    $storedFileName = $originalName;
    $relativePath = appBuildStorageRelativePath(
        (string) ($context['status'] ?? 'Pending'),
        (string) ($context['kategori'] ?? ''),
        (string) ($context['jenis'] ?? ''),
        (int) ($context['user_id'] ?? 0),
        $storedFileName,
        (string) ($context['tanggal_dokumen'] ?? ''),
        (string) ($context['user_name'] ?? ''),
        (string) ($context['jenis_lainnya'] ?? '')
    );

    $absolutePath = (string) (appResolveDocumentAbsolutePath($relativePath, true) ?? '');
    if ($absolutePath === '') {
        return ['ok' => false, 'message' => 'Path penyimpanan tidak valid.'];
    }

    $storageDir = dirname($absolutePath);
    if (!appEnsureDirectoryExists($storageDir)) {
        return ['ok' => false, 'message' => 'Folder penyimpanan gagal dibuat. Pastikan user web server memiliki izin menulis ke folder storage (Windows: Apache/XAMPP; Ubuntu: www-data).'];
    }

    $storedFileName = appMakeUniqueStorageFilename($storageDir, $storedFileName);
    $relativePath = appBuildStorageRelativePath(
        (string) ($context['status'] ?? 'Pending'),
        (string) ($context['kategori'] ?? ''),
        (string) ($context['jenis'] ?? ''),
        (int) ($context['user_id'] ?? 0),
        $storedFileName,
        (string) ($context['tanggal_dokumen'] ?? ''),
        (string) ($context['user_name'] ?? ''),
        (string) ($context['jenis_lainnya'] ?? '')
    );
    $absolutePath = (string) (appResolveDocumentAbsolutePath($relativePath, true) ?? '');

    if (!move_uploaded_file($tmpName, $absolutePath)) {
        return ['ok' => false, 'message' => 'File gagal disimpan ke storage.'];
    }

    return [
        'ok' => true,
        'original_name' => $originalName,
        'stored_file_name' => $storedFileName,
        'relative_path' => $relativePath,
        'absolute_path' => $absolutePath,
        'extension' => $extension,
        'mime_type' => $mimeType,
        'file_size' => $fileSize,
    ];
}

function appNormalizeDocumentState(string $status): string
{
    return match (appNormalizeDocumentStatus($status)) {
        'approved' => 'Approved',
        'revision' => 'Revision',
        'rejected' => 'Rejected',
        default => 'Pending',
    };
}

function appCanChangeDocumentState(string $currentStatus, string $newStatus): bool
{
    $currentStatus = appNormalizeDocumentState($currentStatus);
    $newStatus = appNormalizeDocumentState($newStatus);

    $transitions = [
        'Pending' => ['Approved', 'Revision', 'Rejected'],
        'Revision' => ['Approved', 'Rejected'],
        'Approved' => [],
        'Rejected' => [],
    ];

    return in_array($newStatus, $transitions[$currentStatus] ?? [], true);
}

function appMoveDocumentByState(array $doc, string $newStatus): array
{
    $newStatus = appNormalizeDocumentState($newStatus);
    $currentPath = trim((string) ($doc['file_path'] ?? ''));

    if ($currentPath === '') {
        return ['ok' => false, 'path' => $currentPath, 'message' => 'empty_path'];
    }

    $source = (string) (appResolveDocumentAbsolutePath($currentPath, false) ?? '');

    if (!is_file($source)) {
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

    $userName = trim((string) ($doc['user_name'] ?? ''));
    $jenisUntukFolder = trim((string) ($doc['jenis_dokumen'] ?? ''));
    $jenisLainnyaUntukFolder = trim((string) ($doc['jenis_dokumen_lainnya'] ?? ''));

    $tanggalDokumen = trim((string) ($doc['tanggal_dokumen'] ?? ''));
    if ($tanggalDokumen === '') {
        $tanggalDokumen = trim((string) ($doc['created_at'] ?? ''));
    }

    $targetRelativePath = appBuildStorageRelativePath(
        $newStatus,
        (string) ($doc['kategori_dokumen'] ?? ''),
        $jenisUntukFolder,
        $userId,
        $storedFileName,
        $tanggalDokumen,
        $userName,
        $jenisLainnyaUntukFolder
    );
    $target = (string) (appResolveDocumentAbsolutePath($targetRelativePath, true) ?? '');
    $targetDir = dirname($target);

    if (!appEnsureDirectoryExists($targetDir)) {
        return ['ok' => false, 'path' => $currentPath, 'message' => 'target_dir_failed'];
    }

    if ($source === $target) {
        return ['ok' => true, 'path' => $targetRelativePath, 'stored_file_name' => $storedFileName, 'message' => 'same_path'];
    }

    if (is_file($target)) {
        $storedFileName = appMakeUniqueStorageFilename($targetDir, $storedFileName);
        $targetRelativePath = appBuildStorageRelativePath(
            $newStatus,
            (string) ($doc['kategori_dokumen'] ?? ''),
            $jenisUntukFolder,
            $userId,
            $storedFileName,
            $tanggalDokumen,
            $userName,
            $jenisLainnyaUntukFolder
        );
        $target = (string) (appResolveDocumentAbsolutePath($targetRelativePath, true) ?? '');
    }

    if (!@rename($source, $target)) {
        if (!@copy($source, $target)) {
            return ['ok' => false, 'path' => $currentPath, 'message' => 'move_failed'];
        }

        @unlink($source);
    }

    appRemoveEmptyParentDirectories(dirname($source));

    return ['ok' => true, 'path' => $targetRelativePath, 'stored_file_name' => $storedFileName, 'message' => 'moved'];
}
