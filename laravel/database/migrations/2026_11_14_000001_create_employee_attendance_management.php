<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $schema=DB::connection()->getSchemaBuilder();
        if (!$schema->hasTable('employee_shift_templates')) $schema->create('employee_shift_templates',function(Blueprint $t) {
            $t->id();$t->unsignedInteger('branch_store_id')->index();$t->string('name',100);
            $t->time('start_time');$t->time('end_time');$t->unsignedSmallInteger('grace_minutes')->default(0);
            $t->boolean('is_active')->default(true);$t->timestamps();
        });
        if (!$schema->hasTable('employee_shift_schedules')) $schema->create('employee_shift_schedules',function(Blueprint $t) {
            $t->id();$t->unsignedBigInteger('employee_id');$t->unsignedInteger('branch_store_id');
            $t->date('work_date');$t->unsignedBigInteger('template_id')->nullable();$t->string('shift_name',100);
            $t->dateTime('starts_at')->nullable();$t->dateTime('ends_at')->nullable();
            $t->unsignedSmallInteger('grace_minutes')->default(0);$t->boolean('is_day_off')->default(false);
            $t->text('note')->nullable();$t->unsignedInteger('created_by');$t->timestamps();
            $t->unique(['employee_id','work_date'],'employee_shift_employee_date_unique');
            $t->index(['branch_store_id','work_date'],'employee_shift_branch_date_index');
        });
        if (!$schema->hasTable('employee_attendance_leaves')) $schema->create('employee_attendance_leaves',function(Blueprint $t) {
            $t->id();$t->unsignedBigInteger('employee_id');$t->unsignedInteger('branch_store_id');
            $t->string('type',20);$t->date('start_date');$t->date('end_date');$t->text('reason');
            $t->string('status',20)->default('pending');$t->unsignedInteger('created_by');
            $t->unsignedInteger('reviewed_by')->nullable();$t->dateTime('reviewed_at')->nullable();
            $t->text('review_note')->nullable();$t->timestamps();
            $t->index(['employee_id','start_date','end_date'],'employee_leave_dates_index');
            $t->index(['branch_store_id','status'],'employee_leave_branch_status_index');
        });
        foreach (['schedule_id','scheduled_start','scheduled_end','late_minutes','early_leave_minutes'] as $column) {
            if (!$schema->hasColumn('face_attendance_records',$column)) $schema->table('face_attendance_records',function(Blueprint $t) use($column) {
                if ($column==='schedule_id') $t->unsignedBigInteger($column)->nullable()->index();
                elseif (in_array($column,['scheduled_start','scheduled_end'])) $t->dateTime($column)->nullable();
                else $t->unsignedInteger($column)->default(0);
            });
        }
    }
    public function down(): void { /* Preserve shared schedules and attendance history. */ }
};

