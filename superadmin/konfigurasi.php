<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['superadmin']);

$errors = [];
$cfg = [
    'threshold'        => get_config('threshold', '0.90'),
    'zona_waktu'       => get_config('zona_waktu', APP_TIMEZONE),
    'aturan_duplikasi' => get_config('aturan_duplikasi', 'satu_per_sesi'),
    'model_version'    => get_config('model_version', ''),
];
$zonaPilihan = ['Asia/Jakarta' => 'WIB (Asia/Jakarta)', 'Asia/Makassar' => 'WITA (Asia/Makassar)', 'Asia/Jayapura' => 'WIT (Asia/Jayapura)'];
$aturanPilihan = ['satu_per_sesi' => 'Satu catatan per mahasiswa per sesi (sesuai BR-04)'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $baru = [
        'threshold'        => str_replace(',', '.', trim((string)($_POST['threshold'] ?? ''))),
        'zona_waktu'       => (string)($_POST['zona_waktu'] ?? ''),
        'aturan_duplikasi' => (string)($_POST['aturan_duplikasi'] ?? ''),
        'model_version'    => trim((string)($_POST['model_version'] ?? '')),
    ];
    $catatan = trim((string)($_POST['catatan'] ?? ''));

    if (!is_numeric($baru['threshold']) || (float)$baru['threshold'] < 0.5 || (float)$baru['threshold'] > 0.99) {
        $errors[] = 'Threshold harus berupa angka antara 0,50 dan 0,99.';
    } else {
        $baru['threshold'] = number_format((float)$baru['threshold'], 2, '.', '');
    }
    if (!isset($zonaPilihan[$baru['zona_waktu']])) {
        $errors[] = 'Zona waktu tidak valid.';
    }
    if (!isset($aturanPilihan[$baru['aturan_duplikasi']])) {
        $errors[] = 'Aturan duplikasi tidak valid.';
    }
    if ($baru['model_version'] === '' || mb_strlen($baru['model_version']) > 50) {
        $errors[] = 'Versi model wajib diisi (maksimal 50 karakter).';
    }
    if ($catatan === '' || mb_strlen($catatan) > 150) {
        $errors[] = 'Catatan perubahan wajib diisi (maksimal 150 karakter), mis. rujukan hasil evaluasi FAR/FRR.';
    }

    if (!$errors) {
        $perubahan = [];
        foreach ($baru as $k => $v) {
            if ($v !== $cfg[$k]) {
                $perubahan[] = "$k: {$cfg[$k]} → $v";
                set_config($k, $v);
            }
        }
        if ($perubahan) {
            $ver = (int)get_config('versi_konfigurasi', '1') + 1;
            set_config('versi_konfigurasi', (string)$ver);
            audit('konfigurasi_ubah', 'v' . $ver . ' | ' . implode('; ', $perubahan) . ' | ' . $catatan);
            flash('success', 'Konfigurasi disimpan sebagai versi v' . $ver . '.');
        } else {
            flash('info', 'Tidak ada perubahan.');
        }
        redirect('superadmin/konfigurasi.php');
    }
    $cfg = array_merge($cfg, $baru);
}

$pageTitle  = 'Konfigurasi Sistem';
$activeMenu = 's_config';
require __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:720px">
    <h2>Konfigurasi pengenalan wajah</h2>
    <p class="muted">Versi konfigurasi saat ini: <strong>v<?= e(get_config('versi_konfigurasi', '1')) ?></strong>.
        Setiap perubahan dicatat pada audit log (FR-19, BR-08).</p>

    <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

    <form method="post" novalidate>
        <?= csrf_field() ?>

        <label class="form-label" for="threshold">Threshold confidence (0,50 – 0,99)</label>
        <input class="input" id="threshold" name="threshold" value="<?= e($cfg['threshold']) ?>" inputmode="decimal" required>
        <div class="form-help">Prediksi diterima hanya jika confidence ≥ nilai ini. Tetapkan dari hasil evaluasi FAR/FRR pada dataset aktual; 0,90 hanyalah nilai awal.</div>

        <label class="form-label" for="zona_waktu">Zona waktu institusi</label>
        <select class="input" id="zona_waktu" name="zona_waktu">
            <?php foreach ($zonaPilihan as $k => $v): ?>
                <option value="<?= e($k) ?>" <?= $k === $cfg['zona_waktu'] ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>

        <label class="form-label" for="aturan_duplikasi">Aturan presensi ganda</label>
        <select class="input" id="aturan_duplikasi" name="aturan_duplikasi">
            <?php foreach ($aturanPilihan as $k => $v): ?>
                <option value="<?= e($k) ?>" <?= $k === $cfg['aturan_duplikasi'] ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>

        <label class="form-label" for="model_version">Versi model aktif</label>
        <input class="input" id="model_version" name="model_version" value="<?= e($cfg['model_version']) ?>" maxlength="50" required>

        <label class="form-label" for="catatan">Catatan perubahan</label>
        <input class="input" id="catatan" name="catatan" maxlength="150" placeholder="contoh: threshold naik ke 0,92 berdasarkan uji FAR 2026-10" required>

        <div class="form-actions"><button class="btn btn-primary" type="submit">Simpan konfigurasi</button></div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
