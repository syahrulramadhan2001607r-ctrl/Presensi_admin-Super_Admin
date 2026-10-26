/* Halaman Presensi Kamera: ambil frame webcam -> api/kiosk.php -> tampilkan hasil. */
(function () {
    var $ = function (id) { return document.getElementById(id); };
    var video = $('kiosk-video'), btnStart = $('kiosk-start'), btnSnap = $('kiosk-snap'),
        auto = $('kiosk-auto'), sesi = $('kiosk-sesi'), result = $('kiosk-result'),
        log = $('kiosk-log'), sel = $('kiosk-cam');
    var busy = false, timer = null, running = false;

    function show(kind, title, sub) {
        result.className = 'kiosk-result ' + kind;
        result.innerHTML = '';
        var a = document.createElement('div'); a.className = 'kr-title'; a.textContent = title;
        var b = document.createElement('div'); b.className = 'kr-sub'; b.textContent = sub || '';
        result.appendChild(a); result.appendChild(b);
    }

    function addLog(text, cls) {
        if (log.firstElementChild && log.firstElementChild.classList.contains('muted')) { log.innerHTML = ''; }
        var li = document.createElement('li');
        li.className = cls || '';
        li.textContent = new Date().toLocaleTimeString('id-ID') + ' — ' + text;
        log.insertBefore(li, log.firstChild);
        while (log.children.length > 8) { log.removeChild(log.lastChild); }
    }

    function schedule(ms) {
        clearTimeout(timer);
        if (auto.checked && running) { timer = setTimeout(capture, ms); }
    }

    async function capture() {
        if (busy || !running) { return; }
        var nama = (sesi.value || '').trim();
        if (!nama) { show('warn', 'Sesi kosong', 'Isi nama sesi terlebih dahulu.'); schedule(3000); return; }

        busy = true; btnSnap.disabled = true;
        var next = 3000;
        try {
            var blob = await Cam.snap(video, 800, 0.9);
            if (!blob) { throw new Error('Kamera belum siap'); }
            var r = await fetch(window.KIOSK.endpoint + '?sesi=' + encodeURIComponent(nama), {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'image/jpeg', 'X-CSRF-Token': window.KIOSK.csrf },
                body: blob
            });
            var d = await r.json().catch(function () { return {}; });

            if (r.status === 401) {
                show('bad', 'Sesi login berakhir', 'Muat ulang halaman lalu login kembali.'); auto.checked = false; return;
            }
            if (r.status === 403) { show('bad', 'Token keamanan tidak valid', 'Muat ulang halaman.'); auto.checked = false; return; }
            if (r.status === 503) {
                show('bad', 'Layanan pengenalan tidak tersedia', d.pesan || 'Pastikan server Python berjalan.');
                addLog('Layanan pengenalan tidak tersedia', 'bad'); next = 6000; return;
            }
            switch (d.status) {
                case 'hadir':
                    show('ok', 'Hadir ✔', (d.nama || '') + ' (' + (d.id_pengguna || '') + ') — ' + Math.round((d.confidence || 0) * 100) + '%');
                    addLog('Hadir: ' + (d.nama || d.id_pengguna), 'ok'); next = 5000; break;
                case 'sudah_presensi':
                    show('warn', 'Sudah presensi', (d.nama || '') + ' sudah tercatat pada ' + (d.sesi || 'sesi ini') + '.');
                    addLog('Sudah presensi: ' + (d.nama || d.id_pengguna), 'warn'); next = 5000; break;
                case 'tidak_dikenal':
                    show('bad', 'Tidak dikenali', 'Wajah belum terdaftar atau keyakinan terlalu rendah. Coba lagi atau hubungi admin.');
                    addLog('Tidak dikenali', 'bad'); next = 3500; break;
                case 'tidak_ada_wajah':
                    show('idle', 'Wajah tidak terdeteksi', 'Hadapkan wajah ke kamera.'); break;
                default:
                    show('bad', 'Terjadi kesalahan', d.pesan || ('Kode: ' + (d.status || r.status))); next = 5000;
            }
        } catch (e) {
            show('bad', 'Gagal memproses', e && e.message ? e.message : 'Periksa koneksi ke server.');
            next = 5000;
        } finally {
            busy = false; btnSnap.disabled = !running; schedule(next);
        }
    }

    async function startCamera() {
        try {
            await Cam.start(video, sel.value || null);
            running = true;
            btnStart.textContent = 'Matikan kamera';
            btnSnap.disabled = false; auto.disabled = false;
            await Cam.fillSelect(sel);
            show('idle', 'Siap', 'Tekan "Presensi sekarang" atau aktifkan mode otomatis.');
        } catch (e) {
            show('bad', 'Kamera tidak dapat dibuka', Cam.explain(e));
        }
    }

    function stopCamera() {
        running = false; clearTimeout(timer); Cam.stop(); video.srcObject = null;
        btnStart.textContent = 'Nyalakan kamera';
        btnSnap.disabled = true; auto.checked = false; auto.disabled = true;
        show('idle', 'Menunggu', 'Nyalakan kamera untuk mulai.');
    }

    btnStart.addEventListener('click', function () { running ? stopCamera() : startCamera(); });
    btnSnap.addEventListener('click', capture);
    auto.addEventListener('change', function () { auto.checked ? schedule(300) : clearTimeout(timer); });
    sel.addEventListener('change', function () { if (running) { startCamera(); } });
    window.addEventListener('beforeunload', function () { Cam.stop(); });
})();
