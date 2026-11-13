<?php
namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceShiftService
{
    private function reject(string $message): void
    {
        throw ValidationException::withMessages(['type'=>$message]);
    }

    public function context(int $employeeId, int $profileId, int $branch, string $type, Carbon $time, bool $mobile=false): array
    {
        $date=$time->toDateString();
        $schedule=null;
        if ($type==='check_out') {
            // An unfinished overnight attendance belongs to its check-in work date.
            $open=DB::table('face_attendance_records as i')->where('i.employee_id',$profileId)->where('i.type','check_in')
                ->where('i.attended_at','>=',$time->copy()->subHours(36))
                ->whereNotExists(function($q) { $q->selectRaw('1')->from('face_attendance_records as o')
                    ->whereColumn('o.employee_id','i.employee_id')->whereColumn('o.attendance_date','i.attendance_date')->where('o.type','check_out'); })
                ->orderByDesc('i.attended_at')->first();
            if (!$open) $this->reject('Tidak ada absensi masuk yang masih berjalan dalam 36 jam terakhir.');
            return ['attendance_date'=>$open->attendance_date,'schedule_id'=>$open->schedule_id,
                'scheduled_start'=>$open->scheduled_start,'scheduled_end'=>$open->scheduled_end,
                'late_minutes'=>0,'early_leave_minutes'=>$open->scheduled_end ? $this->minutes($time,Carbon::parse($open->scheduled_end,$time->timezone)) : 0];
        }

        $open=DB::table('face_attendance_records as i')->where('i.employee_id',$profileId)->where('i.type','check_in')
            ->where('i.attended_at','>=',$time->copy()->subHours(36))
            ->whereNotExists(function($q) { $q->selectRaw('1')->from('face_attendance_records as o')->whereColumn('o.employee_id','i.employee_id')
                ->whereColumn('o.attendance_date','i.attendance_date')->where('o.type','check_out'); })->exists();
        if ($open) $this->reject('Masih ada absen masuk yang belum ditutup. Lakukan absen pulang terlebih dahulu.');
        $schedules=DB::table('employee_shift_schedules')->where('employee_id',$employeeId)
            ->whereBetween('work_date',[$time->copy()->subDay()->toDateString(),$time->copy()->addDay()->toDateString()])->get();
        // Prefer a shift whose attendance window is open, including tomorrow's early shift.
        foreach ($schedules->sortBy('starts_at') as $candidate) {
            if (!$candidate->is_day_off && $time->between(Carbon::parse($candidate->starts_at,$time->timezone)->subHours(4),Carbon::parse($candidate->ends_at,$time->timezone))) {
                $schedule=$candidate;break;
            }
        }
        if (!$schedule) $schedule=$schedules->firstWhere('work_date',$date);
        if ($schedule) {
            $date=$schedule->work_date;
            if (!$mobile && (int)$schedule->branch_store_id!==$branch) $this->reject('Jadwal pegawai berada di cabang lain.');
            if ($schedule->is_day_off) $this->reject('Pegawai dijadwalkan libur pada tanggal ini.');
            if (!$time->between(Carbon::parse($schedule->starts_at,$time->timezone)->subHours(4),Carbon::parse($schedule->ends_at,$time->timezone))) {
                $this->reject('Absen masuk tersedia mulai 4 jam sebelum shift sampai jam akhir shift.');
            }
        }
        if (DB::table('employee_attendance_leaves')->where('employee_id',$employeeId)->where('status','approved')
            ->where('start_date','<=',$date)->where('end_date','>=',$date)->exists()) $this->reject('Pegawai sedang izin/cuti yang sudah disetujui.');
        return ['attendance_date'=>$date,'schedule_id'=>$schedule->id ?? null,'scheduled_start'=>$schedule->starts_at ?? null,
            'scheduled_end'=>$schedule->ends_at ?? null,'late_minutes'=>$schedule ? $this->minutes(Carbon::parse($schedule->starts_at,$time->timezone)->addMinutes($schedule->grace_minutes),$time) : 0,
            'early_leave_minutes'=>0];
    }

    private function minutes(Carbon $start, Carbon $end): int
    {
        return max(0,(int)floor(($end->timestamp-$start->timestamp)/60));
    }
}
