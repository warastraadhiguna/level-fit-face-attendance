<?php

namespace App\Services;

use App\Models\FaceProfile;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FaceRecognitionService
{
    public function __construct(private FaceVerificationService $faces) {}

    public function identify(array $images, int $branchId): array
    {
        $threshold = config('face.recognition_threshold');
        $margin = config('face.recognition_margin');
        if (! is_finite($threshold) || $threshold <= 0 || $threshold > 1 || ! is_finite($margin) || $margin <= 0 || $margin > 2) {
            throw ValidationException::withMessages(['photo' => 'Absensi belum dapat diproses. Silakan hubungi pengelola.']);
        }
        $employees = FaceProfile::with(['samples'=>fn ($q) => $q->where('face_model',config('face.model'))])
            ->whereHas('samples',fn ($q) => $q->where('face_model',config('face.model')))
            ->whereIn('source_employee_id', \App\Models\Employee::availableInBranch($branchId)->select('id')->where('is_active',true))->get();
        if ($employees->isEmpty()) {
            throw ValidationException::withMessages(['photo' => 'Wajah belum dikenali. Hubungi pengelola untuk memeriksa pendaftaran wajah.']);
        }
        if (count($images) !== 3) {
            throw ValidationException::withMessages(['photo' => 'Ambil ulang foto untuk pemeriksaan wajah berurutan.']);
        }
        $confirmed = null;
        foreach ($images as $image) {
            $match = $this->match($image, $employees, $branchId, $threshold, $margin);
            if ($confirmed && $confirmed['employee']->id !== $match['employee']->id) {
                Log::warning('Attendance identity changed between captures', ['branch_id'=>$branchId,
                    'profile_ids'=>[$confirmed['employee']->id,$match['employee']->id]]);
                throw ValidationException::withMessages(['photo' => 'Identitas wajah belum konsisten. Absensi tidak disimpan. Ambil foto ulang.']);
            }
            if (!$confirmed || $match['score'] < $confirmed['score']) $confirmed = $match;
        }
        $confirmed['sample_snapshot'] = $confirmed['employee']->samples->sortBy('id')->mapWithKeys(
            fn ($sample) => [$sample->id => hash('sha256',$sample->face_model.'|'.$sample->getRawOriginal('face_embedding'))]
        )->all();
        return $confirmed;
    }

    private function match(string $image, $employees, int $branchId, float $threshold, float $margin): array
    {
        $probe = $this->faces->process('enroll', $image)['embedding'];
        $probeNorm = sqrt(array_sum(array_map(fn ($v) => $v * $v, $probe)));
        if ($probeNorm <= 0 || ! is_finite($probeNorm)) {
            throw ValidationException::withMessages(['photo' => 'Foto belum dapat diproses. Silakan ambil foto ulang.']);
        }
        $ranked = [];
        foreach ($employees as $employee) {
            $scores = [];
            foreach ($employee->samples as $sample) {
                $reference = $sample->face_embedding;
                if (!is_array($reference) || !array_is_list($reference) || count($reference)!==128) continue;
                $dot=0;$norm=0;
                foreach ($reference as $i=>$value) {
                    if (!is_numeric($value) || !is_finite((float)$value)) { $norm=0;break; }
                    $dot+=$probe[$i]*$value;$norm+=$value*$value;
                }
                if ($norm<=0 || !is_finite($norm)) continue;
                $score=max(-1.0,min(1.0,$dot/($probeNorm*sqrt($norm))));
                $scores[]=['score'=>$score,'sample_id'=>$sample->id];
            }
            if ($scores) {
                usort($scores, fn ($a,$b) => $b['score'] <=> $a['score']);
                // A majority of registered samples must support the identity;
                // one incorrectly registered outlier must not decide attendance.
                $supported = $scores[intdiv(count($scores),2)];
                $ranked[]=['employee'=>$employee,'sample_id'=>$scores[0]['sample_id'],
                    'score'=>$supported['score'],'strongest_score'=>$scores[0]['score'],
                    'distance'=>sqrt(max(0,2-2*$supported['score']))];
            }
        }

        usort($ranked, fn ($a, $b) => $b['score'] <=> $a['score']);
        $best = $ranked[0] ?? null;
        Log::info('Attendance face decision', ['branch_id'=>$branchId,'threshold'=>$threshold,'margin'=>$margin,
            'candidates'=>array_map(fn ($item) => ['profile_id'=>$item['employee']->id,
                'sample_id'=>$item['sample_id'],'score'=>$item['score'],'strongest_score'=>$item['strongest_score']],array_slice($ranked,0,2))]);
        if (! $best || $best['score'] < $threshold) {
            throw ValidationException::withMessages(['photo' => '✕ WAJAH TIDAK DIKENALI DI CABANG INI — absensi tidak disimpan.']);
        }
        $rivals = array_filter($ranked, fn ($item) => $item['employee']->id !== $best['employee']->id);
        if (array_filter($rivals, fn ($item) => $item['strongest_score'] >= $threshold)
            || (isset($ranked[1]) && $margin > $best['score'] - $ranked[1]['score'])) {
            throw ValidationException::withMessages(['photo' => '✕ IDENTITAS AMBIGU — Ambil foto ulang atau hubungi pengelola.']);
        }

        return $best;
    }
}
