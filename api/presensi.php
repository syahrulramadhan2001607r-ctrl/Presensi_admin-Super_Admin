<?php
/**
 * POST /api/presensi.php   (klien eksternal bertoken, opsional)
 * Mencatat hasil pengenalan yang sudah dihitung pihak lain (mis. skrip Python sendiri).
 * Untuk webcam di browser, pakai halaman "Presensi Kamera" (api/kiosk.php).
 *
 * Header : Authorization: Bearer <token-kamera>   (atau X-Device-Token)
 * Body   : JSON, contoh
 *   {"nim":"2021001","confidence":0.94,"sesi":"Sesi 1","transaction_id":"opsional-uuid"}
 *   (boleh memakai "id_pengguna":"2021001_Nama")
 *
 * Respons (HTTP 200): status = hadir | sudah_presensi | tidak_dikenal
 * Kesalahan teknis  : 400 permintaan_tidak_valid, 401 perangkat_tidak_terotorisasi,
 *                     405 metode_tidak_diizinkan, 413 payload_terlalu_besar, 500 server_error
 */
define('API_MODE', true);
require_once __DIR__ . '/../includes/api_bootstrap.php';
require_once __DIR__ . '/../includes/presensi_service.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    api_out(405, ['status' => 'metode_tidak_diizinkan']);
}

$dev = api_device();

try {
    $raw = file_get_contents('php://input', false, null, 0, 4097);
    if ($raw === false || strlen($raw) > 4096) {
        api_out(413, ['status' => 'payload_terlalu_besar']);
    }
    $in = json_decode($raw, true);
    if (!is_array($in)) {
        api_out(400, ['status' => 'permintaan_tidak_valid', 'pesan' => 'Body harus berupa JSON.']);
    }

    $idp = trim((string)($in['id_pengguna'] ?? $in['nim'] ?? ''));
    if ($idp === '' || strlen($idp) > 160) {
        api_out(400, ['status' => 'permintaan_tidak_valid', 'pesan' => 'nim / id_pengguna wajib diisi.']);
    }
    if (!isset($in['confidence']) || !is_numeric($in['confidence']) || $in['confidence'] < 0 || $in['confidence'] > 1) {
        api_out(400, ['status' => 'permintaan_tidak_valid', 'pesan' => 'confidence harus angka 0 sampai 1.']);
    }
    $sesi = trim((string)($in['sesi'] ?? 'Sesi 1'));
    if ($sesi === '' || mb_strlen($sesi) > 50) {
        api_out(400, ['status' => 'permintaan_tidak_valid', 'pesan' => 'sesi 1-50 karakter.']);
    }
    $trx = trim((string)($in['transaction_id'] ?? ''));
    if ($trx !== '' && !preg_match('/^[A-Za-z0-9-]{8,36}$/', $trx)) {
        api_out(400, ['status' => 'permintaan_tidak_valid', 'pesan' => 'transaction_id 8-36 karakter (huruf, angka, minus).']);
    }

    [$kode, $isi] = catat_presensi($idp, round((float)$in['confidence'], 4), $sesi, $dev['device_id'], $trx);
    api_out($kode, $isi);
} catch (Throwable $e) {
    error_log('API presensi: ' . $e->getMessage());
    api_out(500, ['status' => 'server_error']);
}
