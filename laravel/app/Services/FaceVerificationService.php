<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FaceVerificationService
{
    public function process(string $action, string $image, ?array $embedding = null): array
    {
        try {
            $response = Http::connectTimeout(3)->timeout(config('face.timeout'))->acceptJson()
                ->attach('image', $image, 'capture.jpg')
                ->post(rtrim(config('face.url'), '/').'/face/'.$action,
                    $embedding === null ? ['check_quality'=>'true'] : ['reference_embedding' => json_encode($embedding)]);
        } catch (ConnectionException $e) {
            report($e);
            throw ValidationException::withMessages(['photo' => 'Verifikasi wajah belum dapat diproses. Silakan coba kembali.']);
        }
        $data = $response->json();
        if (! $response->successful() || ! is_array($data) || ($data['success'] ?? false) !== true) {
            $code=is_string($data['code'] ?? null) ? $data['code'] : '';
            $messages=[
                'no_face'=>'Wajah belum terlihat. Posisikan wajah di depan kamera.',
                'multiple_faces'=>'Pastikan hanya satu wajah berada dalam foto.',
                'face_too_small'=>'Dekatkan wajah ke kamera dan ambil foto ulang.',
                'face_clipped'=>'Pastikan seluruh wajah masuk bingkai kamera.',
                'face_too_dark'=>'Gunakan pencahayaan dari depan lalu ambil foto ulang.',
                'face_too_bright'=>'Kurangi cahaya langsung lalu ambil foto ulang.',
                'face_blurry'=>'Foto buram. Tahan kamera agar stabil dan ambil foto ulang.',
            ];
            Log::warning('Face verification failed',['status'=>$response->status(),'code'=>$code,'detail'=>$data['message'] ?? null]);
            throw ValidationException::withMessages(['photo' => $response->status()<500 ? ($messages[$code] ?? 'Foto belum dapat diproses. Ambil foto ulang atau hubungi pengelola.') : 'Verifikasi wajah belum dapat diproses. Silakan coba kembali.']);
        }
        $valid = ($data['face_count'] ?? null) === 1 && ($data['model'] ?? null) === config('face.model');
        if ($action === 'enroll') {
            $valid = $valid && ($data['quality_checked'] ?? false) === true;
            $vector = $data['embedding'] ?? null;
            $valid = $valid && is_array($vector) && array_is_list($vector) && count($vector) === 128;
            foreach (is_array($vector) ? $vector : [] as $v) {
                $valid = $valid && is_numeric($v) && is_finite((float) $v);
            }
        } else {
            $valid = $valid && is_bool($data['matched'] ?? null) && is_numeric($data['score'] ?? null)
             && is_numeric($data['distance'] ?? null) && is_finite((float) $data['score']) && is_finite((float) $data['distance']);
        }
        if (! $valid) {
            Log::warning('Invalid face verification response');
            throw ValidationException::withMessages(['photo' => 'Foto belum dapat diproses. Ambil foto ulang atau hubungi pengelola.']);
        }

        return $data;
    }
}
