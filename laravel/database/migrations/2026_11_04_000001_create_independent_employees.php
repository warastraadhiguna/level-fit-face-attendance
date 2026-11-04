<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        $schema=Schema::connection(config('database.default'));
        if (!$schema->hasTable('employees')) {
            $schema->create('employees',function(Blueprint $t) {
                $t->id();
                $t->string('employee_code',50)->unique();
                $t->unsignedInteger('branch_store_id')->index();
                $t->string('full_name',150);
                $t->string('position',100)->nullable();
                $t->string('phone',30)->nullable();
                $t->boolean('is_active')->default(true);
                $t->timestamps();$t->softDeletes();
                $t->index(['branch_store_id','is_active','deleted_at'],'employees_attendance_scope_index');
            });
        }
        if (!$schema->hasTable('face_attendance_employees')) {
            if ($schema->hasTable('face_demo_employees')) {
                throw new \RuntimeException('Rename tabel face_demo_employees terlebih dahulu sebelum membuat pegawai baru.');
            }
            $schema->create('face_attendance_employees',function(Blueprint $t) {
                $t->id();$t->string('employee_code')->unique();
                $t->unsignedInteger('source_user_id')->nullable()->unique();
                $t->string('name');$t->string('department');
                $t->json('face_embedding')->nullable();$t->string('face_model')->nullable();
                $t->timestamps();
            });
        }
        if ($schema->hasTable('face_attendance_employees') && !$schema->hasColumn('face_attendance_employees','source_employee_id')) {
            $schema->table('face_attendance_employees',function(Blueprint $t) {
                $t->unsignedBigInteger('source_employee_id')->nullable()->unique();
            });
        }
    }
    public function down(): void { /* Shared employees, profiles and historical attendance are retained. */ }
};
