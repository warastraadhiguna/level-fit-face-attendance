<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class FaceVerificationService
{
    public function process(string $action, string $image, ?array $embedding = null): array
    {
        try {
            $response = Http::connectTimeout(3)->timeout(config('face.timeout'))->acceptJson()
                ->attach('image', $image, 'capture.jpg')
                ->post(rtrim(config('face.url'), '/').'/face/'.$action,
                    $embedding === null ? [] : ['reference_embedding' => json_encode($embedding)]);
        } catch (ConnectionException $e) {
            throw ValidationException::withMessages(['photo' => 'Face verification service sedang tidak tersedia.']);
        }
        $data = $response->json();
        if (! $response->successful() || ! is_array($data) || ($data['success'] ?? false) !== true) {
            throw ValidationException::withMessages(['photo' => is_string($data['message'] ?? null) ? $data['message'] : 'Face verification service sedang tidak tersedia.']);
        }
        $valid = ($data['face_count'] ?? null) === 1 && ($data['model'] ?? null) === config('face.model');
        if ($action === 'enroll') {
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
            throw ValidationException::withMessages(['photo' => 'Respons face service tidak valid.']);
        }

        return $data;
    }
}
