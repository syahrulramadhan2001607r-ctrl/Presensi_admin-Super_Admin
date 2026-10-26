<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Jika sudah login, langsung ke dashboard sesuai peran
if (current_user()) {
    redirect(home_for(current_user()['role']));
}

$error    = '';
$username = '';
$role     = 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim((string)($_POST['username'] ?? ''));
    $role     = (string)($_POST['role'] ?? 'admin');
    $password = (string)($_POST['password'] ?? '');

    [$ok, $msg] = attempt_login($username, $password, $role);
    if ($ok) {
        redirect(home_for(current_user()['role']));
    }
    $error = $msg;
}
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login · <?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/png" href="<?= e(url('assets/img/favicon.png')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body class="login-body">
<div class="login-shell">

    <section class="login-side">
        <img class="login-logo" src="<?= e(url('assets/img/logo.png')) ?>" alt="Logo ITH">
        <h2><?= e(APP_NAME) ?></h2>
        <p>Sistem presensi wajah otomatis berbasis CNN menggunakan kamera/webcam.</p>
        <div class="login-pills">
            <span>Kelola mahasiswa</span>
            <span>Registrasi wajah</span>
            <span>Rekap &amp; ekspor</span>
            <span>Audit keamanan</span>
        </div>
    </section>

    <section class="login-form">
        <h1>Selamat datang</h1>
        <p class="sub">Masuk sebagai Admin atau Super Admin</p>

        <?= flash_render() ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off" novalidate>
            <?= csrf_field() ?>

            <label class="form-label">Jenis login</label>
            <div class="role-switch">
                <label class="role-opt">
                    <input type="radio" name="role" value="admin" <?= $role === 'admin' ? 'checked' : '' ?>>
                    <span><strong>Admin</strong><small>Kelola mahasiswa &amp; rekap</small></span>
                </label>
                <label class="role-opt">
                    <input type="radio" name="role" value="superadmin" <?= $role === 'superadmin' ? 'checked' : '' ?>>
                    <span><strong>Super Admin</strong><small>Akses penuh sistem</small></span>
                </label>
            </div>

            <label class="form-label" for="username">Username</label>
            <input class="input" type="text" id="username" name="username" value="<?= e($username) ?>"
                   maxlength="50" required autofocus>

            <label class="form-label" for="password">Password</label>
            <div class="pw-wrap">
                <input class="input" type="password" id="password" name="password" required>
                <button type="button" class="pw-toggle" onclick="togglePw()" aria-label="Tampilkan password">Lihat</button>
            </div>

            <button class="btn btn-primary btn-block" type="submit">Masuk</button>
        </form>

        <p class="login-note">Hanya akun Admin dan Super Admin yang dapat masuk. Mahasiswa tidak memerlukan login di panel ini.</p>
    </section>
</div>
<script src="<?= e(url('assets/js/app.js')) ?>"></script>
</body>
</html>
