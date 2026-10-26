<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['superadmin']);

$cari  = trim((string)($_GET['cari'] ?? ''));
$hasil = trim((string)($_GET['hasil'] ?? ''));
$dari  = trim((string)($_GET['dari'] ?? ''));
$sampai = trim((string)($_GET['sampai'] ?? ''));

$where  = ['1=1'];
$params = [];
if ($cari !== '') {
    $where[]  = '(actor_username LIKE ? OR aksi LIKE ? OR target LIKE ?)';
    array_push($params, "%$cari%", "%$cari%", "%$cari%");
}
if ($hasil !== '') {
    $where[]  = 'hasil = ?';
    $params[] = $hasil;
}
$isDate = fn(string $d) => (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
if ($dari !== '' && $isDate($dari)) {
    $where[]  = 'waktu >= ?';
    $params[] = $dari . ' 00:00:00';
}
if ($sampai !== '' && $isDate($sampai)) {
    $where[]  = 'waktu <= ?';
    $params[] = $sampai . ' 23:59:59';
}
$w = implode(' AND ', $where);

$total = (int)q("SELECT COUNT(*) FROM audit_log WHERE $w", $params)->fetchColumn();
$pg    = paginate($total, 25);
$rows  = q("SELECT * FROM audit_log WHERE $w ORDER BY id DESC LIMIT {$pg['limit']} OFFSET {$pg['offset']}", $params)->fetchAll();
$hasilList = q('SELECT DISTINCT hasil FROM audit_log ORDER BY hasil')->fetchAll(PDO::FETCH_COLUMN);

$pageTitle  = 'Audit Log';
$activeMenu = 's_audit';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-head"><h2>Jejak aktivitas (<?= $total ?>)</h2></div>
    <form method="get" class="filters">
        <div class="f"><label>Cari pelaku / aksi / target</label><input class="input" name="cari" value="<?= e($cari) ?>"></div>
        <div class="f"><label>Hasil</label>
            <select class="input" name="hasil"><option value="">Semua</option>
                <?php foreach ($hasilList as $h): ?>
                    <option value="<?= e($h) ?>" <?= $h === $hasil ? 'selected' : '' ?>><?= e($h) ?></option>
                <?php endforeach; ?>
            </select></div>
        <div class="f"><label>Dari</label><input class="input" type="date" name="dari" value="<?= e($dari) ?>"></div>
        <div class="f"><label>Sampai</label><input class="input" type="date" name="sampai" value="<?= e($sampai) ?>"></div>
        <button class="btn btn-primary" type="submit">Terapkan</button>
        <a class="btn" href="<?= e(url('superadmin/audit.php')) ?>">Reset</a>
    </form>

    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Waktu</th><th>Pelaku</th><th>Aksi</th><th>Target / detail</th><th>Hasil</th><th>IP</th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="6" class="empty">Tidak ada catatan.</td></tr><?php endif; ?>
            <?php foreach ($rows as $l): ?>
                <tr>
                    <td><?= e(date('d/m/Y H:i:s', strtotime($l['waktu']))) ?></td>
                    <td><?= e($l['actor_username'] ?? '-') ?></td>
                    <td><?= e($l['aksi']) ?></td>
                    <td><?= e($l['target']) ?></td>
                    <td><span class="badge badge-<?= $l['hasil'] === 'berhasil' ? 'green' : 'red' ?>"><?= e($l['hasil']) ?></span></td>
                    <td><?= e($l['ip']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?= pagination_html($pg) ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
