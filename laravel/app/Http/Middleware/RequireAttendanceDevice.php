<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use App\Services\AttendanceDeviceAccess;
class RequireAttendanceDevice
{
    public function handle(Request $request, Closure $next)
    {
        $device=app(AttendanceDeviceAccess::class)->resolve($request);
        if(!$device) {
            if($request->expectsJson()) return response()->json(['message'=>'Perangkat absensi belum aktif atau akses sudah dicabut. Minta Owner mengaktifkan perangkat ini.'],403)->header('Cache-Control','no-store, private');
            return redirect()->route('device.activate')->header('Cache-Control','no-store, private');
        }
        $request->attributes->set('attendance_device',$device);
        $response=$next($request);
        $response->headers->set('Cache-Control','no-store, private');
        return $response;
    }
}
