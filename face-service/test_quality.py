import unittest
from pathlib import Path
import cv2
import numpy as np
from fastapi.testclient import TestClient
from app.main import app, check_face_quality, FaceError


class QualityTests(unittest.TestCase):
    def setUp(self):
        self.face = [50, 50, 200, 200]

    def assert_quality_error(self, image, face, code):
        with self.assertRaises(FaceError) as error:
            check_face_quality(image, face)
        self.assertEqual(error.exception.code, code)

    def test_dark_bright_blurry_small_and_clipped_faces_have_specific_errors(self):
        self.assert_quality_error(np.full((320, 320, 3), 10, np.uint8), self.face, 'face_too_dark')
        self.assert_quality_error(np.full((320, 320, 3), 245, np.uint8), self.face, 'face_too_bright')
        self.assert_quality_error(np.full((320, 320, 3), 120, np.uint8), self.face, 'face_blurry')
        self.assert_quality_error(np.full((320, 320, 3), 120, np.uint8), [20, 20, 40, 40], 'face_too_small')
        self.assert_quality_error(np.full((320, 320, 3), 120, np.uint8), [-10, 40, 200, 200], 'face_clipped')

    def test_quality_is_measured_on_face_not_background_or_clothes(self):
        random = np.random.default_rng(123)
        image = np.zeros((320, 320, 3), np.uint8)
        image[50:250, 50:250] = random.integers(60, 190, size=(200, 200, 3), dtype=np.uint8)
        first = check_face_quality(image, self.face)
        brighter = np.full_like(image, 255)
        brighter[50:250, 50:250] = image[50:250, 50:250]
        self.assertEqual(first, check_face_quality(brighter, self.face))

    def test_enrollment_can_require_quality_and_attendance_extraction_stays_compatible(self):
        client = TestClient(app)
        photo = (Path(__file__).parent / 'test-fixtures/lena.jpg').read_bytes()
        response = client.post('/face/enroll', data={'check_quality': 'true'}, files={'image': ('face.jpg', photo, 'image/jpeg')})
        self.assertEqual(response.status_code, 200, response.text)
        self.assertTrue(response.json()['quality_checked'])
        self.assertEqual(len(response.json()['embedding']), 128)
        response = client.post('/face/enroll', files={'image': ('face.jpg', photo, 'image/jpeg')})
        self.assertEqual(response.status_code, 200)
        self.assertFalse(response.json()['quality_checked'])


if __name__ == '__main__':
    unittest.main()
