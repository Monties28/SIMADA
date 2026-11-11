<?php
/* =========================================================
   SECURITY BOOTSTRAP SIMADA
   Fungsi: Header keamanan, session cookie aman, CSRF token,
   dan helper respons JSON untuk endpoint AJAX.
========================================================= */

declare(strict_types=1);

if (!function_exists('simadaIsHttps')) {
    function simadaIsHttps(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $https === 'on' || $https === '1' || $forwardedProto === 'https';
    }
}

if (!function_exists('appSendSecurityHeaders')) {
    function appSendSecurityHeaders(bool $noStore = false, string $framePolicy = 'DENY', string $frameAncestors = "'none'"): void
    {
        if (headers_sent()) {
            return;
        }

        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: ' . $framePolicy);
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        header('X-Permitted-Cross-Domain-Policies: none');
        header('Cross-Origin-Resource-Policy: same-origin');

        $csp = "default-src 'self'; "
            . "base-uri 'self'; "
            . "frame-src 'self'; "
            . "frame-ancestors " . $frameAncestors . "; "
            . "object-src 'none'; "
            . "img-src 'self' data: blob:; "
            . "font-src 'self' https://cdnjs.cloudflare.com data:; "
            . "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; "
            . "script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; "
            . "connect-src 'self'; "
            . "form-action 'self' https://wa.me;";
        header('Content-Security-Policy: ' . $csp);

        if (simadaIsHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }

        if ($noStore) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        }
    }
}

if (!function_exists('appSecureSessionStart')) {
    function appSecureSessionStart(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');

        if (session_name() !== 'SIMADA_SESSID') {
            session_name('SIMADA_SESSID');
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => simadaIsHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        session_start();
    }
}

if (!function_exists('appCsrfToken')) {
    function appCsrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            appSecureSessionStart();
        }

        if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['csrf_token'];
    }
}

if (!function_exists('appVerifyCsrfToken')) {
    function appVerifyCsrfToken(?string $token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            appSecureSessionStart();
        }

        $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
        $token = (string) $token;

        return $sessionToken !== '' && $token !== '' && hash_equals($sessionToken, $token);
    }
}

if (!function_exists('appRequireCsrfToken')) {
    function appRequireCsrfToken(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $token = (string) ($_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if (appVerifyCsrfToken($token)) {
            return;
        }

        http_response_code(419);
        exit('Sesi keamanan tidak valid. Silakan refresh halaman lalu coba lagi.');
    }
}

if (!function_exists('appCsrfInput')) {
    function appCsrfInput(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(appCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('appJsonResponse')) {
    function appJsonResponse(array $payload, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}
