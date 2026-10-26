<?php
/**
 * Unduh dataset foto wajah (ZIP) untuk pelatihan model di server Python.
 * Struktur ZIP: NIM_Nama/foto.jpg  (satu folder per kelas/identitas, sesuai pipeline SRS).
 * Hanya Super Admin; hanya mahasiswa aktif dengan persetujuan wajah; setiap unduhan dicatat di audit log.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['superadmin']);

/** Daftar mahasiswa yang boleh masuk dataset beserta jumlah fotonya. */
function dataset_rows(): array
{
    return q('SELECT m.id, m.nim, m.nama, m.kelas, COUNT(d.id) AS jml
              FROM mahasiswa m JOIN data_wajah d ON d.mahasiswa_id = m.id
              WHERE m.status_aktif = 1 AND m.persetujuan_wajah = 1
              GROUP BY m.id, m.nim, m.nama, m.kelas ORDER BY m.nim')->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    if (!class_exists('ZipArchive')) {
        flash('danger', 'Ekstensi PHP "zip" belum aktif. Aktifkan extension=zip di php.ini XAMPP lalu restart Apache.');
        redirect('superadmin/dataset.php');
    }
    $rows = dataset_rows();
    if (!$rows) {
        flash('warning', 'Belum ada foto wajah dari mahasiswa aktif yang berpersetujuan.');
        redirect('superadmin/dataset.php');
    }

    $tmp = tempnam(sys_get_temp_dir(), 'ds');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        flash('danger', 'Gagal membuat berkas ZIP.');
        redirect('superadmin/dataset.php');
    }
    $jumlah = 0;
    foreach ($rows as $r) {
        $folder = preg_replace('/[^A-Za-z0-9._-]+/', '_', $r['nim'] . '_' . $r['nama']);
        $folder = trim($folder, '_');
        $fotos  = q('SELECT nama_file FROM data_wajah WHERE mahasiswa_id = ? ORDER BY id', [$r['id']])->fetchAll();
        foreach ($fotos as $f) {
            $path = UPLOAD_DIR . '/' . (int)$r['id'] . '/' . basename($f['nama_file']);
            if (is_file($path)) {
                $zip->addFile($path, $folder . '/' . basename($f['nama_file']));
                $jumlah++;
            }
        }
    }
    $zip->close();

    audit('dataset_unduh', count($rows) . ' mahasiswa, ' . $jumlah . ' foto');

    session_write_close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="dataset_wajah_' . date('Ymd_His') . '.zip"');
    header('Content-Length: ' . filesize($tmp));
    header('Cache-Control: no-store');
    readfile($tmp);
    @unlink($tmp);
    exit;
}

$rows  = dataset_rows();
$total = array_sum(array_column($rows, 'jml'));

$pageTitle  = 'Dataset Pelatihan';
$activeMenu = 's_dataset';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-head">
        <h2>Dataset foto wajah (<?= count($rows) ?> mahasiswa, <?= (int)$total ?> foto)</h2>
        <form method="post" data-confirm="Unduh dataset? Berkas berisi data biometrik, simpan dengan aman dan hapus setelah pelatihan selesai.">
            <?= csrf_field() ?>
            <button class="btn btn-primary" type="submit" <?= $rows ? '' : 'disabled' ?>>⬇ Unduh dataset (ZIP)</button>
        </form>
    </div>
    <div class="alert alert-warning">
        Berisi data biometrik. Gunakan hanya untuk pelatihan model di server terlindungi, jangan dibagikan,
        dan hapus salinan setelah dipakai. Setiap unduhan dicatat pada audit log.
    </div>
    <p class="muted">Format: satu folder per mahasiswa <code>NIM_Nama/</code> berisi foto JPG/PNG. Hanya mahasiswa aktif dengan persetujuan wajah yang ikut.
        Setelah ekstrak, lakukan deteksi/crop 160×160 dan pembagian latih/validasi/uji di skrip Python Anda.</p>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>NIM</th><th>Nama</th><th>Kelas</th><th>Jumlah foto</th><th>Catatan</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="5" class="empty">Belum ada data.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['nim']) ?></td><td><?= e($r['nama']) ?></td><td><?= e($r['kelas']) ?></td>
                    <td><?= (int)$r['jml'] ?></td>
                    <td><?= (int)$r['jml'] < 10 ? '<span class="badge badge-yellow">Foto sedikit (disarankan ≥ 10)</span>' : '<span class="badge badge-green">Cukup</span>' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
