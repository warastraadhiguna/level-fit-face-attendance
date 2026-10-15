<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class RequireAttendanceDevice
{
    public function handle(Request $request, Closure $next)
    {
        $token=$request->cookie(config('device.cookie'));
        $device=is_string($token) && preg_match('/^[a-f0-9]{64}$/D',$token)
            ? DB::table('face_attendance_devices as d')->join('branch_stores as b','d.branch_store_id','=','b.id')
                ->where('d.token_hash',hash('sha256',$token))->where('d.expires_at','>',now('UTC'))
                ->select('d.*','b.name as branch_name')->first() : null;
        if(!$device) {
            if($request->expectsJson()) return response()->json(['message'=>'Perangkat absensi belum aktif atau akses sudah dicabut. Minta Owner mengaktifkan perangkat ini.'],403);
            return redirect()->route('device.activate');
        }
        $request->attributes->set('attendance_device',$device);
        $response=$next($request);
        $response->headers->set('Cache-Control','no-store, private');
        return $response;
    }
}
