<?php
namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceDeviceAccess
{
    public function resolve(Request $request): ?object
    {
        $token=$request->cookie(config('device.cookie'));
        if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D',$token)) return null;

        return DB::table('face_attendance_devices as d')
            ->join('branch_stores as b','d.branch_store_id','=','b.id')
            ->where('d.token_hash',hash('sha256',$token))
            ->where('d.expires_at','>',now('UTC'))
            ->select('d.*','b.name as branch_name')->first();
    }
}
