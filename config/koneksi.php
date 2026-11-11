<?php
/* =========================================================
   KONEKSI DATABASE SIMADA
   Fungsi: Menghubungkan aplikasi ke MySQL/MariaDB memakai mysqli.
   Siap dipakai di shared hosting seperti InfinityFree.
========================================================= */

declare(strict_types=1);

date_default_timezone_set('Asia/Makassar');

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/helpers.php';
simadaLoadEnv();

mysqli_report(MYSQLI_REPORT_OFF);

$host = (string) simadaEnv('DB_HOST', '127.0.0.1');
$user = (string) simadaEnv('DB_USERNAME', 'root');
$pass = (string) simadaEnv('DB_PASSWORD', '');
$db   = (string) simadaEnv('DB_DATABASE', 'simada');
$port = (int) simadaEnv('DB_PORT', 3306);
$appDebug = filter_var((string) simadaEnv('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);

$conn = @new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    http_response_code(500);

    if ($appDebug) {
        exit('Koneksi database gagal: ' . e($conn->connect_error));
    }

    exit('Koneksi database gagal. Periksa konfigurasi database Anda.');
}

$conn->set_charset('utf8mb4');
$conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
$koneksi = $conn;
