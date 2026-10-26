<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['superadmin']);

$me = current_user();

/** Jumlah Super Admin aktif selain $exceptId. */
function super_aktif_lain(int $exceptId): int
{
    return (int)q("SELECT COUNT(*) FROM users WHERE role='superadmin' AND status_aktif=1 AND id <> ?", [$exceptId])->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = (string)($_POST['aksi'] ?? '');
    $id   = (int)($_POST['id'] ?? 0);
    $u    = q('SELECT * FROM users WHERE id = ?', [$id])->fetch();

    if (!$u) {
        flash('danger', 'Akun tidak ditemukan.');
    } elseif ($aksi === 'toggle') {
        if ($id === $me['id']) {
            flash('danger', 'Anda tidak dapat menonaktifkan akun Anda sendiri.');
        } elseif ($u['role'] === 'superadmin' && $u['status_aktif'] && super_aktif_lain($id) < 1) {
            flash('danger', 'Minimal harus ada satu Super Admin aktif.');
        } else {
            $baru = $u['status_aktif'] ? 0 : 1;
            q('UPDATE users SET status_aktif = ? WHERE id = ?', [$baru, $id]);
            audit($baru ? 'user_aktifkan' : 'user_nonaktifkan', $u['username']);
            flash('success', 'Akun ' . $u['username'] . ($baru ? ' diaktifkan.' : ' dinonaktifkan.'));
        }
    } elseif ($aksi === 'hapus') {
        if ($id === $me['id']) {
            flash('danger', 'Anda tidak dapat menghapus akun Anda sendiri.');
        } elseif ($u['role'] === 'superadmin' && $u['status_aktif'] && super_aktif_lain($id) < 1) {
            flash('danger', 'Minimal harus ada satu Super Admin aktif.');
        } else {
            q('DELETE FROM users WHERE id = ?', [$id]);
            audit('user_hapus', $u['username']);
            flash('success', 'Akun ' . $u['username'] . ' dihapus.');
        }
    }
    redirect('superadmin/users.php');
}

$users = q('SELECT * FROM users ORDER BY role DESC, username ASC')->fetchAll();

$pageTitle  = 'Kelola Admin';
$activeMenu = 's_users';
require __DIR__ . '/../includes/header.php';
?>
<div class="card">
    <div class="card-head">
        <h2>Akun pengelola (<?= count($users) ?>)</h2>
        <a class="btn btn-primary" href="<?= e(url('superadmin/user_form.php')) ?>">+ Tambah akun</a>
    </div>
    <p class="muted">Hanya peran Admin dan Super Admin yang dapat login ke panel ini.</p>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Username</th><th>Nama</th><th>Email</th><th>Peran</th><th>Status</th><th>Login terakhir</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): $self = (int)$u['id'] === $me['id']; ?>
                <tr>
                    <td><?= e($u['username']) ?><?= $self ? ' <small>(Anda)</small>' : '' ?></td>
                    <td><?= e($u['nama_lengkap']) ?></td>
                    <td><?= e($u['email'] ?? '-') ?></td>
                    <td><span class="badge badge-<?= $u['role'] === 'superadmin' ? 'purple' : 'blue' ?>"><?= e(role_label($u['role'])) ?></span></td>
                    <td><span class="badge badge-<?= $u['status_aktif'] ? 'green' : 'gray' ?>"><?= $u['status_aktif'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                    <td><?= e(waktu_id($u['last_login'])) ?></td>
                    <td class="actions">
                        <a class="btn btn-sm" href="<?= e(url('superadmin/user_form.php?id=' . $u['id'])) ?>">Ubah</a>
                        <?php if (!$self): ?>
                        <form method="post" class="inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="toggle">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <button class="btn btn-sm" type="submit"><?= $u['status_aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                        </form>
                        <form method="post" class="inline" data-confirm="Hapus akun <?= e($u['username']) ?> secara permanen?">
                            <?= csrf_field() ?>
                            <input type="hidden" name="aksi" value="hapus">
                            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit">Hapus</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
