<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_attendance_employees', function (Blueprint $t) {
            $t->id();
            $t->string('employee_code')->unique();
            $t->unsignedInteger('source_user_id')->nullable()->unique();
            $t->string('name');
            $t->string('department');
            $t->json('face_embedding')->nullable();
            $t->string('face_model')->nullable();
            $t->timestamps();
        });
        Schema::create('face_attendance_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('employee_id')->constrained('face_attendance_employees')->cascadeOnDelete();
            $t->enum('type', ['check_in', 'check_out']);
            $t->string('photo_path');
            $t->boolean('face_verified');
            $t->double('face_score')->nullable();
            $t->double('face_distance')->nullable();
            $t->dateTime('attended_at');
            $t->date('attendance_date');
            $t->timestamps();
            $t->unique(['employee_id', 'type', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_attendance_records');
        Schema::dropIfExists('face_attendance_employees');
    }
};
