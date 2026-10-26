<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/rekap_helper.php';
require_login(['admin', 'superadmin']);

$f = rekap_filter();

$rows = q("SELECT p.tanggal, p.waktu, m.nim, m.nama, m.kelas, p.sesi, p.status, p.confidence, p.device_id, p.keterangan
           FROM presensi p JOIN mahasiswa m ON m.id = p.mahasiswa_id
           WHERE {$f['where']} ORDER BY p.tanggal, p.sesi, m.nama", $f['params'])->fetchAll();

audit('ekspor_rekap_csv', $f['filters']['dari'] . ' s/d ' . $f['filters']['sampai'] . ' (' . count($rows) . ' baris)');

$nama = 'rekap_presensi_' . $f['filters']['dari'] . '_sd_' . $f['filters']['sampai'] . '.csv';

session_write_close();
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nama . '"');
header('Cache-Control: no-store');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
fputcsv($out, ['Tanggal', 'Waktu', 'NIM', 'Nama', 'Kelas', 'Sesi', 'Status', 'Confidence (%)', 'Perangkat', 'Keterangan'], ';');
foreach ($rows as $r) {
    fputcsv($out, [
        $r['tanggal'],
        date('H:i:s', strtotime($r['waktu'])),
        csv_safe($r['nim']),
        csv_safe($r['nama']),
        csv_safe($r['kelas']),
        csv_safe($r['sesi']),
        $r['status'],
        $r['confidence'] !== null ? number_format((float)$r['confidence'] * 100, 1, ',', '') : '',
        csv_safe($r['device_id'] ?? ''),
        csv_safe($r['keterangan'] ?? ''),
    ], ';');
}
fclose($out);
exit;
