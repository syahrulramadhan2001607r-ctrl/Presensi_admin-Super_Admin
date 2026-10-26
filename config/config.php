<?php
/**
 * Konfigurasi aplikasi.
 * Sesuaikan nilai di bawah ini dengan lingkungan XAMPP Anda.
 */

// --- Database (default XAMPP: user root tanpa password) ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'presensi_wajah');
define('DB_USER', 'root');
define('DB_PASS', '');

// --- Aplikasi ---
define('APP_NAME', 'Presensi Wajah CNN');

// Alamat dasar aplikasi. Dideteksi OTOMATIS dari lokasi folder proyek terhadap htdocs:
//   C:\xampp\htdocs\presensi_admin  ->  '/presensi_admin'
//   C:\xampp\htdocs  (langsung di root) ->  ''
// Isi manual HANYA jika deteksi otomatis salah, contoh: '/presensi_admin'. Kosongkan ('') untuk otomatis.
const BASE_URL_MANUAL = '';

(function () {
    if (BASE_URL_MANUAL !== '') {
        define('BASE_URL', '/' . trim(BASE_URL_MANUAL, '/'));
        return;
    }
    $norm = function (string $p): string {
        return rtrim(str_replace('\\', '/', $p), '/');
    };
    $proyek = $norm((string)realpath(dirname(__DIR__)));
    $doc    = $norm((string)realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $base   = '';
    if ($doc !== '' && stripos($proyek, $doc) === 0) {
        $base = substr($proyek, strlen($doc));
    }
    define('BASE_URL', $base === '' ? '' : '/' . trim($base, '/'));
})();

// Zona waktu institusi (WITA) dan offset yang sama untuk MySQL
define('APP_TIMEZONE', 'Asia/Makassar');
define('APP_TZ_OFFSET', '+08:00');

// Sesi login berakhir bila tidak aktif selama 30 menit
define('SESSION_TIMEOUT', 1800);

// Pembatasan login gagal: maksimal percobaan dalam jendela waktu (menit)
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_WINDOW_MINUTES', 15);

// --- Layanan pengenalan wajah (server Python, folder python_server/) ---
// Halaman "Presensi Kamera" mengirim gambar webcam ke server ini untuk dikenali.
// Kosongkan PY_SERVER_URL bila server Python belum dijalankan.
define('PY_SERVER_URL', 'http://127.0.0.1:5000');
define('PY_SERVER_TOKEN', '');   // samakan dengan variabel lingkungan PY_SERVER_TOKEN di server Python
define('PY_TIMEOUT', 8);         // detik

// Unggah foto wajah
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/wajah');
define('UPLOAD_MAX_BYTES', 2 * 1024 * 1024); // 2 MB per foto
define('UPLOAD_MAX_PER_MHS', 30);            // maksimal foto per mahasiswa
define('UPLOAD_MIN_DIMENSION', 160);         // sisi terpendek minimal (px), sesuai input model 160x160

// Tampilkan error PHP saat pengembangan (ubah ke false pada produksi)
define('APP_DEBUG', true);
if (APP_DEBUG) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}
