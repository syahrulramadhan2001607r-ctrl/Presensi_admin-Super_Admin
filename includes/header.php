<?php
/**
 * Layout atas (sidebar + topbar).
 * Variabel yang dipakai: $pageTitle, $activeMenu
 */
$user       = current_user();
$pageTitle  = $pageTitle ?? 'Dashboard';
$activeMenu = $activeMenu ?? '';

$menuAdmin = [
    'dashboard' => ['Dashboard',          'admin/dashboard.php',  '▦'],
    'kiosk'     => ['Presensi Kamera',    'admin/kiosk.php',      '◉'],
    'mahasiswa' => ['Data Mahasiswa',     'admin/mahasiswa.php',  '☺'],
    'rekap'     => ['Rekap Presensi',     'admin/rekap.php',      '☰'],
    'profil'    => ['Profil & Password',  'admin/profil.php',     '⚙'],
];
$menuSuper = [
    's_dashboard' => ['Dashboard Super Admin', 'superadmin/dashboard.php',  '▦'],
    's_users'     => ['Kelola Admin',          'superadmin/users.php',      '♚'],
    's_config'    => ['Konfigurasi Sistem',    'superadmin/konfigurasi.php','⚒'],
    's_device'    => ['Kamera & Token API',    'superadmin/perangkat.php',  '▣'],
    's_dataset'   => ['Dataset Pelatihan',     'superadmin/dataset.php',    '⇩'],
    's_audit'     => ['Audit Log',             'superadmin/audit.php',      '✎'],
];
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/png" href="<?= e(url('assets/img/favicon.png')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
</head>
<body>
<div class="layout">
    <aside class="sidebar" id="sidebar">
        <div class="brand">
            <img class="brand-logo" src="<?= e(url('assets/img/logo-sm.png')) ?>" alt="Logo ITH">
            <div><strong><?= e(APP_NAME) ?></strong><small>Panel Admin &amp; Super Admin</small></div>
        </div>

        <?php if ($user): ?>
        <nav>
            <div class="nav-title">Admin</div>
            <?php foreach ($menuAdmin as $key => [$label, $href, $icon]): ?>
                <a href="<?= e(url($href)) ?>" class="<?= $activeMenu === $key ? 'active' : '' ?>">
                    <span class="ico"><?= $icon ?></span><?= e($label) ?>
                </a>
            <?php endforeach; ?>

            <?php if (($user['role'] ?? '') === 'superadmin'): ?>
                <div class="nav-title">Super Admin</div>
                <?php foreach ($menuSuper as $key => [$label, $href, $icon]): ?>
                    <a href="<?= e(url($href)) ?>" class="<?= $activeMenu === $key ? 'active' : '' ?>">
                        <span class="ico"><?= $icon ?></span><?= e($label) ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </nav>

        <div class="sidebar-foot">
            <form method="post" action="<?= e(url('logout.php')) ?>" data-confirm="Keluar dari aplikasi?">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-block btn-light">Keluar</button>
            </form>
        </div>
        <?php endif; ?>
    </aside>

    <div class="main">
        <header class="topbar">
            <button class="menu-toggle" type="button" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
            <h1><?= e($pageTitle) ?></h1>
            <?php if ($user): ?>
            <div class="who">
                <span class="avatar"><?= e(mb_strtoupper(mb_substr($user['nama'], 0, 1))) ?></span>
                <span class="nama"><?= e($user['nama']) ?></span>
                <span class="badge badge-<?= $user['role'] === 'superadmin' ? 'purple' : 'blue' ?>"><?= e(role_label($user['role'])) ?></span>
            </div>
            <?php endif; ?>
        </header>
        <main class="content">
            <?= flash_render() ?>
