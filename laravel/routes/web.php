<?php
use App\Http\Controllers\{AttendanceController,DeviceActivationController};
use App\Http\Middleware\RequireAttendanceDevice;
use Illuminate\Support\Facades\Route;
Route::get('/activate',[DeviceActivationController::class,'index'])->name('device.activate');
Route::post('/activate',[DeviceActivationController::class,'store'])->middleware('throttle:5,1')->name('device.activate.store');
Route::get('/enroll',function() { return redirect()->away(config('face.enrollment_url')); });
Route::middleware(RequireAttendanceDevice::class)->group(function() {
    Route::get('/',[AttendanceController::class,'index']);
    Route::get('/attendance',[AttendanceController::class,'index']);
    Route::post('/attendance',[AttendanceController::class,'attend'])->middleware('throttle:20,1');
    Route::get('/attendance/photos/{attendance}',[AttendanceController::class,'photo'])->name('attendance.photo');
});
