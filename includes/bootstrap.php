<?php
/**
 * Dimuat di awal setiap halaman: konfigurasi, session, database, helper, autentikasi.
 */
require_once __DIR__ . '/../config/config.php';

date_default_timezone_set(APP_TIMEZONE);

// Header keamanan dasar
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');

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

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Terapkan zona waktu dari tabel konfigurasi (PHP dan MySQL disamakan)
apply_timezone();
