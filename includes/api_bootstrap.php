<?php
/**
 * Bootstrap khusus endpoint API (tanpa session & tanpa tampilan HTML).
 * Autentikasi memakai token perangkat: header "Authorization: Bearer <token>"
 * atau "X-Device-Token: <token>".
 */
require_once __DIR__ . '/../config/config.php';

date_default_timezone_set(APP_TIMEZONE);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

apply_timezone();

/** Kirim respons JSON lalu hentikan eksekusi. */
function api_out(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Autentikasi pengguna (Admin/Super Admin) lewat sesi login + header X-CSRF-Token.
 * Dipakai endpoint yang dipanggil dari halaman web (mis. api/kiosk.php).
 */
function api_user(): array
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('PRESENSI_ADMIN');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => BASE_URL === '' ? '/' : BASE_URL . '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);
        session_start();
    }
    $u = $_SESSION['user'] ?? null;
    if (!$u || time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
        api_out(401, ['status' => 'belum_login']);
    }
    $row = q('SELECT status_aktif, role FROM users WHERE id = ?', [$u['id']])->fetch();
    if (!$row || !(int)$row['status_aktif'] || !in_array($row['role'], ALLOWED_ROLES, true)) {
        api_out(401, ['status' => 'belum_login']);
    }
    $_SESSION['last_activity'] = time();
    $csrf = (string)($_SESSION['csrf'] ?? '');
    $sent = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    session_write_close();
    if ($csrf === '' || !hash_equals($csrf, $sent)) {
        api_out(403, ['status' => 'csrf_tidak_valid']);
    }
    return $u;
}

/** Autentikasi perangkat; mengembalikan baris perangkat atau menghentikan dengan 401. */
function api_device(): array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    $token  = '';
    if (stripos($header, 'Bearer ') === 0) {
        $token = trim(substr($header, 7));
    }
    if ($token === '') {
        $token = trim((string)($_SERVER['HTTP_X_DEVICE_TOKEN'] ?? ''));
    }
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        api_out(401, ['status' => 'perangkat_tidak_terotorisasi']);
    }

    $dev = q('SELECT * FROM perangkat WHERE token_hash = ? AND status_aktif = 1', [hash('sha256', $token)])->fetch();
    if (!$dev) {
        api_out(401, ['status' => 'perangkat_tidak_terotorisasi']);
    }
    q('UPDATE perangkat SET last_seen = NOW() WHERE id = ?', [$dev['id']]);
    return $dev;
}
