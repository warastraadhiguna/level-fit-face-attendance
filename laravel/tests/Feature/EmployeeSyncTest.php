<?php
namespace Tests\Feature;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Schema};
use Tests\TestCase;
class EmployeeSyncTest extends TestCase
{
    use RefreshDatabase;
    public function test_seeding_does_not_import_users_or_change_independent_employees(): void
    {
        Schema::create('users',function(Blueprint $t) { $t->id();$t->string('full_name'); });
        DB::table('users')->insert(['full_name'=>'Login account']);
        DB::table('employees')->insert(['employee_code'=>'NEW','full_name'=>'Independent','branch_store_id'=>1]);
        $this->seed();$this->seed();
        $this->assertDatabaseCount('employees',1);
        $this->assertDatabaseCount('face_attendance_employees',0);
        $this->assertDatabaseCount('users',1);
    }
}
