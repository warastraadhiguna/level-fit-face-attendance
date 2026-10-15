<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration
{
    public function up(): void
    {
        $schema=Schema::connection(config('database.default'));
        if (!$schema->hasTable('face_attendance_devices')) {
            $schema->create('face_attendance_devices',function(Blueprint $t) {
                $t->id();$t->unsignedInteger('branch_store_id')->unique();
                $t->string('label',100)->nullable();$t->string('token_hash',64)->nullable()->unique();
                $t->dateTime('expires_at')->nullable();$t->dateTime('activated_at')->nullable();
                $t->string('pairing_hash',64)->nullable()->unique();$t->string('pairing_label',100)->nullable();
                $t->dateTime('pairing_expires_at')->nullable();$t->unsignedInteger('owner_id')->nullable();$t->timestamps();
            });
        }
        if ($schema->hasTable('face_attendance_records') && !$schema->hasColumn('face_attendance_records','branch_store_id')) {
            $schema->table('face_attendance_records',function(Blueprint $t) { $t->unsignedInteger('branch_store_id')->nullable()->index(); });
            if (!DB::connection(config('database.default'))->table('face_attendance_records')->exists()) return;
            DB::connection(config('database.default'))->table('face_attendance_records as a')
                ->join('face_attendance_employees as e','a.employee_id','=','e.id')->join('users as u','e.source_user_id','=','u.id')
                ->select('a.id','u.branch_store_id')->chunkById(500,function($rows) {
                    foreach($rows as $row) DB::connection(config('database.default'))->table('face_attendance_records')->where('id',$row->id)->update(['branch_store_id'=>$row->branch_store_id]);
                },'a.id','id');
        }
    }
    public function down(): void { /* Shared device/attendance records are retained on rollback. */ }
};
