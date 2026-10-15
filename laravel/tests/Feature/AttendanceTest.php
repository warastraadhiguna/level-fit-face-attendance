<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Schema::create('branch_stores',function(\Illuminate\Database\Schema\Blueprint $t) { $t->id();$t->string('name'); });
        \Illuminate\Support\Facades\Schema::create('users',function(\Illuminate\Database\Schema\Blueprint $t) { $t->id();$t->integer('branch_store_id');$t->timestamp('deleted_at')->nullable(); });
        \Illuminate\Support\Facades\DB::table('branch_stores')->insert([['id'=>1,'name'=>'Branch One'],['id'=>2,'name'=>'Branch Two']]);
        $token=str_repeat('a',64);
        \Illuminate\Support\Facades\DB::table('face_attendance_devices')->insert(['branch_store_id'=>1,'token_hash'=>hash('sha256',$token),'expires_at'=>now('UTC')->addYear()]);
        $this->withCredentials()->withCookie(config('device.cookie'),$token);
    }

    private function vector(int $axis = 0): array
    {
        $v = array_fill(0, 128, 0.0);
        $v[$axis] = 1.0;

        return $v;
    }

    private function employee(string $code = 'EMP001', int $axis = 0): Employee
    {
        $employee=Employee::create(['employee_code' => $code, 'name' => $code === 'EMP001' ? 'Andi' : 'Budi', 'department' => 'Finance', 'face_embedding' => $this->vector($axis), 'face_model' => 'opencv-sface-2021dec']);
        \Illuminate\Support\Facades\DB::table('users')->insert(['id'=>$employee->id,'branch_store_id'=>1]);
        $employee->update(['source_user_id'=>$employee->id]);
        return $employee;
    }

    private function body(string $type = 'check_in'): array
    {
        return ['type' => $type, 'photo' => 'data:image/jpeg;base64,'.base64_encode(file_get_contents(base_path('tests/Fixtures/capture.jpg')))];
    }

    private function service(int $axis = 0): void
    {
        Http::fake(['*/face/enroll' => Http::response(['success' => true, 'face_count' => 1, 'embedding' => $this->vector($axis), 'model' => 'opencv-sface-2021dec'])]);
    }

    public function test_identifies_without_employee_id_and_saves_server_time_and_photo(): void
    {
        Storage::fake('local');
        $e = $this->employee();
        $this->employee('EMP002', 1);
        $this->service();
        $this->travelTo(now()->setTime(9, 0));
        $this->postJson('/attendance', $this->body() + ['attended_at' => '1999-01-01'])->assertOk()->assertJsonPath('name', 'Andi');
        $a = Attendance::first();
        $this->assertEquals($e->id, $a->employee_id);
        $this->assertEquals(now()->toDateTimeString(), $a->attended_at->toDateTimeString());
        Storage::disk('local')->assertExists($a->photo_path);
    }

    public function test_browser_employee_id_cannot_override_recognition(): void
    {
        Storage::fake('local');
        $e = $this->employee();
        $b = $this->employee('EMP002', 1);
        $this->service();
        $this->postJson('/attendance', $this->body() + ['employee_id' => $b->id])->assertOk()->assertJsonPath('name', 'Andi');
        $this->assertEquals($e->id, Attendance::first()->employee_id);
    }

    public function test_unknown_face_never_saves(): void
    {
        Storage::fake('local');
        $this->employee();
        $this->service(1);
        $this->postJson('/attendance', $this->body())->assertStatus(422);
        $this->assertDatabaseCount('face_attendance_records', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_ambiguous_face_never_saves(): void
    {
        $this->employee();
        $this->employee('EMP002');
        $this->service();
        $this->postJson('/attendance', $this->body())->assertStatus(422);
        $this->assertDatabaseCount('face_attendance_records', 0);
    }

    public function test_no_enrolled_faces_does_not_call_python(): void
    {
        Http::fake();
        $this->postJson('/attendance', $this->body())->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_checkout_requires_checkin(): void
    {
        $this->employee();
        $this->service();
        $this->postJson('/attendance', $this->body('check_out'))->assertStatus(422);
        $this->assertDatabaseCount('face_attendance_records', 0);
    }

    public function test_duplicate_and_checkout_rules(): void
    {
        Storage::fake('local');
        $this->employee();
        $this->service();
        $this->postJson('/attendance', $this->body())->assertOk();
        $this->postJson('/attendance', $this->body())->assertStatus(422);
        $this->postJson('/attendance', $this->body('check_out'))->assertOk();
        $this->postJson('/attendance', $this->body('check_out'))->assertStatus(422);
        $this->assertDatabaseCount('face_attendance_records', 2);
    }

    public function test_service_unavailable(): void
    {
        $this->employee();
        Http::fake(['*' => Http::failedConnection()]);
        $this->postJson('/attendance', $this->body())->assertStatus(422)->assertJsonValidationErrors('photo');
        $this->assertDatabaseCount('face_attendance_records', 0);
    }

    public function test_multiple_faces_never_saves(): void
    {
        $this->employee();
        Http::fake(['*' => Http::response(['success' => false, 'face_count' => 2, 'message' => 'Harus tepat satu wajah.'], 422)]);
        $this->postJson('/attendance', $this->body())->assertStatus(422);
        $this->assertDatabaseCount('face_attendance_records', 0);
    }

    public function test_old_enrollment_routes_cannot_bypass_master_branch_settings(): void
    {
        Http::fake();
        $this->get('/enroll')->assertRedirect(config('face.enrollment_url'));
        $this->postJson('/enroll', $this->body() + ['employee_id' => 1])->assertStatus(405);
        Http::assertNothingSent();
    }

    public function test_pages_show_selection_only_for_enrollment(): void
    {
        $this->employee();
        $this->get('/')->assertOk()->assertDontSee('id="employee"', false)->assertSee('Identifikasi otomatis');
        $this->get('/enroll')->assertRedirect(config('face.enrollment_url'));
    }

    public function test_invalid_recognition_configuration_fails_closed(): void
    {
        $this->employee();
        Http::fake();
        config(['face.recognition_margin' => -1]);
        $this->postJson('/attendance', $this->body())->assertStatus(422);
        Http::assertNothingSent();
        $this->assertDatabaseCount('face_attendance_records', 0);
    }
    public function test_other_branch_and_deleted_user_cannot_be_recognized(): void
    {
        $employee=$this->employee();$this->service();
        \Illuminate\Support\Facades\DB::table('users')->where('id',$employee->source_user_id)->update(['branch_store_id'=>2]);
        $this->postJson('/attendance',$this->body()+['branch_store_id'=>2])->assertStatus(422);
        \Illuminate\Support\Facades\DB::table('users')->where('id',$employee->source_user_id)->update(['branch_store_id'=>1,'deleted_at'=>now()]);
        $this->postJson('/attendance',$this->body())->assertStatus(422);
        $this->assertDatabaseCount('face_attendance_records',0);
    }
    public function test_revocation_during_inference_prevents_attendance(): void
    {
        $this->employee();
        Http::fake(function() {
            \Illuminate\Support\Facades\DB::table('face_attendance_devices')->update(['token_hash'=>null]);
            return Http::response(['success'=>true,'face_count'=>1,'embedding'=>$this->vector(),'model'=>'opencv-sface-2021dec']);
        });
        $this->postJson('/attendance',$this->body())->assertForbidden();
        $this->assertDatabaseCount('face_attendance_records',0);
    }
    public function test_history_and_private_photos_are_limited_to_device_branch(): void
    {
        Storage::fake('local');$employee=$this->employee();$this->service();
        $response=$this->postJson('/attendance',$this->body())->assertOk();
        $this->get($response->json('photo'))->assertOk()->assertHeader('Cache-Control','no-store, private');
        $attendance=Attendance::first();$attendance->update(['branch_store_id'=>2]);
        $this->get($response->json('photo'))->assertNotFound();
        $this->get('/')->assertOk()->assertSee('Belum ada absensi');
    }
    public function test_same_name_and_face_in_two_branches_match_only_the_device_branch_account(): void
    {
        Storage::fake('local');
        $candisari=$this->employee('MILA-CANDISARI');
        $supriyadi=$this->employee('MILA-SUPRIYADI');
        $candisari->update(['name'=>'Mila','department'=>'CS · Candisari']);
        $supriyadi->update(['name'=>'Mila','department'=>'CS · Supriyadi']);
        \Illuminate\Support\Facades\DB::table('users')->where('id',$supriyadi->source_user_id)->update(['branch_store_id'=>2]);
        $this->service();
        // Both embeddings are identical: a global comparison would be ambiguous.
        $this->postJson('/attendance',$this->body()+['employee_id'=>$supriyadi->id,'branch_store_id'=>2])->assertOk()->assertJsonPath('name','Mila');
        $this->assertDatabaseHas('face_attendance_records',['employee_id'=>$candisari->id,'branch_store_id'=>1]);
        $this->assertDatabaseMissing('face_attendance_records',['employee_id'=>$supriyadi->id]);
        // Simulate the independently authorized phone of the other branch.
        $token=str_repeat('b',64);
        \Illuminate\Support\Facades\DB::table('face_attendance_devices')->insert(['branch_store_id'=>2,'token_hash'=>hash('sha256',$token),'expires_at'=>now('UTC')->addYear()]);
        $this->withCookie(config('device.cookie'),$token)->postJson('/attendance',$this->body()+['employee_id'=>$candisari->id,'branch_store_id'=>1])->assertOk()->assertJsonPath('name','Mila');
        $this->assertDatabaseHas('face_attendance_records',['employee_id'=>$supriyadi->id,'branch_store_id'=>2]);
        $this->assertDatabaseCount('face_attendance_records',2);
    }
}
