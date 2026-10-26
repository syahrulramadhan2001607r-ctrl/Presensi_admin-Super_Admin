<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin', 'superadmin']);

$id  = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$mhs = q('SELECT * FROM mahasiswa WHERE id = ?', [$id])->fetch();
if (!$mhs) {
    flash('danger', 'Data mahasiswa tidak ditemukan.');
    redirect('admin/mahasiswa.php');
}

$dir = UPLOAD_DIR . '/' . $id;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = (string)($_POST['aksi'] ?? '');

    // ---------- Unggah foto (FR-03, FR-04) ----------
    if ($aksi === 'upload') {
        if (!$mhs['persetujuan_wajah']) {
            flash('danger', 'Persetujuan pemrosesan data wajah belum dicatat. Centang persetujuan pada form ubah mahasiswa terlebih dahulu.');
            redirect('admin/wajah.php?id=' . $id);
        }
        if (empty($_FILES['foto']['name'][0])) {
            flash('warning', 'Pilih minimal satu foto.');
            redirect('admin/wajah.php?id=' . $id);
        }

        $jumlahAda = (int)q('SELECT COUNT(*) FROM data_wajah WHERE mahasiswa_id = ?', [$id])->fetchColumn();
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            flash('danger', 'Folder penyimpanan tidak dapat dibuat. Periksa izin folder uploads.');
            redirect('admin/wajah.php?id=' . $id);
        }

        $finfo   = new finfo(FILEINFO_MIME_TYPE);
        $ok      = 0;
        $ditolak = [];
        $names   = $_FILES['foto']['name'];

        foreach ($names as $i => $orig) {
            $err = $_FILES['foto']['error'][$i];
            $tmp = $_FILES['foto']['tmp_name'][$i];
            $size = (int)$_FILES['foto']['size'][$i];
            $label = mb_substr(basename((string)$orig), 0, 40);

            if ($err !== UPLOAD_ERR_OK) {
                $ditolak[] = "$label: gagal diunggah (kode $err)";
                continue;
            }
            if ($jumlahAda + $ok >= UPLOAD_MAX_PER_MHS) {
                $ditolak[] = "$label: batas " . UPLOAD_MAX_PER_MHS . ' foto per mahasiswa tercapai';
                continue;
            }
            if ($size <= 0 || $size > UPLOAD_MAX_BYTES) {
                $ditolak[] = "$label: ukuran harus 1 byte - " . (UPLOAD_MAX_BYTES / 1048576) . ' MB';
                continue;
            }
            $mime = $finfo->file($tmp);
            $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png'][$mime] ?? null;
            if (!$ext) {
                $ditolak[] = "$label: format harus JPG atau PNG";
                continue;
            }
            $info = @getimagesize($tmp);
            if (!$info) {
                $ditolak[] = "$label: berkas bukan citra yang valid";
                continue;
            }
            if (min($info[0], $info[1]) < UPLOAD_MIN_DIMENSION) {
                $ditolak[] = "$label: resolusi terlalu kecil (minimal " . UPLOAD_MIN_DIMENSION . ' px pada sisi terpendek)';
                continue;
            }

            $nama = bin2hex(random_bytes(12)) . '.' . $ext;
            if (!move_uploaded_file($tmp, $dir . '/' . $nama)) {
                $ditolak[] = "$label: gagal disimpan";
                continue;
            }
            q('INSERT INTO data_wajah (mahasiswa_id, nama_file, ukuran, created_by) VALUES (?,?,?,?)',
                [$id, $nama, $size, current_user()['id']]);
            $ok++;
        }

        if ($ok > 0) {
            audit('wajah_registrasi', $mhs['nim'] . ' +' . $ok . ' foto');
            flash('success', "$ok foto berhasil disimpan.");
        }
        if ($ditolak) {
            flash('warning', 'Sebagian foto ditolak: ' . implode('; ', $ditolak));
        }
        redirect('admin/wajah.php?id=' . $id);
    }

    // ---------- Hapus satu foto ----------
    if ($aksi === 'hapus_foto') {
        $fid = (int)($_POST['foto_id'] ?? 0);
        $f   = q('SELECT * FROM data_wajah WHERE id = ? AND mahasiswa_id = ?', [$fid, $id])->fetch();
        if ($f) {
            @unlink($dir . '/' . basename($f['nama_file']));
            q('DELETE FROM data_wajah WHERE id = ?', [$fid]);
            audit('wajah_hapus_foto', $mhs['nim'] . ' foto#' . $fid);
            flash('success', 'Foto dihapus.');
        }
        redirect('admin/wajah.php?id=' . $id);
    }

    // ---------- Hapus seluruh data wajah (FR-21) ----------
    if ($aksi === 'hapus_semua') {
        $fotos = q('SELECT nama_file FROM data_wajah WHERE mahasiswa_id = ?', [$id])->fetchAll();
        foreach ($fotos as $f) {
            @unlink($dir . '/' . basename($f['nama_file']));
        }
        @rmdir($dir);
        q('DELETE FROM data_wajah WHERE mahasiswa_id = ?', [$id]);
        audit('wajah_hapus_semua', $mhs['nim'] . ' (' . count($fotos) . ' foto)');
        flash('success', 'Seluruh data wajah ' . $mhs['nama'] . ' telah dihapus.');
        redirect('admin/wajah.php?id=' . $id);
    }
}

$fotos = q('SELECT * FROM data_wajah WHERE mahasiswa_id = ? ORDER BY id DESC', [$id])->fetchAll();

$pageTitle  = 'Registrasi Wajah';
$extraJs    = ['assets/js/camera.js', 'assets/js/wajah_cam.js'];
$activeMenu = 'mahasiswa';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-head">
        <div>
            <h2><?= e($mhs['nama']) ?> <small>(<?= e($mhs['nim']) ?> · <?= e($mhs['kelas']) ?>)</small></h2>
            <span class="badge badge-<?= $mhs['persetujuan_wajah'] ? 'green' : 'red' ?>">
                Persetujuan wajah: <?= $mhs['persetujuan_wajah'] ? 'ada' : 'belum ada' ?>
            </span>
            <span class="badge badge-blue"><?= count($fotos) ?> / <?= UPLOAD_MAX_PER_MHS ?> foto</span>
        </div>
        <a class="btn" href="<?= e(url('admin/mahasiswa.php')) ?>">← Kembali</a>
    </div>

    <?php if (!$mhs['persetujuan_wajah']): ?>
        <div class="alert alert-warning">
            Unggah foto dinonaktifkan sampai persetujuan pemrosesan data wajah dicatat.
            <a href="<?= e(url('admin/mahasiswa_form.php?id=' . $id)) ?>">Ubah data mahasiswa</a>.
        </div>
    <?php else: ?>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="upload">
            <input type="hidden" name="id" value="<?= $id ?>">
            <label class="form-label" for="foto">Unggah foto wajah (bisa lebih dari satu)</label>
            <input class="input" type="file" id="foto" name="foto[]" accept="image/jpeg,image/png" multiple
                   data-preview="foto-info" required>
            <div class="form-help">
                JPG/PNG, maksimal <?= UPLOAD_MAX_BYTES / 1048576 ?> MB per foto, sisi terpendek minimal <?= UPLOAD_MIN_DIMENSION ?> px.
                Gunakan foto dengan variasi pencahayaan dan sudut. <span id="foto-info"></span>
            </div>
            <div class="form-actions"><button class="btn btn-primary" type="submit">Unggah foto</button></div>
        </form>

        <div class="cam-box">
            <h3>Atau ambil langsung dari kamera</h3>
            <div class="cam-wrap"><video id="cam-video" playsinline muted></video></div>
            <div class="cam-controls">
                <select class="input" id="cam-select" style="display:none"></select>
                <button class="btn" type="button" id="cam-start">Nyalakan kamera</button>
                <button class="btn btn-accent" type="button" id="cam-snap" disabled>📷 Ambil foto</button>
                <button class="btn btn-primary" type="button" id="cam-save" disabled>Simpan foto</button>
            </div>
            <div class="form-help" id="cam-msg">Ambil 10-20 foto dengan variasi sudut, jarak, dan pencahayaan. Foto yang sudah diambil muncul di bawah; hapus yang buram sebelum disimpan.</div>
            <div class="gallery" id="cam-thumbs" style="margin-top:.8rem"></div>
        </div>
        <script>
            window.WAJAH = {
                csrf: <?= json_encode(csrf_token()) ?>,
                id: <?= (int)$id ?>,
                sisa: <?= max(0, UPLOAD_MAX_PER_MHS - count($fotos)) ?>
            };
        </script>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head">
        <h2>Foto terdaftar</h2>
        <?php if ($fotos): ?>
        <form method="post" data-confirm="Hapus SEMUA data wajah <?= e($mhs['nama']) ?>? Tindakan ini tidak dapat dibatalkan.">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="hapus_semua">
            <input type="hidden" name="id" value="<?= $id ?>">
            <button class="btn btn-danger btn-sm" type="submit">Hapus semua data wajah</button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (!$fotos): ?>
        <p class="empty">Belum ada foto wajah terdaftar.</p>
    <?php else: ?>
    <div class="gallery">
        <?php foreach ($fotos as $f): ?>
        <figure>
            <img src="<?= e(url('admin/foto.php?id=' . $f['id'])) ?>" alt="Foto wajah" loading="lazy">
            <figcaption>
                <span><?= e(tgl_id($f['created_at'])) ?></span>
                <form method="post" class="inline" data-confirm="Hapus foto ini?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="aksi" value="hapus_foto">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <input type="hidden" name="foto_id" value="<?= (int)$f['id'] ?>">
                    <button class="btn btn-sm btn-danger" type="submit">Hapus</button>
                </form>
            </figcaption>
        </figure>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<div class="alert alert-info">
    Catatan: pembaruan model CNN (pelatihan ulang) dilakukan di server Python sesuai SRS. Panel ini hanya mengelola
    foto registrasi dan metadatanya. Folder dataset dapat diambil dari <code>uploads/wajah/{id_mahasiswa}/</code>.
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
