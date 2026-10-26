<?php
/**
 * Pembangun filter rekap presensi (dipakai rekap.php dan ekspor.php).
 */
function rekap_filter(): array
{
    $dari  = (string)($_GET['dari'] ?? date('Y-m-01'));
    $sampai = (string)($_GET['sampai'] ?? date('Y-m-d'));
    $kelas = trim((string)($_GET['kelas'] ?? ''));
    $cari  = trim((string)($_GET['cari'] ?? ''));
    $sesi  = trim((string)($_GET['sesi'] ?? ''));

    // Validasi format tanggal
    $valid = function (string $d): bool {
        $t = DateTime::createFromFormat('Y-m-d', $d);
        return $t && $t->format('Y-m-d') === $d;
    };
    if (!$valid($dari))   { $dari   = date('Y-m-01'); }
    if (!$valid($sampai)) { $sampai = date('Y-m-d'); }
    if ($dari > $sampai)  { [$dari, $sampai] = [$sampai, $dari]; }

    $where  = ['p.tanggal BETWEEN ? AND ?'];
    $params = [$dari, $sampai];
    if ($kelas !== '') {
        $where[]  = 'm.kelas = ?';
        $params[] = $kelas;
    }
    if ($sesi !== '') {
        $where[]  = 'p.sesi = ?';
        $params[] = $sesi;
    }
    if ($cari !== '') {
        $where[]  = '(m.nim LIKE ? OR m.nama LIKE ?)';
        $params[] = "%$cari%";
        $params[] = "%$cari%";
    }

    return [
        'where'   => implode(' AND ', $where),
        'params'  => $params,
        'filters' => compact('dari', 'sampai', 'kelas', 'cari', 'sesi'),
    ];
}
