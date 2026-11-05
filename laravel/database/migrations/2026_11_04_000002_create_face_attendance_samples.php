<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration
{
    public function up(): void
    {
        $db=DB::connection(config('database.default'));
        $schema=$db->getSchemaBuilder();
        if (!$schema->hasTable('face_attendance_samples')) {
            $schema->create('face_attendance_samples',function(Blueprint $t) {
                $t->id();$t->unsignedBigInteger('face_profile_id');
                $t->unsignedTinyInteger('slot');$t->string('pose',20)->default('front');
                $t->json('face_embedding');$t->string('face_model');$t->timestamps();
                $t->foreign('face_profile_id')->references('id')->on('face_attendance_employees')->onDelete('cascade');
                $t->unique(['face_profile_id','slot'],'face_attendance_samples_profile_slot_unique');
            });
        }
        // Preserve old enrollment once; never re-add deleted slots to an existing collection.
        $db->table('face_attendance_samples')->insertUsing(
            ['face_profile_id','slot','pose','face_embedding','face_model','created_at','updated_at'],
            $db->table('face_attendance_employees as f')
                ->select('f.id')->selectRaw("1, 'front'")
                ->addSelect('f.face_embedding','f.face_model','f.created_at','f.updated_at')
                ->whereNotNull('f.face_embedding')->whereNotNull('f.face_model')
                ->whereNotExists(function($q) { $q->selectRaw('1')->from('face_attendance_samples as s')->whereColumn('s.face_profile_id','f.id'); })
        );
    }
    public function down(): void { /* Retain shared biometric samples. */ }
};
