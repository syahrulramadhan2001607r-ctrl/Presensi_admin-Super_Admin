<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin', 'superadmin']);

/** Hapus seluruh berkas foto wajah milik mahasiswa dari disk. */
function hapus_folder_wajah(int $mhsId): void
{
    $dir = UPLOAD_DIR . '/' . $mhsId;
    if (is_dir($dir)) {
        foreach (glob($dir . '/*') ?: [] as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        @rmdir($dir);
    }
}

// ---------- Aksi POST: aktif/nonaktif & hapus ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = (string)($_POST['aksi'] ?? '');
    $id   = (int)($_POST['id'] ?? 0);
    $mhs  = q('SELECT * FROM mahasiswa WHERE id = ?', [$id])->fetch();

    if (!$mhs) {
        flash('danger', 'Data mahasiswa tidak ditemukan.');
    } elseif ($aksi === 'toggle') {
        $baru = $mhs['status_aktif'] ? 0 : 1;
        q('UPDATE mahasiswa SET status_aktif = ? WHERE id = ?', [$baru, $id]);
        audit($baru ? 'mahasiswa_aktifkan' : 'mahasiswa_nonaktifkan', $mhs['nim'] . ' ' . $mhs['nama']);
        flash('success', 'Status ' . $mhs['nama'] . ' diubah menjadi ' . ($baru ? 'aktif' : 'nonaktif') . '.');
    } elseif ($aksi === 'hapus') {
        // BR-07: data wajah ikut dihapus bersama data mahasiswa
        hapus_folder_wajah($id);
        q('DELETE FROM mahasiswa WHERE id = ?', [$id]);
        audit('mahasiswa_hapus', $mhs['nim'] . ' ' . $mhs['nama']);
        flash('success', 'Mahasiswa ' . $mhs['nama'] . ' beserta data wajah dan riwayat presensinya telah dihapus.');
    }
    redirect('admin/mahasiswa.php?' . http_build_query(array_filter([
        'cari' => $_POST['cari'] ?? '', 'kelas' => $_POST['kelas'] ?? '', 'page' => $_POST['page'] ?? '',
    ])));
}

// ---------- Filter & data ----------
$cari  = trim((string)($_GET['cari'] ?? ''));
$kelas = trim((string)($_GET['kelas'] ?? ''));

$where  = ['1=1'];
$params = [];
if ($cari !== '') {
    $where[]  = '(m.nim LIKE ? OR m.nama LIKE ?)';
    $params[] = "%$cari%";
    $params[] = "%$cari%";
}
if ($kelas !== '') {
    $where[]  = 'm.kelas = ?';
    $params[] = $kelas;
}
$w = implode(' AND ', $where);

$total = (int)q("SELECT COUNT(*) FROM mahasiswa m WHERE $w", $params)->fetchColumn();
$pg    = paginate($total, 15);

$rows = q("SELECT m.*, (SELECT COUNT(*) FROM data_wajah d WHERE d.mahasiswa_id = m.id) AS jml_foto
           FROM mahasiswa m WHERE $w ORDER BY m.nama ASC
           LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params)->fetchAll();

$daftarKelas = q("SELECT DISTINCT kelas FROM mahasiswa WHERE kelas <> '' ORDER BY kelas")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle  = 'Data Mahasiswa';
$activeMenu = 'mahasiswa';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-head">
        <h2>Daftar mahasiswa (<?= $total ?>)</h2>
        <a class="btn btn-primary" href="<?= e(url('admin/mahasiswa_form.php')) ?>">+ Tambah mahasiswa</a>
    </div>

    <form method="get" class="filters">
        <div class="f"><label>Cari NIM / nama</label>
            <input class="input" type="text" name="cari" value="<?= e($cari) ?>" placeholder="Ketik kata kunci"></div>
        <div class="f"><label>Kelas</label>
            <select class="input" name="kelas">
                <option value="">Semua kelas</option>
                <?php foreach ($daftarKelas as $k): ?>
                    <option value="<?= e($k) ?>" <?= $k === $kelas ? 'selected' : '' ?>><?= e($k) ?></option>
                <?php endforeach; ?>
            </select></div>
        <button class="btn btn-primary" type="submit">Terapkan</button>
        <a class="btn" href="<?= e(url('admin/mahasiswa.php')) ?>">Reset</a>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>NIM</th><th>Nama</th><th>Kelas</th><th>Status</th><th>Persetujuan wajah</th><th>Foto wajah</th><th></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="7" class="empty">Tidak ada data mahasiswa.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['nim']) ?></td>
                    <td><?= e($r['nama']) ?></td>
                    <td><?= e($r['kelas']) ?></td>
                    <td><span class="badge badge-<?= $r['status_aktif'] ? 'green' : 'gray' ?>"><?= $r['status_aktif'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                    <td><span class="badge badge-<?= $r['persetujuan_wajah'] ? 'green' : 'red' ?>"><?= $r['persetujuan_wajah'] ? 'Ada' : 'Belum' ?></span></td>
                    <td><?= (int)$r['jml_foto'] ?> foto</td>
                    <td class="actions">
                        <a class="btn btn-sm" href="<?= e(url('admin/wajah.php?id=' . $r['id'])) ?>">Wajah</a>
                        <a class="btn btn-sm" href="<?= e(url('admin/mahasiswa_form.php?id=' . $r['id'])) ?>">Ubah</a>
                        <form method="post" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="toggle">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="cari" value="<?= e($cari) ?>">
                            <input type="hidden" name="kelas" value="<?= e($kelas) ?>">
                            <input type="hidden" name="page" value="<?= (int)$pg['page'] ?>">
                            <button class="btn btn-sm" type="submit"><?= $r['status_aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                        </form>
                        <form method="post" class="inline"
                              data-confirm="Hapus <?= e($r['nama']) ?>? Foto wajah dan seluruh riwayat presensinya ikut terhapus dan tidak dapat dikembalikan.">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <input type="hidden" name="cari" value="<?= e($cari) ?>">
                            <input type="hidden" name="kelas" value="<?= e($kelas) ?>">
                            <button class="btn btn-sm btn-danger" type="submit">Hapus</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= pagination_html($pg) ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
