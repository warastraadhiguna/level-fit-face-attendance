<?php

return ['enrollment_url' => env('FACE_ENROLLMENT_URL', 'http://localhost:8002/enroll'), 'url' => env('FACE_SERVICE_URL', 'http://127.0.0.1:8001'), 'timeout' => (int) env('FACE_SERVICE_TIMEOUT', 30), 'model' => 'opencv-sface-2021dec', 'recognition_threshold' => (float) env('FACE_RECOGNITION_THRESHOLD', 0.45), 'recognition_margin' => (float) env('FACE_RECOGNITION_MARGIN', 0.08)];
