<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Services\FaceRecognitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $device=$request->attributes->get('attendance_device');
        return view('attendance', ['enroll'=>false,'employees'=>collect(),'branchName'=>$device->branch_name,
            'history'=>Attendance::with('employee')->where('branch_store_id',$device->branch_store_id)->latest('attended_at')->limit(10)->get()]);
    }

    public function photo(Request $request, Attendance $attendance)
    {
        abort_unless((int)$attendance->branch_store_id === (int)$request->attributes->get('attendance_device')->branch_store_id,404);
        abort_unless(Storage::disk('local')->exists($attendance->photo_path),404);
        return Storage::disk('local')->response($attendance->photo_path,null,['Cache-Control'=>'no-store, private']);
    }

    private function capture(Request $request): string
    {
        $request->validate(['photo' => 'required|string|max:7000000']);
        if (! preg_match('#^data:image/jpeg;base64,([A-Za-z0-9+/=]+)$#D', $request->photo, $matches)) {
            throw ValidationException::withMessages(['photo' => 'Ambil foto melalui kamera.']);
        }
        $bytes = base64_decode($matches[1], true);
        $info = $bytes ? @getimagesizefromstring($bytes) : false;
        if (! $info || $info[2] !== IMAGETYPE_JPEG || strlen($bytes) > 5 * 1024 * 1024 || $info[0] * $info[1] > 12000000) {
            throw ValidationException::withMessages(['photo' => 'Foto kamera tidak valid atau terlalu besar.']);
        }

        return $bytes;
    }

    private function check(Employee $employee, string $type, string $date): void
    {
        $query = Attendance::where('employee_id', $employee->id)->where('attendance_date', $date);
        if ((clone $query)->where('type', $type)->exists()) {
            throw ValidationException::withMessages(['type' => 'Absensi '.($type === 'check_in' ? 'masuk' : 'pulang').' hari ini sudah tercatat.']);
        }
        if ($type === 'check_out' && ! (clone $query)->where('type', 'check_in')->exists()) {
            throw ValidationException::withMessages(['type' => 'Tidak boleh absen pulang sebelum absen masuk.']);
        }
    }

    public function attend(Request $request, FaceRecognitionService $service)
    {
        $request->validate(['type' => 'required|in:check_in,check_out']);
        $image = $this->capture($request);
        $device = $request->attributes->get('attendance_device');
        $data = $service->identify($image, (int)$device->branch_store_id);
        $employee = $data['employee'];
        $path = null;
        try {
            $attendance = DB::transaction(function () use ($employee, $device, $request, $image, $data, &$path) {
                $activeDevice=DB::table('face_attendance_devices')->where('id',$device->id)
                    ->where('token_hash',$device->token_hash)->where('expires_at','>',now('UTC'))->lockForUpdate()->first();
                abort_unless($activeDevice,403,'Akses perangkat telah dicabut. Minta Owner mengaktifkan ulang perangkat.');
                $sourceUser=DB::table('users')->where('id',$employee->source_user_id)->where('branch_store_id',$device->branch_store_id)->whereNull('deleted_at')->lockForUpdate()->first();
                abort_unless($sourceUser,403,'Pegawai tidak lagi aktif pada cabang perangkat ini.');
                // Serialize attendance writes for this employee before checking daily rules.
                DB::table('face_attendance_employees')->where('id', $employee->id)->update(['updated_at' => now()]);
                $time = now();
                $this->check($employee, $request->type, $time->toDateString());
                $path = 'attendance/'.$time->format('Y/m/d').'/'.Str::uuid().'.jpg';
                if (! Storage::disk('local')->put($path, $image)) {
                    throw new \RuntimeException('Photo storage failed');
                }

                return Attendance::create(['branch_store_id'=>$device->branch_store_id,'employee_id' => $employee->id, 'type' => $request->type, 'photo_path' => $path,
                    'face_verified' => true, 'face_score' => $data['score'], 'face_distance' => $data['distance'],
                    'attended_at' => $time, 'attendance_date' => $time->toDateString()]);
            });
        } catch (ValidationException $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            } throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            if ($path) Storage::disk('local')->delete($path);
            throw $e;
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            } report($e);

            return response()->json(['message' => 'Absensi belum tersimpan. Silakan coba kembali.'], 503);
        }

        return response()->json(['message' => '✓ ABSENSI BERHASIL', 'name' => $employee->name,
            'type' => $attendance->type === 'check_in' ? 'Masuk' : 'Pulang', 'time' => $attendance->attended_at->format('d M Y H:i:s'),
            'score' => $attendance->face_score, 'photo' => route('attendance.photo',$attendance)]);
    }
}
