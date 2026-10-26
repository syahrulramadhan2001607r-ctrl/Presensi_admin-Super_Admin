<?php
/**
 * Menampilkan foto wajah hanya untuk admin/superadmin yang sudah login.
 * Foto tidak dapat diakses langsung lewat URL (lihat uploads/.htaccess).
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if (!current_user()) {
    http_response_code(403);
    exit('Akses ditolak.');
}

$id = (int)($_GET['id'] ?? 0);
$f  = q('SELECT mahasiswa_id, nama_file FROM data_wajah WHERE id = ?', [$id])->fetch();
if (!$f) {
    http_response_code(404);
    exit('Foto tidak ditemukan.');
}

$path = UPLOAD_DIR . '/' . (int)$f['mahasiswa_id'] . '/' . basename($f['nama_file']);
if (!is_file($path)) {
    http_response_code(404);
    exit('Berkas tidak ada.');
}

$ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$mime = $ext === 'png' ? 'image/png' : 'image/jpeg';

session_write_close();
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=300');
readfile($path);
