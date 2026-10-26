<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/presensi_service.php';
require_login(['admin', 'superadmin']);

$pageTitle  = 'Dashboard Admin';
$activeMenu = 'dashboard';

$totalMhs    = (int)q('SELECT COUNT(*) FROM mahasiswa WHERE status_aktif = 1')->fetchColumn();
$hadirHariIni = (int)q('SELECT COUNT(DISTINCT mahasiswa_id) FROM presensi WHERE tanggal = CURDATE()')->fetchColumn();
$wajahTerdaftar = (int)q('SELECT COUNT(DISTINCT mahasiswa_id) FROM data_wajah')->fetchColumn();
$perangkatAktif = (int)q('SELECT COUNT(*) FROM perangkat WHERE status_aktif = 1')->fetchColumn();

$terbaru = q('SELECT p.waktu, p.sesi, p.status, p.confidence, m.nim, m.nama, m.kelas
              FROM presensi p JOIN mahasiswa m ON m.id = p.mahasiswa_id
              ORDER BY p.waktu DESC LIMIT 10')->fetchAll();

// Kehadiran 7 hari terakhir (termasuk hari tanpa data)
$perHari = q('SELECT tanggal, COUNT(DISTINCT mahasiswa_id) AS n FROM presensi
              WHERE tanggal >= (CURDATE() - INTERVAL 6 DAY) GROUP BY tanggal')->fetchAll(PDO::FETCH_KEY_PAIR);
$tujuh = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $tujuh[] = ['label' => date('d/m', strtotime($d)), 'n' => (int)($perHari[$d] ?? 0)];
}
$maks = max(1, max(array_column($tujuh, 'n')));

// Status layanan pengenalan wajah dan kamera/klien API (NFR-06)
$layanan = layanan_status();
$devices = q('SELECT device_id, nama, lokasi, status_aktif, last_seen FROM perangkat ORDER BY nama')->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-4" style="margin-bottom:1.25rem">
    <div class="stat"><div class="num"><?= $totalMhs ?></div><div class="lbl">Mahasiswa aktif</div></div>
    <div class="stat"><div class="num"><?= $hadirHariIni ?></div><div class="lbl">Hadir hari ini</div></div>
    <div class="stat"><div class="num"><?= $wajahTerdaftar ?></div><div class="lbl">Mahasiswa dengan foto wajah</div></div>
    <div class="stat"><div class="num"><?= $perangkatAktif ?></div><div class="lbl">Klien API aktif</div></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Kehadiran 7 hari terakhir</h2>
        <div class="bars">
            <?php foreach ($tujuh as $b): ?>
                <div class="bar-col" title="<?= e($b['label']) ?>: <?= $b['n'] ?> mahasiswa">
                    <span class="bar-val"><?= $b['n'] ?></span>
                    <div class="bar" style="height:<?= (int)round($b['n'] / $maks * 100) ?>%"></div>
                    <span class="bar-lbl"><?= e($b['label']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="card">
        <h2>Status layanan</h2>
        <table class="table">
            <tr>
                <td><strong>Server pengenalan wajah</strong><br><small><?= e($layanan['teks']) ?></small></td>
                <td class="actions"><span class="badge badge-<?= ['online' => 'green', 'peringatan' => 'yellow', 'offline' => 'red', 'belum' => 'gray'][$layanan['state']] ?>">
                    <?= ['online' => 'Online', 'peringatan' => 'Perhatian', 'offline' => 'Offline', 'belum' => 'Belum diatur'][$layanan['state']] ?></span></td>
            </tr>
            <?php foreach ($devices as $d):
                $online = $d['status_aktif'] && $d['last_seen'] && (time() - strtotime($d['last_seen']) <= 300); ?>
                <tr>
                    <td><strong><?= e($d['nama']) ?></strong><br><small>Klien API · <?= e($d['lokasi']) ?></small></td>
                    <td class="actions">
                        <span class="badge badge-<?= !$d['status_aktif'] ? 'gray' : ($online ? 'green' : 'yellow') ?>">
                            <?= !$d['status_aktif'] ? 'Dicabut' : ($online ? 'Online' : 'Offline') ?>
                        </span><br><small><?= e($d['last_seen'] ? waktu_id($d['last_seen']) : 'belum pernah') ?></small>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
        <p><a class="btn btn-sm btn-accent" href="<?= e(url('admin/kiosk.php')) ?>">Buka Presensi Kamera</a></p>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h2>10 presensi terbaru</h2>
        <a class="btn btn-sm" href="<?= e(url('admin/rekap.php')) ?>">Lihat semua rekap</a>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Waktu</th><th>NIM</th><th>Nama</th><th>Kelas</th><th>Sesi</th><th>Status</th><th>Confidence</th></tr></thead>
            <tbody>
            <?php if (!$terbaru): ?>
                <tr><td colspan="7" class="empty">Belum ada data presensi.</td></tr>
            <?php endif; ?>
            <?php foreach ($terbaru as $r): ?>
                <tr>
                    <td><?= e(waktu_id($r['waktu'])) ?></td>
                    <td><?= e($r['nim']) ?></td>
                    <td><?= e($r['nama']) ?></td>
                    <td><?= e($r['kelas']) ?></td>
                    <td><?= e($r['sesi']) ?></td>
                    <td><span class="badge badge-<?= $r['status'] === 'hadir' ? 'green' : 'yellow' ?>"><?= e($r['status']) ?></span></td>
                    <td><?= $r['confidence'] !== null ? e(number_format((float)$r['confidence'] * 100, 1)) . '%' : '-' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
