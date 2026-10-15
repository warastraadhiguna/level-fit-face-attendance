"""Download official OpenCV Zoo models explicitly; never download during requests."""
from pathlib import Path
from urllib.request import urlopen
import hashlib

BASE = 'https://media.githubusercontent.com/media/opencv/opencv_zoo/main/models/'
MODELS = {
 'face_detection_yunet_2023mar.onnx': 'face_detection_yunet',
 'face_recognition_sface_2021dec.onnx': 'face_recognition_sface',
}
HASHES = {
 'face_detection_yunet_2023mar.onnx': '8f2383e4dd3cfbb4553ea8718107fc0423210dc964f9f4280604804ed2552fa4',
 'face_recognition_sface_2021dec.onnx': '0ba9fbfa01b5270c96627c4ef784da859931e02f04419c829e83484087c34e79',
}
root = Path(__file__).resolve().parent / 'models'
root.mkdir(exist_ok=True)
for name, directory in MODELS.items():
    target = root / name
    if target.exists() and hashlib.sha256(target.read_bytes()).hexdigest() == HASHES[name]:
        print(f'Already present: {name}')
        continue
    temporary = target.with_suffix('.part')
    try:
        with urlopen(BASE + directory + '/' + name, timeout=120) as response, temporary.open('wb') as output:
            while chunk := response.read(1024 * 1024):
                output.write(chunk)
        if hashlib.sha256(temporary.read_bytes()).hexdigest() != HASHES[name]:
            raise RuntimeError('Download is not a model binary')
        temporary.replace(target)
        print(name, 'SHA256:', hashlib.sha256(target.read_bytes()).hexdigest())
    finally:
        temporary.unlink(missing_ok=True)
