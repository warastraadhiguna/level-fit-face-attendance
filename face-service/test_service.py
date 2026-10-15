import unittest
import cv2
import numpy as np
from fastapi.testclient import TestClient
from app.main import app

class ServiceTests(unittest.TestCase):
    def setUp(self):
        self.client = TestClient(app)
    def test_health_loads_real_models(self):
        response = self.client.get('/health')
        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.json()['status'], 'ok')
    def test_corrupt_image(self):
        response = self.client.post('/face/enroll', files={'image': ('bad.jpg', b'\xff\xd8\xffbad', 'image/jpeg')})
        self.assertEqual(response.status_code, 422)
        self.assertFalse(response.json()['success'])
    def test_zero_faces(self):
        _, image = cv2.imencode('.jpg', np.zeros((320,320,3), np.uint8))
        response = self.client.post('/face/enroll', files={'image': ('blank.jpg', image.tobytes(), 'image/jpeg')})
        self.assertEqual(response.status_code, 422)
        self.assertEqual(response.json()['face_count'], 0)
    def test_invalid_reference(self):
        response = self.client.post('/face/verify', data={'reference_embedding': '[1,2]'}, files={'image': ('bad.jpg', b'invalid', 'image/jpeg')})
        self.assertEqual(response.status_code, 422)
    def test_real_enrollment_and_comparison(self):
        from pathlib import Path
        fixture = Path(__file__).parent / 'test-fixtures' / 'lena.jpg'
        if not fixture.exists():
            self.skipTest('Optional OpenCV face fixture unavailable')
        photo = fixture.read_bytes()
        enrollment = self.client.post('/face/enroll', files={'image': ('face.jpg',photo,'image/jpeg')})
        self.assertEqual(enrollment.status_code,200)
        vector = enrollment.json()['embedding']
        import json
        verified = self.client.post('/face/verify',data={'reference_embedding':json.dumps(vector)},files={'image':('face.jpg',photo,'image/jpeg')})
        self.assertTrue(verified.json()['matched'])
        self.assertGreater(verified.json()['score'],0.99)
        rejected = self.client.post('/face/verify',data={'reference_embedding':json.dumps([-x for x in vector])},files={'image':('face.jpg',photo,'image/jpeg')})
        self.assertFalse(rejected.json()['matched'])
        img=cv2.imdecode(np.frombuffer(photo,np.uint8),cv2.IMREAD_COLOR)
        _,two=cv2.imencode('.jpg',np.concatenate([img,img],axis=1))
        multiple=self.client.post('/face/enroll',files={'image':('two.jpg',two.tobytes(),'image/jpeg')})
        self.assertEqual(multiple.status_code,422)
        self.assertEqual(multiple.json()['face_count'],2)

if __name__ == '__main__':
    unittest.main()
