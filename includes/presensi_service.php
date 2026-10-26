<?php
/**
 * Logika presensi yang dipakai bersama oleh api/presensi.php (klien eksternal bertoken)
 * dan api/kiosk.php (webcam di browser).
 */

/**
 * Terapkan aturan bisnis lalu catat presensi.
 * BR-01 terdaftar+aktif+berpersetujuan+punya foto, BR-02 confidence >= threshold,
 * BR-04 satu catatan per mahasiswa per sesi, BR-06 hadir hanya setelah tersimpan.
 *
 * @return array [kode HTTP, isi respons]
 */
function catat_presensi(string $idp, float $confidence, string $sesi, string $deviceId, ?string $trx = null): array
{
    $trx = ($trx === null || $trx === '') ? uuid4() : $trx;

    $cari = "SELECT m.id, m.nim, m.nama, m.kelas
             FROM mahasiswa m
             WHERE m.nim = ? AND m.status_aktif = 1 AND m.persetujuan_wajah = 1
               AND EXISTS (SELECT 1 FROM data_wajah d WHERE d.mahasiswa_id = m.id)";
    $mhs = q($cari, [$idp])->fetch();
    if (!$mhs && strpos($idp, '_') !== false) {
        $mhs = q($cari, [explode('_', $idp, 2)[0]])->fetch();
    }

    $threshold = (float)get_config('threshold', '0.90');
    if (!$mhs || $confidence < $threshold) {
        return [200, ['status' => 'tidak_dikenal', 'confidence' => $confidence, 'threshold' => $threshold]];
    }

    // Idempotensi: transaction_id yang sama dikirim ulang tidak membuat catatan baru
    $ada = q('SELECT p.*, m.nim, m.nama FROM presensi p JOIN mahasiswa m ON m.id = p.mahasiswa_id WHERE p.transaction_id = ?', [$trx])->fetch();
    if ($ada) {
        if ((int)$ada['mahasiswa_id'] !== (int)$mhs['id']) {
            return [400, ['status' => 'permintaan_tidak_valid', 'pesan' => 'transaction_id sudah dipakai.']];
        }
        return [200, [
            'status' => 'hadir', 'ulang' => true, 'id_pengguna' => $ada['nim'], 'nama' => $ada['nama'],
            'confidence' => (float)$ada['confidence'], 'sesi' => $ada['sesi'], 'waktu' => date('c', strtotime($ada['waktu'])),
        ]];
    }

    $sekarang = time();
    try {
        q('INSERT INTO presensi (mahasiswa_id, tanggal, sesi, waktu, confidence, status, device_id, model_version, transaction_id)
           VALUES (?,?,?,?,?,?,?,?,?)', [
            $mhs['id'], date('Y-m-d', $sekarang), $sesi, date('Y-m-d H:i:s', $sekarang), round($confidence, 4),
            'hadir', mb_substr($deviceId, 0, 50), get_config('model_version', ''), $trx,
        ]);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') { // batas unik (mahasiswa, tanggal, sesi)
            $lama = q('SELECT waktu FROM presensi WHERE mahasiswa_id = ? AND tanggal = ? AND sesi = ?',
                [$mhs['id'], date('Y-m-d', $sekarang), $sesi])->fetch();
            return [200, [
                'status' => 'sudah_presensi', 'id_pengguna' => $mhs['nim'], 'nama' => $mhs['nama'], 'sesi' => $sesi,
                'waktu' => $lama ? date('c', strtotime($lama['waktu'])) : null,
            ]];
        }
        throw $e;
    }

    return [200, [
        'status' => 'hadir', 'id_pengguna' => $mhs['nim'], 'nama' => $mhs['nama'], 'kelas' => $mhs['kelas'],
        'confidence' => round($confidence, 4), 'sesi' => $sesi, 'waktu' => date('c', $sekarang), 'transaction_id' => $trx,
    ]];
}

/**
 * Kirim satu gambar JPEG ke server pengenalan wajah (Python).
 * Kontrak respons server Python:
 *   {"status":"ok","id_pengguna":"2021001_Nama","confidence":0.97}
 *   {"status":"tidak_ada_wajah"}
 *
 * @return array ['ok'=>bool, 'data'=>array|null, 'error'=>string]
 */
function pengenalan_kirim(string $jpeg): array
{
    if (PY_SERVER_URL === '') {
        return ['ok' => false, 'data' => null, 'error' => 'Server pengenalan wajah belum dikonfigurasi (PY_SERVER_URL di config/config.php).'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'data' => null, 'error' => 'Ekstensi PHP cURL belum aktif (aktifkan extension=curl di php.ini XAMPP).'];
    }
    $ch = curl_init(rtrim(PY_SERVER_URL, '/') . '/presensi');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $jpeg,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT        => PY_TIMEOUT,
        CURLOPT_HTTPHEADER     => ['Content-Type: image/jpeg', 'Authorization: Bearer ' . PY_SERVER_TOKEN],
    ]);
    $out  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);

    if ($out === false) {
        return ['ok' => false, 'data' => null, 'error' => 'Server pengenalan wajah tidak dapat dihubungi (' . $err . ').'];
    }
    $data = json_decode((string)$out, true);
    if ($code !== 200 || !is_array($data)) {
        return ['ok' => false, 'data' => null, 'error' => 'Server pengenalan wajah mengembalikan respons tidak valid (HTTP ' . $code . ').'];
    }
    return ['ok' => true, 'data' => $data, 'error' => ''];
}

/** Status layanan pengenalan untuk dashboard (NFR-06). */
function layanan_status(): array
{
    if (PY_SERVER_URL === '') {
        return ['state' => 'belum', 'teks' => 'Belum dikonfigurasi'];
    }
    if (!function_exists('curl_init')) {
        return ['state' => 'offline', 'teks' => 'Ekstensi cURL belum aktif'];
    }
    $ch = curl_init(rtrim(PY_SERVER_URL, '/') . '/health');
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 1, CURLOPT_TIMEOUT => 2]);
    $out  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($out === false || $code !== 200) {
        return ['state' => 'offline', 'teks' => 'Tidak dapat dihubungi'];
    }
    $d = json_decode((string)$out, true);
    $model = is_array($d) && !empty($d['model_loaded']);
    return ['state' => $model ? 'online' : 'peringatan', 'teks' => $model ? 'Siap, model termuat' : 'Berjalan, tetapi model belum ditemukan'];
}
