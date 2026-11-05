"""CPU-only YuNet detection and SFace 1:1 verification; no employee data."""
import json
import logging
import os
from pathlib import Path
from threading import Lock

import cv2
import numpy as np
from fastapi import FastAPI, File, Form, UploadFile
from fastapi.responses import JSONResponse
from starlette.concurrency import run_in_threadpool

MODEL = 'opencv-sface-2021dec'
MODEL_DIR = Path(__file__).resolve().parents[1] / 'models'
MAX_BYTES = 5 * 1024 * 1024
MAX_PIXELS = 12_000_000
THRESHOLD = float(os.getenv('FACE_MATCH_THRESHOLD', '0.363'))
DETECTION_THRESHOLD = float(os.getenv('FACE_DETECTION_THRESHOLD', '0.9'))
if not -1 <= THRESHOLD <= 1 or not 0 < DETECTION_THRESHOLD <= 1:
    raise ValueError('Invalid face threshold configuration')
app = FastAPI(title='Face Attendance Service')
lock = Lock()
detector = recognizer = None


class FaceError(Exception):
    def __init__(self, message, count=0, status=422, code='face_error'):
        self.message, self.count, self.status, self.code = message, count, status, code


@app.exception_handler(FaceError)
async def face_error(_request, error):
    return JSONResponse(status_code=error.status, content={
        'success': False, 'face_count': error.count, 'message': error.message, 'code': error.code})


def load_models():
    global detector, recognizer
    if detector is None or recognizer is None:
        detector = cv2.FaceDetectorYN.create(
            str(MODEL_DIR / 'face_detection_yunet_2023mar.onnx'), '', (320, 320),
            DETECTION_THRESHOLD, 0.3, 5000, cv2.dnn.DNN_BACKEND_OPENCV, cv2.dnn.DNN_TARGET_CPU)
        recognizer = cv2.FaceRecognizerSF.create(
            str(MODEL_DIR / 'face_recognition_sface_2021dec.onnx'), '',
            cv2.dnn.DNN_BACKEND_OPENCV, cv2.dnn.DNN_TARGET_CPU)


@app.get('/health')
def health():
    try:
        with lock:
            load_models()
        return {'status': 'ok', 'model': MODEL}
    except Exception:
        logging.exception('Model loading failed')
        return JSONResponse(status_code=503, content={'status': 'unavailable', 'message': 'Model belum tersedia atau gagal dimuat.'})


async def read_image(upload):
    raw = await upload.read(MAX_BYTES + 1)
    await upload.close()
    if not raw or len(raw) > MAX_BYTES:
        raise FaceError('Foto kosong atau melebihi batas 5 MB.')
    return raw


def check_face_quality(image, face):
    """Enrollment-only checks on the detected face, not clothing/background."""
    height, width = image.shape[:2]
    x, y, w, h = [float(value) for value in face[:4]]
    if min(w, h) < 80 or w * h / (width * height) < 0.025:
        raise FaceError('Wajah terlalu kecil. Dekatkan kamera dan ambil foto ulang.', 1, code='face_too_small')
    if x < 0 or y < 0 or x + w > width or y + h > height:
        raise FaceError('Wajah terpotong. Pastikan seluruh wajah masuk bingkai.', 1, code='face_clipped')
    crop = image[int(y):int(y+h), int(x):int(x+w)]
    gray = cv2.cvtColor(cv2.resize(crop, (160, 160)), cv2.COLOR_BGR2GRAY)
    brightness = float(np.mean(gray))
    if brightness < 40:
        raise FaceError('Wajah terlalu gelap. Gunakan cahaya dari depan.', 1, code='face_too_dark')
    if brightness > 220:
        raise FaceError('Wajah terlalu terang. Hindari cahaya langsung yang berlebihan.', 1, code='face_too_bright')
    sharpness = float(cv2.Laplacian(gray, cv2.CV_64F).var())
    if sharpness < 25:
        raise FaceError('Foto wajah buram. Tahan HP agar stabil dan ambil foto ulang.', 1, code='face_blurry')
    return {'brightness': round(brightness, 1), 'sharpness': round(sharpness, 1)}


def embedding(raw, check_quality=False):
    try:
        # Decode only bounded JPEG/PNG inputs. Laravel sends JPEG camera captures.
        if not (raw.startswith(b'\xff\xd8\xff') or raw.startswith(b'\x89PNG\r\n\x1a\n')):
            raise FaceError('Format gambar tidak valid. Gunakan JPEG atau PNG.')
        image = cv2.imdecode(np.frombuffer(raw, np.uint8), cv2.IMREAD_COLOR)
        if image is None or image.size == 0:
            raise FaceError('Gambar rusak atau tidak valid.')
        h, w = image.shape[:2]
        if h * w > MAX_PIXELS:
            raise FaceError('Resolusi foto terlalu besar.')
        scale = min(1.0, 1280 / max(h, w))
        if scale < 1:
            image = cv2.resize(image, (round(w * scale), round(h * scale)))
        # OpenCV networks are mutable; serialize detection, alignment and inference.
        with lock:
            load_models()
            detector.setInputSize((image.shape[1], image.shape[0]))
            _, faces = detector.detect(image)
            count = 0 if faces is None else len(faces)
            if count != 1:
                raise FaceError('Tidak ada wajah terdeteksi.' if count == 0 else 'Harus tepat satu wajah dalam foto.', count, code='no_face' if count == 0 else 'multiple_faces')
            if check_quality:
                check_face_quality(image, faces[0])
            aligned = recognizer.alignCrop(image, faces[0])
            vector = recognizer.feature(aligned).flatten().astype(np.float64)
        norm = np.linalg.norm(vector)
        if vector.size != 128 or not np.all(np.isfinite(vector)) or norm <= 0:
            raise RuntimeError('Invalid model embedding')
        return vector / norm
    except FaceError:
        raise
    except Exception:
        logging.exception('Face inference failed')
        raise FaceError('Model wajah gagal diproses. Periksa model dan service.', status=503)


@app.post('/face/enroll')
async def enroll(image: UploadFile = File(...), check_quality: bool = Form(False)):
    raw = await read_image(image)
    # CPU work runs in a worker thread, keeping the event loop responsive.
    vector = await run_in_threadpool(embedding, raw, check_quality)
    return {'success': True, 'face_count': 1, 'embedding': vector.tolist(), 'model': MODEL, 'quality_checked': check_quality, 'message': 'Face enrolled'}


@app.post('/face/verify')
async def verify(image: UploadFile = File(...), reference_embedding: str = Form(..., max_length=12000)):
    try:
        parsed = json.loads(reference_embedding)
        if not isinstance(parsed, list) or len(parsed) != 128 or any(type(x) not in (int, float) for x in parsed):
            raise ValueError('Invalid vector')
        reference = np.asarray(parsed, dtype=np.float64)
        norm = np.linalg.norm(reference)
        if not np.all(np.isfinite(reference)) or not np.isfinite(norm) or norm <= 0:
            raise ValueError('Invalid norm')
        reference /= norm
    except (ValueError, TypeError, OverflowError):
        raise FaceError('Reference embedding tidak valid.')
    vector = await run_in_threadpool(embedding, await read_image(image))
    score = float(np.clip(np.dot(reference, vector), -1, 1))
    distance = float(np.linalg.norm(reference - vector))
    matched = score >= THRESHOLD
    return {'success': True, 'face_count': 1, 'matched': matched,
            'score': score, 'distance': distance, 'model': MODEL,
            'message': 'Face verified' if matched else 'Wajah tidak cocok.'}
