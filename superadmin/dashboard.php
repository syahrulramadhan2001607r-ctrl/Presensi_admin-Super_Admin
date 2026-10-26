<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['superadmin']);

$pageTitle  = 'Dashboard Super Admin';
$activeMenu = 's_dashboard';

$jmlAdmin  = (int)q("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status_aktif = 1")->fetchColumn();
$jmlSuper  = (int)q("SELECT COUNT(*) FROM users WHERE role = 'superadmin' AND status_aktif = 1")->fetchColumn();
$jmlDevice = (int)q('SELECT COUNT(*) FROM perangkat WHERE status_aktif = 1')->fetchColumn();
$gagal24   = (int)q("SELECT COUNT(*) FROM audit_log WHERE aksi IN ('login_gagal','login_diblokir') AND waktu >= (NOW() - INTERVAL 1 DAY)")->fetchColumn();

$log = q('SELECT * FROM audit_log ORDER BY id DESC LIMIT 10')->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-4" style="margin-bottom:1.25rem">
    <div class="stat"><div class="num"><?= $jmlAdmin ?></div><div class="lbl">Admin aktif</div></div>
    <div class="stat"><div class="num"><?= $jmlSuper ?></div><div class="lbl">Super Admin aktif</div></div>
    <div class="stat"><div class="num"><?= $jmlDevice ?></div><div class="lbl">Perangkat aktif</div></div>
    <div class="stat"><div class="num"><?= $gagal24 ?></div><div class="lbl">Login gagal / diblokir (24 jam)</div></div>
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>Konfigurasi saat ini</h2>
        <table class="table">
            <tr><td>Threshold confidence</td><td><strong><?= e(get_config('threshold', '0.90')) ?></strong></td></tr>
            <tr><td>Zona waktu</td><td><?= e(get_config('zona_waktu', APP_TIMEZONE)) ?></td></tr>
            <tr><td>Aturan duplikasi</td><td><?= e(get_config('aturan_duplikasi', 'satu_per_sesi')) ?></td></tr>
            <tr><td>Versi model</td><td><?= e(get_config('model_version', '-')) ?></td></tr>
            <tr><td>Versi konfigurasi</td><td>v<?= e(get_config('versi_konfigurasi', '1')) ?></td></tr>
        </table>
        <p><a class="btn btn-sm" href="<?= e(url('superadmin/konfigurasi.php')) ?>">Ubah konfigurasi</a></p>
    </div>

    <div class="card">
        <h2>Menu cepat</h2>
        <p><a class="btn" href="<?= e(url('superadmin/users.php')) ?>">Kelola akun Admin</a></p>
        <p><a class="btn" href="<?= e(url('superadmin/perangkat.php')) ?>">Kelola kamera &amp; token API</a></p>
        <p><a class="btn" href="<?= e(url('superadmin/audit.php')) ?>">Lihat audit log lengkap</a></p>
        <p><a class="btn" href="<?= e(url('admin/dashboard.php')) ?>">Buka fitur Admin</a></p>
    </div>
</div>

<div class="card">
    <h2>Aktivitas terbaru</h2>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Waktu</th><th>Pelaku</th><th>Aksi</th><th>Target</th><th>Hasil</th></tr></thead>
            <tbody>
            <?php if (!$log): ?><tr><td colspan="5" class="empty">Belum ada aktivitas.</td></tr><?php endif; ?>
            <?php foreach ($log as $l): ?>
                <tr>
                    <td><?= e(waktu_id($l['waktu'])) ?></td>
                    <td><?= e($l['actor_username'] ?? '-') ?></td>
                    <td><?= e($l['aksi']) ?></td>
                    <td><?= e($l['target']) ?></td>
                    <td><span class="badge badge-<?= $l['hasil'] === 'berhasil' ? 'green' : 'red' ?>"><?= e($l['hasil']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
