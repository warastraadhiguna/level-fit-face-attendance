<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        $schema=Schema::connection(config('database.default'));
        if(!$schema->hasColumn('face_attendance_devices','owner_login_branch_store_id')) {
            $schema->table('face_attendance_devices',function(Blueprint $t) { $t->unsignedInteger('owner_login_branch_store_id')->nullable(); });
        }
        if(!$schema->hasColumn('face_attendance_devices','owner_login_branch_name')) {
            $schema->table('face_attendance_devices',function(Blueprint $t) { $t->string('owner_login_branch_name',100)->nullable(); });
        }
        if(!$schema->hasColumn('face_attendance_devices','owner_name')) {
            $schema->table('face_attendance_devices',function(Blueprint $t) { $t->string('owner_name')->nullable(); });
        }
        if(!$schema->hasColumn('face_attendance_devices','owner_recorded_at')) {
            $schema->table('face_attendance_devices',function(Blueprint $t) { $t->dateTime('owner_recorded_at')->nullable(); });
        }
        // Do not infer historical login context from the owner's current branch.
    }
    public function down(): void { /* Retain shared owner/branch snapshots. */ }
};
