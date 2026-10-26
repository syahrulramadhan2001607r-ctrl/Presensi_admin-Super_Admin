<?php
/**
 * POST /api/kiosk.php?sesi=Sesi%201
 * Dipakai halaman "Presensi Kamera" (webcam di browser).
 *
 * Alur: browser kirim 1 frame JPEG -> PHP meneruskan ke server Python (pengenalan wajah)
 *       -> PHP menerapkan threshold & aturan presensi -> hasil dikembalikan ke browser.
 *
 * Autentikasi: sesi login Admin/Super Admin + header X-CSRF-Token.
 * Body       : byte JPEG (Content-Type: image/jpeg), maksimal 1,5 MB.
 * Respons    : status = hadir | sudah_presensi | tidak_dikenal | tidak_ada_wajah
 *              Kesalahan: 400 gambar_tidak_valid, 401 belum_login, 403 csrf_tidak_valid,
 *              405, 413, 503 layanan_pengenalan_tidak_tersedia, 500 server_error
 */
define('API_MODE', true);
require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/presensi_service.php';

const KIOSK_MAX_BYTES = 1572864; // 1,5 MB

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    api_out(405, ['status' => 'metode_tidak_diizinkan']);
}

$user = api_user();

try {
    $jpeg = file_get_contents('php://input', false, null, 0, KIOSK_MAX_BYTES + 1);
    if ($jpeg === false || $jpeg === '') {
        api_out(400, ['status' => 'gambar_tidak_valid', 'pesan' => 'Gambar kosong.']);
    }
    if (strlen($jpeg) > KIOSK_MAX_BYTES) {
        api_out(413, ['status' => 'payload_terlalu_besar']);
    }
    $info = @getimagesizefromstring($jpeg);
    if (!$info || $info[2] !== IMAGETYPE_JPEG) {
        api_out(400, ['status' => 'gambar_tidak_valid', 'pesan' => 'Berkas harus JPEG yang valid.']);
    }

    $sesi = trim((string)($_GET['sesi'] ?? 'Sesi 1'));
    if ($sesi === '' || mb_strlen($sesi) > 50) {
        api_out(400, ['status' => 'permintaan_tidak_valid', 'pesan' => 'sesi 1-50 karakter.']);
    }

    $hasil = pengenalan_kirim($jpeg);
    if (!$hasil['ok']) {
        api_out(503, ['status' => 'layanan_pengenalan_tidak_tersedia', 'pesan' => $hasil['error']]);
    }
    $d = $hasil['data'];

    if (($d['status'] ?? '') === 'tidak_ada_wajah') {
        api_out(200, ['status' => 'tidak_ada_wajah']);
    }
    $idp  = trim((string)($d['id_pengguna'] ?? ''));
    $conf = $d['confidence'] ?? null;
    if (($d['status'] ?? '') !== 'ok' || $idp === '' || !is_numeric($conf) || $conf < 0 || $conf > 1) {
        api_out(200, ['status' => 'tidak_dikenal']);
    }

    [$kode, $isi] = catat_presensi($idp, round((float)$conf, 4), $sesi, 'webcam-' . $user['username']);
    api_out($kode, $isi);
} catch (Throwable $e) {
    error_log('API kiosk: ' . $e->getMessage());
    api_out(500, ['status' => 'server_error']);
}
