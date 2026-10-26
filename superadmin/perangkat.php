<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['superadmin']);

$tokenBaru = null; // ditampilkan sekali saja
$tokenUntuk = '';

/** Buat token acak 64 hex; yang disimpan hanya hash SHA-256-nya. */
function buat_token(): array
{
    $token = bin2hex(random_bytes(32));
    return [$token, hash('sha256', $token)];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = (string)($_POST['aksi'] ?? '');
    $id   = (int)($_POST['id'] ?? 0);

    if ($aksi === 'tambah') {
        $nama   = trim((string)($_POST['nama'] ?? ''));
        $lokasi = trim((string)($_POST['lokasi'] ?? ''));
        $devId  = trim((string)($_POST['device_id'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9_-]{3,50}$/', $devId)) {
            flash('danger', 'Device ID 3-50 karakter (huruf, angka, minus, garis bawah).');
        } elseif ($nama === '' || mb_strlen($nama) > 100 || mb_strlen($lokasi) > 150) {
            flash('danger', 'Nama wajib diisi (maks 100) dan lokasi maksimal 150 karakter.');
        } elseif (q('SELECT id FROM perangkat WHERE device_id = ?', [$devId])->fetch()) {
            flash('danger', 'Device ID sudah terdaftar.');
        } else {
            [$token, $hash] = buat_token();
            q('INSERT INTO perangkat (device_id, nama, lokasi, token_hash) VALUES (?,?,?,?)', [$devId, $nama, $lokasi, $hash]);
            audit('perangkat_tambah', $devId);
            $tokenBaru  = $token;
            $tokenUntuk = $devId;
        }
    } else {
        $p = q('SELECT * FROM perangkat WHERE id = ?', [$id])->fetch();
        if (!$p) {
            flash('danger', 'Perangkat tidak ditemukan.');
        } elseif ($aksi === 'toggle') {
            $baru = $p['status_aktif'] ? 0 : 1;
            q('UPDATE perangkat SET status_aktif = ? WHERE id = ?', [$baru, $id]);
            audit($baru ? 'perangkat_aktifkan' : 'perangkat_cabut_akses', $p['device_id']);
            flash('success', 'Perangkat ' . $p['device_id'] . ($baru ? ' diaktifkan.' : ' dinonaktifkan (akses dicabut).'));
        } elseif ($aksi === 'token') {
            [$token, $hash] = buat_token();
            q('UPDATE perangkat SET token_hash = ? WHERE id = ?', [$hash, $id]);
            audit('perangkat_token_baru', $p['device_id']);
            $tokenBaru  = $token;
            $tokenUntuk = $p['device_id'];
        } elseif ($aksi === 'hapus') {
            q('DELETE FROM perangkat WHERE id = ?', [$id]);
            audit('perangkat_hapus', $p['device_id']);
            flash('success', 'Perangkat ' . $p['device_id'] . ' dihapus.');
        }
    }
    if ($tokenBaru === null) {
        redirect('superadmin/perangkat.php');
    }
}

$devices = q('SELECT * FROM perangkat ORDER BY created_at DESC')->fetchAll();

$pageTitle  = 'Kamera & Token API';
$activeMenu = 's_device';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($tokenBaru !== null): ?>
<div class="alert alert-warning">
    <strong>Token untuk perangkat <?= e($tokenUntuk) ?></strong> — salin sekarang. Token hanya ditampilkan satu kali dan tidak dapat dilihat lagi.
    <div class="token-box" id="tok"><?= e($tokenBaru) ?></div>
    Pakai pada header <code>Authorization: Bearer &lt;token&gt;</code> pada klien eksternal (opsional).
</div>
<?php endif; ?>

<?php
$skema    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$apiBase  = $skema . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url('api');
?>
<div class="alert alert-info">
    <strong>Klien API eksternal (opsional)</strong> — presensi lewat webcam di browser tidak memerlukan token; token hanya untuk skrip/aplikasi lain yang mengirim hasil pengenalan ke panel ini.<br>
    Catat presensi: <code>POST <?= e($apiBase) ?>/presensi.php</code> &nbsp;·&nbsp;
    Ambil konfigurasi: <code>GET <?= e($apiBase) ?>/config.php</code><br>
    Header <code>Authorization: Bearer &lt;token&gt;</code>, body JSON
    <code>{"nim":"2021001","confidence":0.94,"sesi":"Sesi 1"}</code>. Contoh lengkap ada di README.
</div>

<div class="card">
    <div class="card-head"><h2>Kamera / klien terdaftar (<?= count($devices) ?>)</h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Device ID</th><th>Nama</th><th>Lokasi</th><th>Status</th><th>Terakhir terlihat</th><th></th></tr></thead>
            <tbody>
            <?php if (!$devices): ?><tr><td colspan="6" class="empty">Belum ada perangkat.</td></tr><?php endif; ?>
            <?php foreach ($devices as $d): ?>
                <tr>
                    <td><code><?= e($d['device_id']) ?></code></td>
                    <td><?= e($d['nama']) ?></td>
                    <td><?= e($d['lokasi']) ?></td>
                    <td><span class="badge badge-<?= $d['status_aktif'] ? 'green' : 'gray' ?>"><?= $d['status_aktif'] ? 'Aktif' : 'Dicabut' ?></span></td>
                    <td><?= e(waktu_id($d['last_seen'])) ?></td>
                    <td class="actions">
                        <form method="post" class="inline" data-confirm="Buat token baru? Token lama langsung tidak berlaku.">
                            <?= csrf_field() ?><input type="hidden" name="aksi" value="token"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                            <button class="btn btn-sm" type="submit">Token baru</button>
                        </form>
                        <form method="post" class="inline">
                            <?= csrf_field() ?><input type="hidden" name="aksi" value="toggle"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                            <button class="btn btn-sm" type="submit"><?= $d['status_aktif'] ? 'Cabut akses' : 'Aktifkan' ?></button>
                        </form>
                        <form method="post" class="inline" data-confirm="Hapus perangkat <?= e($d['device_id']) ?>?">
                            <?= csrf_field() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card" style="max-width:720px">
    <h2>Daftarkan kamera / klien baru</h2>
    <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="tambah">
        <div class="form-row">
            <div><label class="form-label">Device ID</label>
                <input class="input" name="device_id" maxlength="50" placeholder="contoh: kamera-ruang-a" required></div>
            <div><label class="form-label">Nama perangkat</label>
                <input class="input" name="nama" maxlength="100" placeholder="Kamera Ruang A" required></div>
        </div>
        <label class="form-label">Lokasi</label>
        <input class="input" name="lokasi" maxlength="150" placeholder="Gedung A, lantai 1">
        <div class="form-actions"><button class="btn btn-primary" type="submit">Daftarkan &amp; buat token</button></div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
