<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
return new class extends Migration
{
    public function up(): void
    {
        $db=DB::connection(config('database.default'));
        if (!$db->getSchemaBuilder()->hasTable('face_attendance_devices')) return;
        $indexes=$db->getDriverName()==='sqlite'
            ? $db->select("PRAGMA index_list('face_attendance_devices')")
            : $db->select('SHOW INDEX FROM face_attendance_devices');
        $names=array_map(function($row) { return $row->name ?? $row->Key_name; },$indexes);
        $schema=$db->getSchemaBuilder();
        if (!in_array('face_attendance_devices_branch_store_id_index',$names,true)) {
            $schema->table('face_attendance_devices',function(Blueprint $t) { $t->index('branch_store_id'); });
        }
        if (in_array('face_attendance_devices_branch_store_id_unique',$names,true)) {
            $schema->table('face_attendance_devices',function(Blueprint $t) { $t->dropUnique('face_attendance_devices_branch_store_id_unique'); });
        }
    }
    public function down(): void { /* Multiple devices and existing tokens are preserved. */ }
};
