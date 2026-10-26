/**
 * Pembantu kamera (webcam) berbasis getUserMedia.
 * Catatan: browser hanya mengizinkan kamera di http://localhost atau HTTPS.
 */
var Cam = {
    stream: null,

    supported: function () {
        return !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    },

    /** Daftar kamera. Label baru terisi setelah izin kamera diberikan. */
    list: async function () {
        var all = await navigator.mediaDevices.enumerateDevices();
        return all.filter(function (d) { return d.kind === 'videoinput'; });
    },

    start: async function (video, deviceId) {
        this.stop();
        var v = { width: { ideal: 1280 }, height: { ideal: 720 } };
        if (deviceId) { v.deviceId = { exact: deviceId }; } else { v.facingMode = 'user'; }
        this.stream = await navigator.mediaDevices.getUserMedia({ video: v, audio: false });
        video.srcObject = this.stream;
        await video.play();
    },

    stop: function () {
        if (this.stream) {
            this.stream.getTracks().forEach(function (t) { t.stop(); });
            this.stream = null;
        }
    },

    /** Ambil satu frame sebagai Blob JPEG (sisi terpanjang dibatasi maxSide). */
    snap: function (video, maxSide, quality) {
        maxSide = maxSide || 800;
        quality = quality || 0.92;
        var w = video.videoWidth, h = video.videoHeight;
        if (!w || !h) { return Promise.resolve(null); }
        var k = Math.min(1, maxSide / Math.max(w, h));
        var c = document.createElement('canvas');
        c.width = Math.round(w * k);
        c.height = Math.round(h * k);
        c.getContext('2d').drawImage(video, 0, 0, c.width, c.height);
        return new Promise(function (res) { c.toBlob(res, 'image/jpeg', quality); });
    },

    /** Pesan galat yang mudah dipahami. */
    explain: function (e) {
        if (!Cam.supported()) {
            return 'Kamera hanya dapat dipakai lewat http://localhost atau HTTPS. Buka halaman ini dari alamat tersebut.';
        }
        var n = e && e.name;
        if (n === 'NotAllowedError' || n === 'SecurityError') { return 'Izin kamera ditolak. Klik ikon kamera/gembok di bilah alamat lalu izinkan.'; }
        if (n === 'NotFoundError' || n === 'OverconstrainedError') { return 'Kamera tidak ditemukan. Sambungkan webcam lalu coba lagi.'; }
        if (n === 'NotReadableError') { return 'Kamera sedang dipakai aplikasi lain (Zoom/Meet/dll). Tutup aplikasi itu lalu coba lagi.'; }
        return 'Kamera tidak dapat dibuka' + (e && e.message ? ' (' + e.message + ')' : '') + '.';
    },

    /** Isi <select> dengan daftar kamera. */
    fillSelect: async function (sel) {
        var devs = await this.list();
        var cur = sel.value;
        sel.innerHTML = '';
        devs.forEach(function (d, i) {
            var o = document.createElement('option');
            o.value = d.deviceId;
            o.textContent = d.label || ('Kamera ' + (i + 1));
            sel.appendChild(o);
        });
        if (cur) { sel.value = cur; }
        sel.style.display = devs.length > 1 ? '' : 'none';
    }
};
