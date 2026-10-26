<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/rekap_helper.php';
require_login(['admin', 'superadmin']);

// ---------- Aksi POST: presensi manual & koreksi (hapus) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi  = (string)($_POST['aksi'] ?? '');
    $balik = 'admin/rekap.php?' . http_build_query(array_filter([
        'dari' => $_POST['f_dari'] ?? '', 'sampai' => $_POST['f_sampai'] ?? '',
        'kelas' => $_POST['f_kelas'] ?? '', 'cari' => $_POST['f_cari'] ?? '', 'sesi' => $_POST['f_sesi'] ?? '',
    ]));

    if ($aksi === 'manual') {
        $mid     = (int)($_POST['mahasiswa_id'] ?? 0);
        $tanggal = (string)($_POST['tanggal'] ?? '');
        $sesi    = trim((string)($_POST['sesi_manual'] ?? ''));
        $ket     = trim((string)($_POST['keterangan'] ?? ''));
        $mhs     = q('SELECT * FROM mahasiswa WHERE id = ? AND status_aktif = 1', [$mid])->fetch();
        $dt      = DateTime::createFromFormat('Y-m-d', $tanggal);

        if (!$mhs) {
            flash('danger', 'Pilih mahasiswa aktif.');
        } elseif (!$dt || $dt->format('Y-m-d') !== $tanggal) {
            flash('danger', 'Tanggal tidak valid.');
        } elseif ($sesi === '' || mb_strlen($sesi) > 50) {
            flash('danger', 'Sesi wajib diisi (maksimal 50 karakter).');
        } elseif ($ket === '' || mb_strlen($ket) > 255) {
            flash('danger', 'Alasan/keterangan wajib diisi untuk presensi manual.');
        } else {
            try {
                q('INSERT INTO presensi (mahasiswa_id, tanggal, sesi, waktu, status, transaction_id, keterangan)
                   VALUES (?,?,?,?,?,?,?)',
                    [$mid, $tanggal, $sesi, date('Y-m-d H:i:s'), 'manual', uuid4(), $ket]);
                audit('presensi_manual', $mhs['nim'] . " $tanggal $sesi");
                flash('success', 'Presensi manual untuk ' . $mhs['nama'] . ' dicatat.');
            } catch (PDOException $e) {
                // Kode 23000 = pelanggaran unik: sudah ada catatan pada sesi yang sama (BR-04)
                flash('warning', $e->getCode() === '23000'
                    ? 'Mahasiswa tersebut sudah tercatat pada tanggal dan sesi yang sama.'
                    : 'Gagal menyimpan presensi.');
            }
        }
    } elseif ($aksi === 'hapus') {
        $pid = (int)($_POST['id'] ?? 0);
        $r   = q('SELECT p.*, m.nim FROM presensi p JOIN mahasiswa m ON m.id = p.mahasiswa_id WHERE p.id = ?', [$pid])->fetch();
        if ($r) {
            q('DELETE FROM presensi WHERE id = ?', [$pid]);
            audit('presensi_hapus', $r['nim'] . ' ' . $r['tanggal'] . ' ' . $r['sesi']);
            flash('success', 'Catatan presensi dihapus (koreksi).');
        }
    }
    redirect($balik);
}

// ---------- Data ----------
$f   = rekap_filter();
$flt = $f['filters'];

$total = (int)q("SELECT COUNT(*) FROM presensi p JOIN mahasiswa m ON m.id = p.mahasiswa_id WHERE {$f['where']}", $f['params'])->fetchColumn();
$pg    = paginate($total, 20);

$rows = q("SELECT p.*, m.nim, m.nama, m.kelas
           FROM presensi p JOIN mahasiswa m ON m.id = p.mahasiswa_id
           WHERE {$f['where']} ORDER BY p.waktu DESC
           LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $f['params'])->fetchAll();

// Ringkasan hadir / tidak hadir (mahasiswa aktif sesuai filter kelas)
$jmlMhsSql = 'SELECT COUNT(*) FROM mahasiswa WHERE status_aktif = 1' . ($flt['kelas'] !== '' ? ' AND kelas = ?' : '');
$jmlMhs    = (int)q($jmlMhsSql, $flt['kelas'] !== '' ? [$flt['kelas']] : [])->fetchColumn();
$jmlHadirUnik = (int)q("SELECT COUNT(DISTINCT p.mahasiswa_id) FROM presensi p JOIN mahasiswa m ON m.id = p.mahasiswa_id WHERE {$f['where']}", $f['params'])->fetchColumn();
$tidakHadir = max(0, $jmlMhs - $jmlHadirUnik);

$daftarKelas = q("SELECT DISTINCT kelas FROM mahasiswa WHERE kelas <> '' ORDER BY kelas")->fetchAll(PDO::FETCH_COLUMN);
$daftarSesi  = q('SELECT DISTINCT sesi FROM presensi ORDER BY sesi')->fetchAll(PDO::FETCH_COLUMN);
$mhsAktif    = q('SELECT id, nim, nama FROM mahasiswa WHERE status_aktif = 1 ORDER BY nama')->fetchAll();

$pageTitle  = 'Rekap Presensi';
$activeMenu = 'rekap';
require __DIR__ . '/../includes/header.php';
?>
<div class="grid grid-4" style="margin-bottom:1.25rem">
    <div class="stat"><div class="num"><?= $total ?></div><div class="lbl">Catatan presensi (sesuai filter)</div></div>
    <div class="stat"><div class="num"><?= $jmlHadirUnik ?></div><div class="lbl">Mahasiswa pernah hadir</div></div>
    <div class="stat"><div class="num"><?= $tidakHadir ?></div><div class="lbl">Mahasiswa aktif belum hadir pada rentang ini</div></div>
    <div class="stat"><div class="num"><?= $jmlMhs ?></div><div class="lbl">Mahasiswa aktif<?= $flt['kelas'] !== '' ? ' (' . e($flt['kelas']) . ')' : '' ?></div></div>
</div>

<div class="card">
    <div class="card-head">
        <h2>Filter rekap</h2>
        <a class="btn btn-sm" href="<?= e(url('admin/ekspor.php?' . http_build_query($flt))) ?>">⬇ Ekspor CSV</a>
    </div>
    <form method="get" class="filters">
        <div class="f"><label>Dari tanggal</label><input class="input" type="date" name="dari" value="<?= e($flt['dari']) ?>"></div>
        <div class="f"><label>Sampai tanggal</label><input class="input" type="date" name="sampai" value="<?= e($flt['sampai']) ?>"></div>
        <div class="f"><label>Kelas</label>
            <select class="input" name="kelas"><option value="">Semua</option>
                <?php foreach ($daftarKelas as $k): ?>
                    <option value="<?= e($k) ?>" <?= $k === $flt['kelas'] ? 'selected' : '' ?>><?= e($k) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="f"><label>Sesi</label>
            <select class="input" name="sesi"><option value="">Semua</option>
                <?php foreach ($daftarSesi as $s): ?>
                    <option value="<?= e($s) ?>" <?= $s === $flt['sesi'] ? 'selected' : '' ?>><?= e($s) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="f"><label>NIM / nama</label><input class="input" name="cari" value="<?= e($flt['cari']) ?>"></div>
        <button class="btn btn-primary" type="submit">Tampilkan</button>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Tanggal</th><th>Waktu</th><th>NIM</th><th>Nama</th><th>Kelas</th><th>Sesi</th><th>Status</th><th>Confidence</th><th>Perangkat</th><th>Keterangan</th><th></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="11" class="empty">Tidak ada data pada filter ini.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e(tgl_id($r['tanggal'])) ?></td>
                    <td><?= e(date('H:i:s', strtotime($r['waktu']))) ?></td>
                    <td><?= e($r['nim']) ?></td>
                    <td><?= e($r['nama']) ?></td>
                    <td><?= e($r['kelas']) ?></td>
                    <td><?= e($r['sesi']) ?></td>
                    <td><span class="badge badge-<?= $r['status'] === 'hadir' ? 'green' : 'yellow' ?>"><?= e($r['status']) ?></span></td>
                    <td><?= $r['confidence'] !== null ? e(number_format((float)$r['confidence'] * 100, 1)) . '%' : '-' ?></td>
                    <td><?= e($r['device_id'] ?? '-') ?></td>
                    <td><?= e($r['keterangan'] ?? '') ?></td>
                    <td class="actions">
                        <form method="post" class="inline" data-confirm="Hapus catatan presensi ini?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                            <?php foreach ($flt as $k => $v): ?><input type="hidden" name="f_<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
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

<div class="card" style="max-width:820px">
    <h2>Presensi manual / koreksi</h2>
    <p class="muted">Dipakai bila wajah sah gagal dikenali (prosedur alternatif). Alasan wajib diisi dan tindakan dicatat pada audit log.</p>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="aksi" value="manual">
        <?php foreach ($flt as $k => $v): ?><input type="hidden" name="f_<?= e($k) ?>" value="<?= e($v) ?>"><?php endforeach; ?>
        <div class="form-row">
            <div><label class="form-label">Mahasiswa</label>
                <select class="input" name="mahasiswa_id" required>
                    <option value="">-- pilih --</option>
                    <?php foreach ($mhsAktif as $m): ?>
                        <option value="<?= (int)$m['id'] ?>"><?= e($m['nim'] . ' - ' . $m['nama']) ?></option>
                    <?php endforeach; ?>
                </select></div>
            <div><label class="form-label">Tanggal</label>
                <input class="input" type="date" name="tanggal" value="<?= e(date('Y-m-d')) ?>" required></div>
        </div>
        <div class="form-row">
            <div><label class="form-label">Sesi</label>
                <input class="input" name="sesi_manual" value="Sesi 1" maxlength="50" required></div>
            <div><label class="form-label">Alasan / keterangan</label>
                <input class="input" name="keterangan" maxlength="255" placeholder="contoh: wajah tidak dikenali karena pencahayaan" required></div>
        </div>
        <div class="form-actions"><button class="btn btn-primary" type="submit">Catat presensi manual</button></div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
