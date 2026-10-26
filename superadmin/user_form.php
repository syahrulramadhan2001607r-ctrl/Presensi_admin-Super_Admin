<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['superadmin']);

$me = current_user();
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$data = ['username' => '', 'nama_lengkap' => '', 'email' => '', 'role' => 'admin', 'status_aktif' => 1];

if ($id > 0) {
    $row = q('SELECT * FROM users WHERE id = ?', [$id])->fetch();
    if (!$row) {
        flash('danger', 'Akun tidak ditemukan.');
        redirect('superadmin/users.php');
    }
    $data = $row;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $data['username']     = trim((string)($_POST['username'] ?? $data['username']));
    $data['nama_lengkap'] = trim((string)($_POST['nama_lengkap'] ?? ''));
    $data['email']        = trim((string)($_POST['email'] ?? ''));
    $data['role']         = (string)($_POST['role'] ?? 'admin');
    $data['status_aktif'] = isset($_POST['status_aktif']) ? 1 : 0;
    $password             = (string)($_POST['password'] ?? '');

    if (!preg_match('/^[A-Za-z0-9._-]{4,50}$/', $data['username'])) {
        $errors[] = 'Username 4-50 karakter (huruf, angka, titik, minus, garis bawah).';
    }
    if ($data['nama_lengkap'] === '' || mb_strlen($data['nama_lengkap']) > 100) {
        $errors[] = 'Nama lengkap wajib diisi (maksimal 100 karakter).';
    }
    if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }
    if (!in_array($data['role'], ALLOWED_ROLES, true)) {
        $errors[] = 'Peran tidak valid. Hanya Admin atau Super Admin.';
    }
    if (q('SELECT id FROM users WHERE username = ? AND id <> ?', [$data['username'], $id])->fetch()) {
        $errors[] = 'Username sudah dipakai.';
    }
    if ($id === 0 || $password !== '') {
        if ($err = password_error($password)) {
            $errors[] = $err;
        }
    }
    // Perlindungan akun sendiri dan Super Admin terakhir
    if ($id > 0 && $id === $me['id']) {
        if ($data['role'] !== 'superadmin') {
            $errors[] = 'Anda tidak dapat menurunkan peran akun Anda sendiri.';
        }
        if (!$data['status_aktif']) {
            $errors[] = 'Anda tidak dapat menonaktifkan akun Anda sendiri.';
        }
    }
    if ($id > 0 && ($row['role'] ?? '') === 'superadmin' && ($data['role'] !== 'superadmin' || !$data['status_aktif'])) {
        $lain = (int)q("SELECT COUNT(*) FROM users WHERE role='superadmin' AND status_aktif=1 AND id <> ?", [$id])->fetchColumn();
        if ($lain < 1) {
            $errors[] = 'Minimal harus ada satu Super Admin aktif.';
        }
    }

    if (!$errors) {
        if ($id > 0) {
            q('UPDATE users SET username=?, nama_lengkap=?, email=?, role=?, status_aktif=? WHERE id=?',
                [$data['username'], $data['nama_lengkap'], $data['email'] ?: null, $data['role'], $data['status_aktif'], $id]);
            if ($password !== '') {
                q('UPDATE users SET password_hash=? WHERE id=?', [password_hash($password, PASSWORD_DEFAULT), $id]);
            }
            audit('user_ubah', $data['username'] . ' (' . $data['role'] . ')');
            flash('success', 'Akun diperbarui.');
        } else {
            q('INSERT INTO users (username, nama_lengkap, email, password_hash, role, status_aktif) VALUES (?,?,?,?,?,?)',
                [$data['username'], $data['nama_lengkap'], $data['email'] ?: null,
                 password_hash($password, PASSWORD_DEFAULT), $data['role'], $data['status_aktif']]);
            audit('user_tambah', $data['username'] . ' (' . $data['role'] . ')');
            flash('success', 'Akun baru ditambahkan.');
        }
        redirect('superadmin/users.php');
    }
}

$pageTitle  = $id > 0 ? 'Ubah Akun' : 'Tambah Akun';
$activeMenu = 's_users';
require __DIR__ . '/../includes/header.php';
?>
<div class="card" style="max-width:720px">
    <h2><?= e($pageTitle) ?></h2>
    <?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

    <form method="post" autocomplete="off" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$id ?>">

        <div class="form-row">
            <div><label class="form-label" for="username">Username</label>
                <input class="input" id="username" name="username" value="<?= e($data['username']) ?>" maxlength="50" required></div>
            <div><label class="form-label" for="role">Peran</label>
                <select class="input" id="role" name="role">
                    <option value="admin" <?= $data['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="superadmin" <?= $data['role'] === 'superadmin' ? 'selected' : '' ?>>Super Admin</option>
                </select></div>
        </div>

        <label class="form-label" for="nama_lengkap">Nama lengkap</label>
        <input class="input" id="nama_lengkap" name="nama_lengkap" value="<?= e($data['nama_lengkap']) ?>" maxlength="100" required>

        <label class="form-label" for="email">Email</label>
        <input class="input" type="email" id="email" name="email" value="<?= e($data['email'] ?? '') ?>" maxlength="120">

        <label class="form-label" for="password"><?= $id > 0 ? 'Password baru (kosongkan bila tidak diganti)' : 'Password' ?></label>
        <input class="input" type="password" id="password" name="password" <?= $id > 0 ? '' : 'required' ?>>
        <div class="form-help">Minimal 8 karakter, kombinasi huruf dan angka.</div>

        <label class="checkbox">
            <input type="checkbox" name="status_aktif" value="1" <?= $data['status_aktif'] ? 'checked' : '' ?>>
            <span>Akun aktif</span>
        </label>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit">Simpan</button>
            <a class="btn" href="<?= e(url('superadmin/users.php')) ?>">Batal</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
