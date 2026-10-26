/* Registrasi wajah dari webcam: ambil beberapa foto, tinjau, lalu simpan sekaligus. */
(function () {
    var $ = function (id) { return document.getElementById(id); };
    var video = $('cam-video'), btnStart = $('cam-start'), btnSnap = $('cam-snap'),
        btnSave = $('cam-save'), thumbs = $('cam-thumbs'), msg = $('cam-msg'), sel = $('cam-select');
    if (!video || !window.WAJAH) { return; }
    var queue = [], running = false;

    function refresh() {
        btnSave.textContent = queue.length ? 'Simpan ' + queue.length + ' foto' : 'Simpan foto';
        btnSave.disabled = queue.length === 0;
        btnSnap.disabled = !running || queue.length >= window.WAJAH.sisa;
        if (running && queue.length >= window.WAJAH.sisa) {
            msg.textContent = 'Batas jumlah foto per mahasiswa tercapai. Simpan atau hapus foto lama dulu.';
        }
    }

    function addThumb(item) {
        var fig = document.createElement('figure');
        var img = document.createElement('img'); img.src = item.url; img.alt = 'Foto kamera';
        var cap = document.createElement('figcaption');
        var sp = document.createElement('span'); sp.textContent = 'Belum disimpan';
        var del = document.createElement('button'); del.type = 'button'; del.className = 'btn btn-sm btn-danger'; del.textContent = 'Hapus';
        del.addEventListener('click', function () {
            queue.splice(queue.indexOf(item), 1); URL.revokeObjectURL(item.url); fig.remove(); refresh();
        });
        cap.appendChild(sp); cap.appendChild(del); fig.appendChild(img); fig.appendChild(cap);
        thumbs.insertBefore(fig, thumbs.firstChild);
    }

    btnStart.addEventListener('click', async function () {
        if (running) {
            Cam.stop(); video.srcObject = null; running = false; btnStart.textContent = 'Nyalakan kamera'; refresh(); return;
        }
        try {
            await Cam.start(video, sel.value || null);
            running = true; btnStart.textContent = 'Matikan kamera';
            await Cam.fillSelect(sel); refresh();
        } catch (e) { msg.textContent = Cam.explain(e); }
    });
    sel.addEventListener('change', async function () {
        if (running) { try { await Cam.start(video, sel.value); } catch (e) { msg.textContent = Cam.explain(e); } }
    });

    btnSnap.addEventListener('click', async function () {
        var blob = await Cam.snap(video, 800, 0.92);
        if (!blob) { msg.textContent = 'Kamera belum siap, coba lagi sesaat lagi.'; return; }
        var item = { blob: blob, url: URL.createObjectURL(blob) };
        queue.push(item); addThumb(item); refresh();
    });

    btnSave.addEventListener('click', async function () {
        if (!queue.length) { return; }
        btnSave.disabled = true; btnSave.textContent = 'Menyimpan…';
        var fd = new FormData();
        fd.append('csrf', window.WAJAH.csrf); fd.append('aksi', 'upload'); fd.append('id', window.WAJAH.id);
        queue.forEach(function (it, i) { fd.append('foto[]', it.blob, 'kamera_' + Date.now() + '_' + i + '.jpg'); });
        try {
            // redirect 'manual': jangan ikuti pengalihan, agar pesan hasil (flash) tidak habis terbaca di sini
            var r = await fetch(location.href, { method: 'POST', body: fd, credentials: 'same-origin', redirect: 'manual' });
            if (r.type !== 'opaqueredirect' && !r.ok) { throw new Error('HTTP ' + r.status); }
            Cam.stop();
            location.reload();   // pesan hasil ditampilkan lewat flash message
        } catch (e) {
            msg.textContent = 'Gagal menyimpan: ' + e.message; refresh();
        }
    });
    window.addEventListener('beforeunload', function () { Cam.stop(); });
    refresh();
})();
