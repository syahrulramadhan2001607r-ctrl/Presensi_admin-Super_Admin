<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/presensi_service.php';
require_login(['admin', 'superadmin']);

$daftarSesi = q('SELECT DISTINCT sesi FROM presensi ORDER BY sesi')->fetchAll(PDO::FETCH_COLUMN);
$layanan    = layanan_status();

$pageTitle  = 'Presensi Kamera';
$activeMenu = 'kiosk';
$extraJs    = ['assets/js/camera.js', 'assets/js/kiosk.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-2 kiosk-grid">
    <div class="card">
        <div class="card-head">
            <h2>Kamera</h2>
            <span class="badge badge-<?= ['online' => 'green', 'peringatan' => 'yellow', 'offline' => 'red', 'belum' => 'gray'][$layanan['state']] ?>">
                Layanan pengenalan: <?= e($layanan['teks']) ?>
            </span>
        </div>

        <div class="cam-wrap" id="kiosk-wrap">
            <video id="kiosk-video" playsinline muted></video>
            <div class="cam-overlay" id="kiosk-overlay"></div>
        </div>

        <div class="cam-controls">
            <select class="input" id="kiosk-cam" style="display:none"></select>
            <button class="btn btn-primary" type="button" id="kiosk-start">Nyalakan kamera</button>
            <button class="btn btn-accent" type="button" id="kiosk-snap" disabled>Presensi sekarang</button>
        </div>

        <div class="form-row">
            <div>
                <label class="form-label" for="kiosk-sesi">Sesi</label>
                <input class="input" id="kiosk-sesi" list="daftar-sesi" value="Sesi 1" maxlength="50">
                <datalist id="daftar-sesi">
                    <?php foreach ($daftarSesi as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?>
                </datalist>
            </div>
            <div>
                <label class="checkbox" style="margin-top:2rem">
                    <input type="checkbox" id="kiosk-auto" disabled>
                    <span>Mode otomatis (ambil gambar tiap 3 detik)</span>
                </label>
            </div>
        </div>
        <p class="form-help">Hadapkan wajah ke kamera dengan pencahayaan cukup. Satu mahasiswa hanya tercatat sekali per sesi.</p>
    </div>

    <div class="card">
        <h2>Hasil</h2>
        <div class="kiosk-result idle" id="kiosk-result" aria-live="polite">
            <div class="kr-title">Menunggu</div>
            <div class="kr-sub">Nyalakan kamera untuk mulai.</div>
        </div>
        <h3 style="margin-top:1.2rem;font-size:.95rem">Riwayat sesi ini</h3>
        <ul class="kiosk-log" id="kiosk-log"><li class="muted">Belum ada.</li></ul>
    </div>
</div>

<script>
    window.KIOSK = {
        endpoint: <?= json_encode(url('api/kiosk.php')) ?>,
        csrf: <?= json_encode(csrf_token()) ?>
    };
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
