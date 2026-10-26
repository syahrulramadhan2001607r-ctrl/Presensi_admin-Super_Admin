<?php
/**
 * GET /api/config.php
 * Memberi server Python nilai konfigurasi aktif (threshold, versi model, dst).
 * Header: Authorization: Bearer <token-perangkat>
 */
define('API_MODE', true);
require_once __DIR__ . '/../includes/api_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    api_out(405, ['status' => 'metode_tidak_diizinkan']);
}

api_device();

api_out(200, [
    'status'            => 'ok',
    'threshold'         => (float)get_config('threshold', '0.90'),
    'model_version'     => get_config('model_version', ''),
    'zona_waktu'        => get_config('zona_waktu', APP_TIMEZONE),
    'aturan_duplikasi'  => get_config('aturan_duplikasi', 'satu_per_sesi'),
    'versi_konfigurasi' => (int)get_config('versi_konfigurasi', '1'),
    'waktu_server'      => date('c'),
]);
