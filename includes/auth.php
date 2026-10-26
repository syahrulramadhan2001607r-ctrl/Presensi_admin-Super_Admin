<?php
/**
 * Autentikasi & otorisasi. Hanya peran 'admin' dan 'superadmin' yang dapat login.
 */
const ALLOWED_ROLES = ['admin', 'superadmin'];

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_superadmin(): bool
{
    return (current_user()['role'] ?? '') === 'superadmin';
}

function role_label(string $role): string
{
    return $role === 'superadmin' ? 'Super Admin' : 'Admin';
}

/** Halaman beranda sesuai peran. */
function home_for(string $role): string
{
    return $role === 'superadmin' ? 'superadmin/dashboard.php' : 'admin/dashboard.php';
}

/**
 * Wajib login. $roles = daftar peran yang diizinkan mengakses halaman ini.
 */
function require_login(array $roles = ALLOWED_ROLES): void
{
    $u = current_user();
    if (!$u) {
        redirect('login.php');
    }

    // Sesi kedaluwarsa karena tidak aktif
    if (time() - ($_SESSION['last_activity'] ?? 0) > SESSION_TIMEOUT) {
        logout_user();
        session_start();
        flash('warning', 'Sesi berakhir karena tidak ada aktivitas. Silakan login kembali.');
        redirect('login.php');
    }
    $_SESSION['last_activity'] = time();

    // Pastikan akun masih aktif dan perannya belum berubah
    $row = q('SELECT id, role, status_aktif FROM users WHERE id = ?', [$u['id']])->fetch();
    if (!$row || !(int)$row['status_aktif'] || !in_array($row['role'], ALLOWED_ROLES, true)) {
        logout_user();
        session_start();
        flash('danger', 'Akun Anda tidak aktif atau tidak lagi memiliki akses.');
        redirect('login.php');
    }
    $_SESSION['user']['role'] = $row['role'];

    if (!in_array($row['role'], $roles, true)) {
        http_response_code(403);
        $pageTitle  = 'Akses Ditolak';
        $activeMenu = '';
        require __DIR__ . '/header.php';
        echo '<div class="card"><h2>403 - Akses ditolak</h2>'
           . '<p>Halaman ini hanya untuk peran yang berwenang.</p>'
           . '<p><a class="btn" href="' . e(url(home_for($row['role']))) . '">Kembali ke dashboard</a></p></div>';
        require __DIR__ . '/footer.php';
        exit;
    }
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/** Jumlah percobaan gagal dalam jendela waktu. */
function login_failures(string $username, string $ip): int
{
    $since = date('Y-m-d H:i:s', time() - LOGIN_WINDOW_MINUTES * 60);
    $byUser = (int)q('SELECT COUNT(*) FROM login_attempts WHERE username = ? AND ip = ? AND waktu >= ?',
        [$username, $ip, $since])->fetchColumn();
    $byIp = (int)q('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND waktu >= ?',
        [$ip, $since])->fetchColumn();
    return max($byUser, (int)floor($byIp / 4));
}

/**
 * Proses login. $roleDipilih = 'admin' atau 'superadmin' dari opsi di form.
 * Mengembalikan [bool berhasil, string pesan].
 */
function attempt_login(string $username, string $password, string $roleDipilih): array
{
    $ip = client_ip();
    $username = trim($username);

    if (!in_array($roleDipilih, ALLOWED_ROLES, true)) {
        return [false, 'Pilih jenis login: Admin atau Super Admin.'];
    }
    if ($username === '' || $password === '') {
        return [false, 'Username dan password wajib diisi.'];
    }
    if (login_failures($username, $ip) >= LOGIN_MAX_ATTEMPTS) {
        audit('login_diblokir', $username, 'ditolak');
        return [false, 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . LOGIN_WINDOW_MINUTES . ' menit.'];
    }

    $user = q('SELECT * FROM users WHERE username = ? AND role IN (\'admin\',\'superadmin\') LIMIT 1', [$username])->fetch();

    // Verifikasi tetap dijalankan walau user tidak ada (mengurangi perbedaan waktu respons)
    $dummy = '$2y$10$2R6xSdz.svXV/gIsD7OHP.yCL3el4BQI5pjcbPzau3seYzQHw.0re';
    $hash  = $user['password_hash'] ?? $dummy;
    $ok    = password_verify($password, $hash);

    if (!$user || !$ok || (int)$user['status_aktif'] !== 1 || $user['role'] !== $roleDipilih) {
        q('INSERT INTO login_attempts (ip, username) VALUES (?,?)', [$ip, mb_substr($username, 0, 50)]);
        audit('login_gagal', $username, 'gagal');
        // Pesan sengaja umum agar tidak membocorkan keberadaan akun
        return [false, 'Login gagal. Periksa username, password, dan jenis login yang dipilih.'];
    }

    // Berhasil
    q('DELETE FROM login_attempts WHERE username = ? AND ip = ?', [$username, $ip]);
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'       => (int)$user['id'],
        'username' => $user['username'],
        'nama'     => $user['nama_lengkap'],
        'role'     => $user['role'],
    ];
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
    q('UPDATE users SET last_login = NOW() WHERE id = ?', [$user['id']]);
    audit('login', $username);
    return [true, ''];
}
