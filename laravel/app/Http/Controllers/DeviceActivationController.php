<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cookie,DB};
use Illuminate\Validation\ValidationException;
class DeviceActivationController extends Controller
{
    public function index() { return response()->view('device-activation')->header('Cache-Control','no-store, private'); }
    public function store(Request $request)
    {
        abort_unless($request->isSecure() || app()->environment(['local','testing']),400,'Aktivasi perangkat membutuhkan HTTPS.');
        $request->validate(['code'=>['required','string','regex:/^[a-fA-F0-9 -]{12,20}$/D']]);
        $code=strtoupper(str_replace(['-',' '],'',$request->code));
        $token=bin2hex(random_bytes(32));$now=now('UTC');
        DB::transaction(function() use($code,$token,$now) {
            $device=DB::table('face_attendance_devices')->where('pairing_hash',hash('sha256',$code))
                ->where('pairing_expires_at','>',$now)->lockForUpdate()->first();
            if(!$device || !DB::table('branch_stores')->where('id',$device->branch_store_id)->exists()) {
                throw ValidationException::withMessages(['code'=>'Kode tidak valid, kedaluwarsa, atau sudah digunakan. Minta kode baru kepada Owner.']);
            }
            DB::table('face_attendance_devices')->where('id',$device->id)->update([
                'token_hash'=>hash('sha256',$token),'label'=>$device->pairing_label,
                'activated_at'=>$now,'expires_at'=>$now->copy()->addDays(config('device.lifetime_days')),
                'pairing_hash'=>null,'pairing_label'=>null,'pairing_expires_at'=>null,'updated_at'=>$now,
            ]);
        });
        $cookie=Cookie::make(config('device.cookie'),$token,config('device.lifetime_days')*24*60,'/',null,
            $request->isSecure() || app()->environment('production'),true,false,'strict');
        return redirect('/')->withCookie($cookie);
    }
}
