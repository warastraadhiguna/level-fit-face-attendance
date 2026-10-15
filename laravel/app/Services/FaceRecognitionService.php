<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Validation\ValidationException;

class FaceRecognitionService
{
    public function __construct(private FaceVerificationService $faces) {}

    public function identify(string $image, int $branchId): array
    {
        $threshold = config('face.recognition_threshold');
        $margin = config('face.recognition_margin');
        if (! is_finite($threshold) || $threshold <= 0 || $threshold > 1 || ! is_finite($margin) || $margin <= 0 || $margin > 2) {
            throw ValidationException::withMessages(['photo' => 'Konfigurasi pengenalan wajah tidak valid.']);
        }
        $employees = Employee::whereNotNull('face_embedding')->where('face_model', config('face.model'))
            ->whereIn('source_user_id', \Illuminate\Support\Facades\DB::table('users')->select('id')->where('branch_store_id',$branchId)->whereNull('deleted_at'))->get();
        if ($employees->isEmpty()) {
            throw ValidationException::withMessages(['photo' => 'Belum ada wajah pegawai terdaftar di cabang perangkat ini. Daftarkan wajah pada cabang yang sesuai.']);
        }
        $probe = $this->faces->process('enroll', $image)['embedding'];
        $probeNorm = sqrt(array_sum(array_map(fn ($v) => $v * $v, $probe)));
        if ($probeNorm <= 0 || ! is_finite($probeNorm)) {
            throw ValidationException::withMessages(['photo' => 'Embedding wajah tidak valid.']);
        }
        $ranked = [];
        foreach ($employees as $employee) {
            $reference = $employee->face_embedding;
            if (! is_array($reference) || ! array_is_list($reference) || count($reference) !== 128) {
                continue;
            }
            $dot = 0;
            $norm = 0;
            foreach ($reference as $i => $value) {
                if (! is_numeric($value) || ! is_finite((float) $value)) {
                    $norm = 0;
                    break;
                }
                $dot += $probe[$i] * $value;
                $norm += $value * $value;
            }
            if ($norm <= 0 || ! is_finite($norm)) {
                continue;
            }
            $score = max(-1.0, min(1.0, $dot / ($probeNorm * sqrt($norm))));
            $ranked[] = ['employee' => $employee, 'score' => $score, 'distance' => sqrt(max(0, 2 - 2 * $score))];
        }
        usort($ranked, fn ($a, $b) => $b['score'] <=> $a['score']);
        $best = $ranked[0] ?? null;
        if (! $best || $best['score'] < config('face.recognition_threshold')) {
            throw ValidationException::withMessages(['photo' => '✕ WAJAH TIDAK DIKENALI DI CABANG INI — absensi tidak disimpan.']);
        }
        if (isset($ranked[1]) && config('face.recognition_margin') > $best['score'] - $ranked[1]['score']) {
            throw ValidationException::withMessages(['photo' => '✕ IDENTITAS AMBIGU — wajah mirip dengan lebih dari satu pegawai. Ambil foto ulang atau periksa enrollment.']);
        }

        return $best;
    }
}
