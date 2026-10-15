<?php
namespace Tests\Feature;
use App\Models\Employee;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Schema};
use Tests\TestCase;
class EmployeeSyncTest extends TestCase
{
    use RefreshDatabase;
    public function test_imports_active_source_users_and_preserves_enrollment_when_repeated(): void
    {
        Schema::create('branch_stores', function(Blueprint $t) { $t->id();$t->string('name'); });
        Schema::create('users', function(Blueprint $t) { $t->id();$t->integer('branch_store_id');$t->string('full_name');$t->string('role');$t->timestamp('deleted_at')->nullable(); });
        DB::table('branch_stores')->insert(['id'=>1,'name'=>'Gym']);
        DB::table('users')->insert([
            ['id'=>7,'branch_store_id'=>1,'full_name'=>'Active Staff','role'=>'CS','deleted_at'=>null],
            ['id'=>8,'branch_store_id'=>1,'full_name'=>'Deleted Staff','role'=>'FC','deleted_at'=>'2026-01-01'],
        ]);
        $this->seed();
        $this->assertDatabaseCount('face_attendance_employees',1);
        $employee=Employee::first();
        $this->assertEquals('USER-7',$employee->employee_code);
        $this->assertEquals(7,$employee->source_user_id);
        $this->assertEquals('CS · Gym',$employee->department);
        $employee->update(['face_embedding'=>array_fill(0,128,0.1),'face_model'=>config('face.model')]);
        $this->seed();
        $this->assertDatabaseCount('face_attendance_employees',1);
        $this->assertEquals(0.1,$employee->fresh()->face_embedding[0]);
        $this->assertDatabaseCount('users',2);
    }
}
