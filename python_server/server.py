"""
Server pengenalan wajah (Flask) untuk panel Presensi Wajah CNN.

Tugasnya HANYA mengenali wajah: terima 1 gambar JPEG -> deteksi wajah -> crop 160x160 ->
prediksi MobileNetV2 -> kembalikan identitas + confidence. Threshold, pencegahan presensi ganda,
dan penyimpanan dilakukan oleh panel PHP.

Jalankan:
    pip install -r requirements.txt
    set PY_SERVER_TOKEN=rahasia123        (Windows)   |   export PY_SERVER_TOKEN=rahasia123  (Linux/macOS)
    python server.py

Berkas yang dibutuhkan (hasil pelatihan Anda):
    model/mobilenetv2.h5   model Keras (input 160x160 RGB)
    model/labels.json      daftar nama kelas sesuai urutan output model, mis. ["2021001_Budi", "2021002_Sari"]
                           (folder dataset hasil unduhan panel memakai format NIM_Nama)

PENTING: praproses di bawah (pixel/127.5 - 1, gaya MobileNetV2) harus SAMA dengan yang dipakai saat pelatihan.
"""
import json
import os

import cv2
import numpy as np
from flask import Flask, jsonify, request

BASE = os.path.dirname(os.path.abspath(__file__))
MODEL_PATH = os.environ.get("MODEL_PATH", os.path.join(BASE, "model", "mobilenetv2.h5"))
LABELS_PATH = os.environ.get("LABELS_PATH", os.path.join(BASE, "model", "labels.json"))
TOKEN = os.environ.get("PY_SERVER_TOKEN", "")
MAX_BYTES = 2 * 1024 * 1024
IMG_SIZE = 160
MIN_FACE = 80  # piksel; wajah lebih kecil dari ini diabaikan

app = Flask(__name__)
app.config["MAX_CONTENT_LENGTH"] = MAX_BYTES

if not hasattr(cv2, "CascadeClassifier"):
    raise SystemExit(
        'OpenCV versi ini tidak menyertakan CascadeClassifier (OpenCV 5). '
        'Jalankan: pip install "opencv-python>=4.8,<5"'
    )
_face = cv2.CascadeClassifier(cv2.data.haarcascades + "haarcascade_frontalface_default.xml")
if _face.empty():
    raise SystemExit("Berkas haarcascade_frontalface_default.xml tidak ditemukan pada instalasi OpenCV.")
_model = None
_labels = None


def load_resources():
    """Muat model & label sekali (malas), supaya /health tetap bisa jalan tanpa model."""
    global _model, _labels
    if _model is None:
        from tensorflow.keras.models import load_model  # impor di sini agar start server cepat
        _model = load_model(MODEL_PATH)
        with open(LABELS_PATH, encoding="utf-8") as f:
            _labels = json.load(f)
        if _model.output_shape[-1] != len(_labels):
            raise RuntimeError(
                f"Jumlah kelas model ({_model.output_shape[-1]}) tidak sama dengan labels.json ({len(_labels)})"
            )
    return _model, _labels


def authorized():
    if not TOKEN:
        return True  # tanpa token (hanya untuk uji lokal)
    return request.headers.get("Authorization", "") == f"Bearer {TOKEN}"


@app.get("/health")
def health():
    return jsonify(status="ok", model_loaded=os.path.exists(MODEL_PATH) and os.path.exists(LABELS_PATH))


@app.post("/presensi")
def presensi():
    if not authorized():
        return jsonify(status="tidak_diizinkan"), 401

    data = request.get_data()
    if not data:
        return jsonify(status="gambar_tidak_valid"), 400
    img = cv2.imdecode(np.frombuffer(data, np.uint8), cv2.IMREAD_COLOR)
    if img is None:
        return jsonify(status="gambar_tidak_valid"), 400

    gray = cv2.equalizeHist(cv2.cvtColor(img, cv2.COLOR_BGR2GRAY))
    faces = _face.detectMultiScale(gray, scaleFactor=1.1, minNeighbors=5, minSize=(MIN_FACE, MIN_FACE))
    if len(faces) == 0:
        return jsonify(status="tidak_ada_wajah")

    # pakai wajah terbesar, tambah margin 15%
    x, y, w, h = max(faces, key=lambda r: r[2] * r[3])
    m = int(0.15 * max(w, h))
    x0, y0 = max(0, x - m), max(0, y - m)
    x1, y1 = min(img.shape[1], x + w + m), min(img.shape[0], y + h + m)
    face = cv2.cvtColor(img[y0:y1, x0:x1], cv2.COLOR_BGR2RGB)
    face = cv2.resize(face, (IMG_SIZE, IMG_SIZE)).astype("float32") / 127.5 - 1.0

    try:
        model, labels = load_resources()
    except Exception as exc:  # model belum ada / rusak
        app.logger.error("Gagal memuat model: %s", exc)
        return jsonify(status="model_tidak_tersedia"), 503

    probs = model.predict(face[None, ...], verbose=0)[0]
    i = int(np.argmax(probs))
    return jsonify(status="ok", id_pengguna=labels[i], confidence=round(float(probs[i]), 4))


if __name__ == "__main__":
    # 127.0.0.1 = hanya bisa diakses komputer ini. Ganti ke 0.0.0.0 bila PHP berada di komputer lain.
    app.run(host="127.0.0.1", port=int(os.environ.get("PORT", "5000")))
