<?php
/**
 * Koneksi database (PDO, prepared statement).
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
            $pdo->exec("SET time_zone = '" . APP_TZ_OFFSET . "'");
        } catch (PDOException $e) {
            http_response_code(500);
            if (defined('API_MODE')) {
                header('Content-Type: application/json; charset=utf-8');
                exit(json_encode(['status' => 'server_error']));
            }
            $detail = APP_DEBUG ? '<br><small>' . htmlspecialchars($e->getMessage()) . '</small>' : '';
            exit('<h3>Koneksi database gagal</h3><p>Pastikan MySQL di XAMPP sudah Start dan file '
                . '<code>database.sql</code> sudah diimpor lewat phpMyAdmin.' . $detail . '</p>');
        }
    }
    return $pdo;
}

/** Eksekusi query ber-parameter dan kembalikan statement. */
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
