// Tampilkan / sembunyikan password di halaman login
function togglePw() {
    var el = document.getElementById('password');
    if (!el) return;
    el.type = el.type === 'password' ? 'text' : 'password';
}

// Konfirmasi untuk form/tautan berbahaya: <form data-confirm="Pesan ...">
document.addEventListener('submit', function (ev) {
    var msg = ev.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) {
        ev.preventDefault();
    }
});

// Alert otomatis hilang setelah beberapa detik (kecuali bahaya)
window.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.alert-success').forEach(function (a) {
        setTimeout(function () { a.style.display = 'none'; }, 6000);
    });
});

// Pratinjau nama berkas pada unggah foto
document.addEventListener('change', function (ev) {
    var t = ev.target;
    if (t && t.matches && t.matches('input[type=file][data-preview]')) {
        var out = document.getElementById(t.getAttribute('data-preview'));
        if (out) {
            out.textContent = t.files.length + ' berkas dipilih';
        }
    }
});
