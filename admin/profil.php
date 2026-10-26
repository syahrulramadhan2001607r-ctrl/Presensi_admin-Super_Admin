<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login(['admin', 'superadmin']);

$uid  = current_user()['id'];
$me   = q('SELECT * FROM users WHERE id = ?', [$uid])->fetch();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $aksi = (string)($_POST['aksi'] ?? '');

    if ($aksi === 'profil') {
        $nama  = trim((string)($_POST['nama_lengkap'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        if ($nama === '' || mb_strlen($nama) > 100) {
            $errors[] = 'Nama lengkap wajib diisi (maksimal 100 karakter).';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid.';
        }
        if (!$errors) {
            q('UPDATE users SET nama_lengkap = ?, email = ? WHERE id = ?', [$nama, $email ?: null, $uid]);
            $_SESSION['user']['nama'] = $nama;
            audit('profil_ubah', $me['username']);
            flash('success', 'Profil diperbarui.');
            redirect('admin/profil.php');
        }
        $me['nama_lengkap'] = $nama;
        $me['email']        = $email;
    }

    if ($aksi === 'password') {
        $lama  = (string)($_POST['password_lama'] ?? '');
        $baru  = (string)($_POST['password_baru'] ?? '');
        $ulang = (string)($_POST['password_ulang'] ?? '');

        if (!password_verify($lama, $me['password_hash'])) {
            $errors[] = 'Password lama salah.';
        } elseif ($baru !== $ulang) {
            $errors[] = 'Konfirmasi password baru tidak sama.';
        } elseif ($err = password_error($baru)) {
            $errors[] = $err;
        } elseif ($baru === $lama) {
            $errors[] = 'Password baru harus berbeda dari password lama.';
        } else {
            q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($baru, PASSWORD_DEFAULT), $uid]);
            session_regenerate_id(true);
            audit('password_ubah', $me['username']);
            flash('success', 'Password berhasil diganti.');
            redirect('admin/profil.php');
        }
    }
}

$pageTitle  = 'Profil & Password';
$activeMenu = 'profil';
require __DIR__ . '/../includes/header.php';
?>
<?php foreach ($errors as $err): ?>
    <div class="alert alert-danger"><?= e($err) ?></div>
<?php endforeach; ?>

<div class="grid grid-2">
    <div class="card">
        <h2>Profil</h2>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="profil">
            <label class="form-label">Username</label>
            <input class="input" value="<?= e($me['username']) ?>" disabled>
            <label class="form-label">Peran</label>
            <input class="input" value="<?= e(role_label($me['role'])) ?>" disabled>
            <label class="form-label" for="nama_lengkap">Nama lengkap</label>
            <input class="input" id="nama_lengkap" name="nama_lengkap" value="<?= e($me['nama_lengkap']) ?>" maxlength="100" required>
            <label class="form-label" for="email">Email</label>
            <input class="input" type="email" id="email" name="email" value="<?= e($me['email'] ?? '') ?>" maxlength="120">
            <div class="form-actions"><button class="btn btn-primary" type="submit">Simpan profil</button></div>
        </form>
        <p class="form-help" style="margin-top:1rem">Login terakhir: <?= e(waktu_id($me['last_login'])) ?></p>
    </div>

    <div class="card">
        <h2>Ganti password</h2>
        <form method="post" autocomplete="off">
            <?= csrf_field() ?>
            <input type="hidden" name="aksi" value="password">
            <label class="form-label" for="pl">Password lama</label>
            <input class="input" type="password" id="pl" name="password_lama" required>
            <label class="form-label" for="pb">Password baru</label>
            <input class="input" type="password" id="pb" name="password_baru" required>
            <div class="form-help">Minimal 8 karakter, kombinasi huruf dan angka.</div>
            <label class="form-label" for="pu">Ulangi password baru</label>
            <input class="input" type="password" id="pu" name="password_ulang" required>
            <div class="form-actions"><button class="btn btn-primary" type="submit">Ganti password</button></div>
        </form>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
