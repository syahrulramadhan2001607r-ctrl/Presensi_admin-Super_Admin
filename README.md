# Website Admin & Super Admin – Presensi Wajah CNN

Panel pengelola berbasis PHP + MySQL untuk XAMPP. Hanya akun **Admin** dan **Super Admin** yang dapat login.

## Instalasi di XAMPP

1. XAMPP Control Panel → **Start** Apache dan MySQL.
2. Salin folder `presensi_admin` ke `C:\xampp\htdocs\` sehingga menjadi `C:\xampp\htdocs\presensi_admin\login.php`.
   - Disarankan tetap di dalam subfolder. Jika Anda menaruh isinya langsung di `htdocs`, alamat dasar
     dideteksi otomatis, tetapi folder `admin`, `assets`, `config`, `includes`, `uploads` akan bercampur dengan file XAMPP.
   - Jika sebelumnya sudah menyalin versi lama, **hapus** dulu `database.sql` dan `README.md` lama di `htdocs`
     (berkas itu bisa diunduh lewat browser pada versi lama).
3. Buka `http://localhost/phpmyadmin` → tab **Import** → pilih `database/database.sql` → **Go**.
   Aman diimpor ulang: tabel dan data yang sudah ada tidak dihapus.
4. Buka `http://localhost/presensi_admin/` (atau `http://localhost/` bila diletakkan di root `htdocs`).

Pengaturan database ada di `config/config.php` (`DB_USER`, `DB_PASS`). Alamat dasar (`BASE_URL`) otomatis;
isi `BASE_URL_MANUAL` hanya bila deteksi otomatis keliru.

## Akun bawaan (segera ganti setelah login pertama)

| Jenis login  | Username     | Password         |
|--------------|--------------|------------------|
| Super Admin  | `superadmin` | `SuperAdmin@123` |
| Admin        | `admin`      | `Admin@12345`    |

## Fitur

**Admin** (juga dapat diakses Super Admin)
- Dashboard: ringkasan, grafik kehadiran 7 hari, status server pengenalan wajah, presensi terbaru
- **Presensi Kamera**: webcam di browser, tombol manual atau mode otomatis tiap 3 detik, hasil langsung (hadir / sudah presensi / tidak dikenali)
- Kelola mahasiswa (tambah, ubah, aktif/nonaktif, hapus) – FR-02
- Registrasi foto wajah (wajib ada persetujuan): unggah berkas **atau ambil langsung dari webcam**, hapus foto / seluruh data wajah – FR-03, FR-04, FR-21
- Rekap presensi: filter tanggal/kelas/sesi/nama, presensi manual & koreksi – FR-17
- Ekspor CSV – FR-18
- Profil dan ganti password

**Super Admin**
- Kelola akun Admin/Super Admin (tambah, ubah, ganti password, nonaktifkan, hapus)
- Konfigurasi threshold, zona waktu, versi model (berversi, wajib catatan, tercatat audit) – FR-19
- Kamera & Token API (opsional, untuk klien eksternal): token disimpan sebagai hash, tampil sekali, bisa dicabut
- Unduh dataset pelatihan (ZIP, folder `NIM_Nama/`) untuk pelatihan model di server Python
- Audit log – FR-20

## Cara kerja presensi dengan webcam

```
Webcam (browser) --JPEG--> PHP (api/kiosk.php) --JPEG--> Server Python (python_server/)
                                  |                              |  deteksi wajah + CNN MobileNetV2
                                  |<---- identitas + confidence -+
                                  +--> terapkan threshold, cegah presensi ganda, simpan ke MySQL
```

1. **Siapkan server Python** (folder `python_server/`): letakkan `model/mobilenetv2.h5` dan `model/labels.json`
   hasil pelatihan, lalu jalankan:
   ```
   cd python_server
   pip install -r requirements.txt
   set PY_SERVER_TOKEN=rahasia123      (Windows; Linux/macOS: export ...)
   python server.py
   ```
2. **Isi `config/config.php`**: `PY_SERVER_URL` (bawaan `http://127.0.0.1:5000`) dan `PY_SERVER_TOKEN`
   (sama dengan di atas). Kosongkan `PY_SERVER_URL` bila server belum ada.
3. Login → menu **Presensi Kamera** → *Nyalakan kamera* → izinkan akses kamera.
4. Aktifkan ekstensi PHP **cURL** di XAMPP bila belum (`extension=curl` di `php.ini`; biasanya sudah aktif).

Syarat kamera: browser hanya mengizinkan webcam di **`http://localhost`** atau **HTTPS**. Jika panel dibuka dari
komputer lain lewat IP (`http://192.168.x.x/...`), kamera akan diblokir — gunakan HTTPS atau jalankan kiosk di
komputer yang sama dengan XAMPP.

Panel PHP-lah yang menentukan hasil akhir: `tidak_dikenal` bila confidence di bawah threshold (menu Konfigurasi),
mahasiswa tidak aktif/tanpa persetujuan/tanpa foto, dan `sudah_presensi` bila sudah tercatat pada sesi yang sama.

### Kontrak server Python
`POST {PY_SERVER_URL}/presensi`, body = byte JPEG, header `Authorization: Bearer <PY_SERVER_TOKEN>`.
Respons: `{"status":"ok","id_pengguna":"2021001_Nama","confidence":0.97}` atau `{"status":"tidak_ada_wajah"}`.
`GET /health` dipakai dashboard untuk menampilkan status layanan. `server.py` adalah **template**: pastikan praproses
gambar sama dengan saat pelatihan model Anda.

### API klien eksternal (opsional)
Bila Anda punya skrip lain yang sudah mengenali wajah sendiri, kirim hasilnya ke
`POST /presensi_admin/api/presensi.php` dengan `Authorization: Bearer <token>` (token dibuat di menu
*Kamera & Token API*) dan body JSON `{"nim":"2021001","confidence":0.94,"sesi":"Sesi 1","transaction_id":"opsional"}`.
`transaction_id` yang sama dikirim ulang tidak membuat catatan ganda. `GET /presensi_admin/api/config.php` memberi threshold aktif.

## Keamanan yang diterapkan
- Password di-hash, login dibatasi 5 kali gagal / 15 menit, sesi berakhir 30 menit tanpa aktivitas
- PDO prepared statement, output di-escape, token CSRF pada semua form POST (termasuk logout)
- Status akun dicek ulang di setiap permintaan; pembatasan peran di sisi server
- Foto wajah tidak bisa dibuka langsung lewat URL (hanya via `admin/foto.php` setelah login)
- `config/`, `includes/`, `uploads/`, `database/` diblokir lewat `.htaccess`; berkas `.sql/.md/.log` tidak dapat diunduh
- Unduhan dataset hanya Super Admin dan dicatat di audit log

## Struktur folder
```
presensi_admin/
├── index.php, login.php, logout.php
├── database/database.sql
├── config/config.php
├── includes/        (bootstrap, db, auth, functions, header, footer, api_bootstrap, presensi_service, rekap_helper)
├── admin/           (dashboard, kiosk, mahasiswa, wajah, rekap, ekspor, profil, foto)
├── superadmin/      (dashboard, users, konfigurasi, perangkat, dataset, audit)
├── api/             (kiosk.php, presensi.php, config.php)
├── python_server/   (server.py, requirements.txt, model/)
├── assets/          (css, js: camera/kiosk/wajah_cam, img/logo)
└── uploads/wajah/   (foto registrasi, tidak publik)
```

## Catatan
- Deteksi wajah dan pelatihan model dilakukan di server Python (pelatihan tidak termasuk di sini; unduh dataset dari menu
  Super Admin). Panel memeriksa format, ukuran, dan resolusi foto registrasi.
- Belum ada liveness/anti-spoofing (sesuai keterbatasan di SRS), jadi jangan jadikan satu-satunya kontrol akses.
- Untuk produksi: gunakan HTTPS, ubah `APP_DEBUG` ke `false`, dan ganti semua password bawaan.
