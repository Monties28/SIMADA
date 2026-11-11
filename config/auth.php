<?php
/* =========================================================
   FUNGSI AUTENTIKASI
   Fungsi: Menyediakan fungsi hash password, verifikasi password, dan upgrade password lama ke format hash.
========================================================= */

declare(strict_types=1);

/* =========================================================
   BAGIAN FUNGSI PASSWORD
   Bagian ini berisi fungsi untuk hash, verifikasi, dan pembaruan password.
========================================================= */
function isPasswordHash(string $value): bool
{
    return $value !== '' && password_get_info($value)['algo'] !== null;
}

function hashAppPassword(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyAppPassword(string $plainPassword, ?string $storedPassword): bool
{
    $storedPassword = (string) $storedPassword;

    if ($storedPassword === '') {
        return false;
    }

    if (isPasswordHash($storedPassword)) {
        return password_verify($plainPassword, $storedPassword);
    }

    return hash_equals($storedPassword, $plainPassword);
}

function shouldUpgradePasswordHash(?string $storedPassword): bool
{
    $storedPassword = (string) $storedPassword;

    if ($storedPassword === '') {
        return false;
    }

    if (!isPasswordHash($storedPassword)) {
        return true;
    }

    return password_needs_rehash($storedPassword, PASSWORD_DEFAULT);
}

function upgradePasswordHash(mysqli $db, int $userId, string $plainPassword): bool
{
    $newHash = hashAppPassword($plainPassword);
    $stmt = $db->prepare('UPDATE users SET password = ? WHERE id = ? LIMIT 1');

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('si', $newHash, $userId);
    $ok = $stmt->execute();
    $stmt->close();

    return $ok;
}
