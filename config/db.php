<?php
/* =========================================================
   KOMPATIBILITAS PDO
   Fungsi: Menyediakan koneksi PDO MySQL jika ada file lama yang memakai getDB().
========================================================= */

declare(strict_types=1);

require_once __DIR__ . '/env.php';
simadaLoadEnv();

function getDB(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = (string) simadaEnv('DB_HOST', '127.0.0.1');
    $db = (string) simadaEnv('DB_DATABASE', 'simada');
    $user = (string) simadaEnv('DB_USERNAME', 'root');
    $pass = (string) simadaEnv('DB_PASSWORD', '');
    $port = (int) simadaEnv('DB_PORT', 3306);

    $dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}
