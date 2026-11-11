<?php
/* =========================================================
   KONFIGURASI STORAGE SIMADA
   Fungsi: Menentukan lokasi penyimpanan file upload.
   Default lintas platform: folder storage_simada di dalam project; production Ubuntu disarankan memakai /srv/documents lewat .env.
========================================================= */

declare(strict_types=1);

require_once __DIR__ . '/env.php';
simadaLoadEnv();

$basePath = trim((string) simadaEnv('SIMADA_STORAGE_PATH', ''));

if ($basePath === '') {
    $basePath = dirname(__DIR__) . '/storage_simada';
} else {
    // Lintas platform: dukung path absolut Windows (C:/...) dan Linux (/srv/...).
    $normalizedPath = str_replace('\\', '/', $basePath);
    $isWindowsAbsolute = strlen($normalizedPath) >= 3
        && ctype_alpha($normalizedPath[0])
        && $normalizedPath[1] === ':'
        && $normalizedPath[2] === '/';
    $isUnixAbsolute = str_starts_with($normalizedPath, '/');

    if (!$isWindowsAbsolute && !$isUnixAbsolute) {
        $basePath = dirname(__DIR__) . '/' . $basePath;
    }
}


return [
    'base_path' => $basePath,
    'documents_dir' => 'Documents',
    'profile_photos_dir' => 'Profile_Photos',
    'temp_dir' => 'Temp',
];
