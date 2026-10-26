<?php
/**
 * Fungsi bantu umum.
 */

/** Escape output HTML. */
function e($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Buat URL absolut-path dari BASE_URL. */
function url(string $path = ''): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

function redirect(string $path)
{
    header('Location: ' . url($path));
    exit;
}

function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0', 0, 45);
}

// ---------- CSRF ----------
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

/** Panggil di awal penanganan POST. */
function csrf_check(): void
{
    $sent = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Token keamanan tidak valid atau kedaluwarsa. Silakan muat ulang halaman dan coba lagi.');
    }
}

// ---------- Flash message ----------
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flash_render(): string
{
    if (empty($_SESSION['flash'])) {
        return '';
    }
    $html = '';
    foreach ($_SESSION['flash'] as $f) {
        $html .= '<div class="alert alert-' . e($f['type']) . '">' . e($f['msg']) . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

// ---------- Konfigurasi sistem ----------
function get_config(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (q('SELECT kunci, nilai FROM konfigurasi')->fetchAll() as $r) {
            $cache[$r['kunci']] = $r['nilai'];
        }
    }
    return $cache[$key] ?? $default;
}

function set_config(string $key, string $value): void
{
    $by = current_user()['username'] ?? 'system';
    q('INSERT INTO konfigurasi (kunci, nilai, updated_by) VALUES (?,?,?)
       ON DUPLICATE KEY UPDATE nilai = VALUES(nilai), updated_by = VALUES(updated_by)', [$key, $value, $by]);
}

/**
 * Samakan zona waktu PHP dan MySQL dengan nilai 'zona_waktu' pada tabel konfigurasi.
 * Bila tabel belum ada (database belum diimpor), pakai APP_TIMEZONE bawaan.
 */
function apply_timezone(): void
{
    try {
        $tz = get_config('zona_waktu', APP_TIMEZONE);
        if (in_array($tz, timezone_identifiers_list(), true)) {
            date_default_timezone_set($tz);
            $offset = (new DateTime('now', new DateTimeZone($tz)))->format('P');
            db()->exec("SET time_zone = '" . $offset . "'");
        }
    } catch (Throwable $e) {
        // diabaikan: pakai zona waktu bawaan dari config.php
    }
}

// ---------- Audit log (FR-20, NFR-12) ----------
function audit(string $aksi, string $target = '', string $hasil = 'berhasil'): void
{
    $u = current_user();
    try {
        q('INSERT INTO audit_log (actor_id, actor_username, aksi, target, hasil, ip) VALUES (?,?,?,?,?,?)', [
            $u['id'] ?? null,
            $u['username'] ?? null,
            mb_substr($aksi, 0, 80),
            mb_substr($target, 0, 255),
            mb_substr($hasil, 0, 20),
            client_ip(),
        ]);
    } catch (Throwable $e) {
        // Kegagalan audit tidak boleh menghentikan alur utama
    }
}

// ---------- Paginasi ----------
function paginate(int $total, int $perPage = 15): array
{
    $pages  = max(1, (int)ceil($total / $perPage));
    $page   = min(max(1, (int)($_GET['page'] ?? 1)), $pages);
    $offset = ($page - 1) * $perPage;
    return ['page' => $page, 'pages' => $pages, 'offset' => $offset, 'limit' => $perPage, 'total' => $total];
}

function pagination_html(array $p): string
{
    if ($p['pages'] <= 1) {
        return '';
    }
    $query = $_GET;
    $html  = '<div class="pagination">';
    $link  = function (int $n, string $label, bool $active = false) use ($query) {
        $query['page'] = $n;
        $cls = $active ? ' class="active"' : '';
        return '<a' . $cls . ' href="?' . e(http_build_query($query)) . '">' . e($label) . '</a>';
    };
    if ($p['page'] > 1) {
        $html .= $link($p['page'] - 1, '‹');
    }
    $start = max(1, $p['page'] - 2);
    $end   = min($p['pages'], $p['page'] + 2);
    for ($i = $start; $i <= $end; $i++) {
        $html .= $link($i, (string)$i, $i === $p['page']);
    }
    if ($p['page'] < $p['pages']) {
        $html .= $link($p['page'] + 1, '›');
    }
    return $html . '</div>';
}

// ---------- Format ----------
function tgl_id(?string $date): string
{
    if (!$date) {
        return '-';
    }
    $t = strtotime($date);
    return $t ? date('d/m/Y', $t) : '-';
}

function waktu_id(?string $dt): string
{
    if (!$dt) {
        return '-';
    }
    $t = strtotime($dt);
    return $t ? date('d/m/Y H:i', $t) : '-';
}

/** Cegah CSV/Excel formula injection. */
function csv_safe($v): string
{
    $s = (string)$v;
    if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        $s = "'" . $s;
    }
    return $s;
}

/** UUID v4 sederhana untuk transaction_id. */
function uuid4(): string
{
    $d = random_bytes(16);
    $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
    $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
}

/** Validasi kekuatan password: minimal 8 karakter, ada huruf dan angka. */
function password_error(string $pw): ?string
{
    if (strlen($pw) < 8) {
        return 'Password minimal 8 karakter.';
    }
    if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
        return 'Password harus mengandung huruf dan angka.';
    }
    return null;
}
