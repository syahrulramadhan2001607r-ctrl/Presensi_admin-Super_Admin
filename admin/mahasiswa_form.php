<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin', 'superadmin']);

$id   = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$data = ['nim' => '', 'nama' => '', 'kelas' => '', 'status_aktif' => 1, 'persetujuan_wajah' => 0];

if ($id > 0) {
    $row = q('SELECT * FROM mahasiswa WHERE id = ?', [$id])->fetch();
    if (!$row) {
        flash('danger', 'Data mahasiswa tidak ditemukan.');
        redirect('admin/mahasiswa.php');
    }
    $data = $row;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $data['nim']               = trim((string)($_POST['nim'] ?? ''));
    $data['nama']              = trim((string)($_POST['nama'] ?? ''));
    $data['kelas']             = trim((string)($_POST['kelas'] ?? ''));
    $data['status_aktif']      = isset($_POST['status_aktif']) ? 1 : 0;
    $data['persetujuan_wajah'] = isset($_POST['persetujuan_wajah']) ? 1 : 0;

    if ($data['nim'] === '' || !preg_match('/^[A-Za-z0-9._-]{3,30}$/', $data['nim'])) {
        $errors[] = 'NIM wajib diisi (3-30 karakter: huruf, angka, titik, minus, garis bawah).';
    }
    if ($data['nama'] === '' || mb_strlen($data['nama']) > 120) {
        $errors[] = 'Nama wajib diisi (maksimal 120 karakter).';
    }
    if (mb_strlen($data['kelas']) > 50) {
        $errors[] = 'Kelas maksimal 50 karakter.';
    }
    $dup = q('SELECT id FROM mahasiswa WHERE nim = ? AND id <> ?', [$data['nim'], $id])->fetch();
    if ($dup) {
        $errors[] = 'NIM sudah terdaftar.';
    }

    if (!$errors) {
        if ($id > 0) {
            q('UPDATE mahasiswa SET nim=?, nama=?, kelas=?, status_aktif=?, persetujuan_wajah=? WHERE id=?',
                [$data['nim'], $data['nama'], $data['kelas'], $data['status_aktif'], $data['persetujuan_wajah'], $id]);
            audit('mahasiswa_ubah', $data['nim'] . ' ' . $data['nama']);
            flash('success', 'Data mahasiswa berhasil diperbarui.');
        } else {
            q('INSERT INTO mahasiswa (nim, nama, kelas, status_aktif, persetujuan_wajah) VALUES (?,?,?,?,?)',
                [$data['nim'], $data['nama'], $data['kelas'], $data['status_aktif'], $data['persetujuan_wajah']]);
            audit('mahasiswa_tambah', $data['nim'] . ' ' . $data['nama']);
            flash('success', 'Mahasiswa baru berhasil ditambahkan.');
        }
        redirect('admin/mahasiswa.php');
    }
}

$pageTitle  = $id > 0 ? 'Ubah Mahasiswa' : 'Tambah Mahasiswa';
$activeMenu = 'mahasiswa';
require __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:720px">
    <h2><?= e($pageTitle) ?></h2>

    <?php foreach ($errors as $err): ?>
        <div class="alert alert-danger"><?= e($err) ?></div>
    <?php endforeach; ?>

    <form method="post" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$id ?>">

        <div class="form-row">
            <div>
                <label class="form-label" for="nim">NIM</label>
                <input class="input" id="nim" name="nim" value="<?= e($data['nim']) ?>" maxlength="30" required>
            </div>
            <div>
                <label class="form-label" for="kelas">Kelas</label>
                <input class="input" id="kelas" name="kelas" value="<?= e($data['kelas']) ?>" maxlength="50" placeholder="contoh: TI-A">
            </div>
        </div>

        <label class="form-label" for="nama">Nama lengkap</label>
        <input class="input" id="nama" name="nama" value="<?= e($data['nama']) ?>" maxlength="120" required>

        <label class="checkbox">
            <input type="checkbox" name="status_aktif" value="1" <?= $data['status_aktif'] ? 'checked' : '' ?>>
            <span>Mahasiswa aktif (hanya mahasiswa aktif yang dapat dikenali saat presensi)</span>
        </label>
        <label class="checkbox">
            <input type="checkbox" name="persetujuan_wajah" value="1" <?= $data['persetujuan_wajah'] ? 'checked' : '' ?>>
            <span>Mahasiswa telah memberikan persetujuan pemrosesan data wajah (wajib sebelum foto wajah dapat diunggah)</span>
        </label>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Simpan</button>
            <a class="btn" href="<?= e(url('admin/mahasiswa.php')) ?>">Batal</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
