<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Logout hanya lewat POST + token CSRF agar tidak bisa dipicu dari situs lain
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(current_user() ? home_for(current_user()['role']) : 'login.php');
}

$valid = hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''));
if ($valid && current_user()) {
    audit('logout', current_user()['username']);
    logout_user();
    session_start();
    flash('success', 'Anda telah keluar.');
    redirect('login.php');
}

// Token tidak valid / sesi sudah tidak ada
if (current_user()) {
    flash('danger', 'Permintaan keluar tidak valid. Silakan coba lagi.');
    redirect(home_for(current_user()['role']));
}
redirect('login.php');
