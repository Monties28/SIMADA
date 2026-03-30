<?php
/*
 * config/db.php
 * Koneksi ke SQLite (data.db)
 * Include file ini di setiap halaman yang butuh database:
 *   require_once __DIR__ . '/../../config/db.php';
 */

define('DB_PATH', __DIR__ . '/../data.db');

function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $pdo = new PDO('sqlite:' . DB_PATH);
            $pdo->setAttribute(PDO::ATTR_ERRMODE,            PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            /* Aktifkan foreign keys SQLite */
            $pdo->exec('PRAGMA foreign_keys = ON;');

            /* Buat tabel jika belum ada */
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    username   TEXT    NOT NULL UNIQUE,
                    password   TEXT    NOT NULL,
                    fullname   TEXT,
                    email      TEXT,
                    role       TEXT    NOT NULL DEFAULT 'user',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS documents (
                    id          INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id     INTEGER NOT NULL REFERENCES users(id),
                    filename    TEXT    NOT NULL,
                    original_name TEXT  NOT NULL,
                    type        TEXT,
                    year        TEXT,
                    period      TEXT,
                    ocr_text    TEXT,
                    encrypted   INTEGER DEFAULT 0,
                    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
                );
            ");

        } catch (PDOException $e) {
            /* Tampilkan error hanya di development */
            if (defined('APP_DEBUG') && APP_DEBUG) {
                die('Database error: ' . $e->getMessage());
            }
            die('Koneksi database gagal. Hubungi administrator.');
        }
    }

    return $pdo;
}