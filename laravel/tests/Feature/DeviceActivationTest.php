<?php
namespace Tests\Feature;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB,Http,Schema};
use Tests\TestCase;
class DeviceActivationTest extends TestCase
{
    use RefreshDatabase;
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('branch_stores',function(Blueprint $t) { $t->id();$t->string('name'); });
        DB::table('branch_stores')->insert(['id'=>1,'name'=>'One']);
        $this->withCredentials();
    }
    private function pending(string $code='ABCDEF123456'): void
    {
        DB::table('face_attendance_devices')->updateOrInsert(['branch_store_id'=>1],['pairing_hash'=>hash('sha256',$code),'pairing_label'=>'Front Desk','pairing_expires_at'=>now('UTC')->addMinutes(5),'owner_id'=>7,'owner_name'=>'Owner One','owner_login_branch_store_id'=>1,'owner_login_branch_name'=>'Original Branch','owner_recorded_at'=>now('UTC')]);
    }
    public function test_unactivated_browser_cannot_view_or_submit_attendance_or_photos(): void
    {
        Http::fake();$this->get('/')->assertRedirect(route('device.activate'));
        $this->postJson('/attendance',[])->assertForbidden();
        $employee=\App\Models\Employee::create(['employee_code'=>'ONE','name'=>'One','department'=>'CS']);
        $attendance=\App\Models\Attendance::create(['employee_id'=>$employee->id,'branch_store_id'=>1,'type'=>'check_in','photo_path'=>'attendance/private.jpg','face_verified'=>true,'attended_at'=>now(),'attendance_date'=>now()->toDateString()]);
        $this->getJson('/attendance/photos/'.$attendance->id)->assertForbidden();Http::assertNothingSent();
        $this->get('/activate')->assertOk()->assertSee('Kode aktivasi');
    }
    public function test_one_time_activation_issues_encrypted_http_only_cookie_and_hash_only_storage(): void
    {
        $this->pending();
        $response=$this->post('/activate',['code'=>'ABCD-EF12-3456'])->assertRedirect('/')->assertCookie(config('device.cookie'));
        $cookie=$response->getCookie(config('device.cookie'));
        $this->assertTrue($cookie->isHttpOnly());$this->assertEquals('strict',$cookie->getSameSite());$this->assertEmpty($cookie->getDomain());
        $row=DB::table('face_attendance_devices')->first();$this->assertNull($row->pairing_hash);
        $this->assertEquals('Original Branch',$row->owner_login_branch_name);$this->assertEquals(7,$row->owner_id);
        $token=$cookie->getValue();$this->assertEquals(hash('sha256',$token),$row->token_hash);
        $raw=$response->getCookie(config('device.cookie'),false)->getValue();$this->assertNotEquals($token,$raw);
        $this->withCookie(config('device.cookie'),$token)->get('/')->assertOk()->assertSee('One');
        $this->postJson('/activate',['code'=>'ABCD-EF12-3456'])->assertStatus(422);
    }
    public function test_new_activation_replaces_old_device_and_revocation_blocks_it_immediately(): void
    {
        $old=str_repeat('a',64);DB::table('face_attendance_devices')->insert(['branch_store_id'=>1,'token_hash'=>hash('sha256',$old),'expires_at'=>now('UTC')->addYear()]);
        $this->withCookie(config('device.cookie'),$old)->get('/')->assertOk();
        $this->pending();$response=$this->post('/activate',['code'=>'ABCDEF123456'])->assertRedirect('/');
        $new=$response->getCookie(config('device.cookie'))->getValue();
        $this->withCookie(config('device.cookie'),$old)->postJson('/attendance',[])->assertForbidden();
        $this->withCookie(config('device.cookie'),$new)->get('/')->assertOk();
        DB::table('face_attendance_devices')->update(['token_hash'=>null]);
        $this->get('/')->assertRedirect(route('device.activate'));$this->postJson('/attendance',[])->assertForbidden();
        $this->assertDatabaseCount('face_attendance_devices',1);
    }
    public function test_expired_pairing_or_expired_device_and_forged_token_are_rejected(): void
    {
        $this->pending();DB::table('face_attendance_devices')->update(['pairing_expires_at'=>now('UTC')->subSecond()]);
        $this->postJson('/activate',['code'=>'ABCDEF123456'])->assertStatus(422);
        $token=str_repeat('c',64);DB::table('face_attendance_devices')->update(['token_hash'=>hash('sha256',$token),'expires_at'=>now('UTC')->subSecond()]);
        $this->withCookie(config('device.cookie'),$token)->postJson('/attendance',[])->assertForbidden();
        $this->withCookie(config('device.cookie'),str_repeat('d',64))->postJson('/attendance',[])->assertForbidden();
    }
    public function test_https_activation_uses_secure_cookie(): void
    {
        $this->pending();$response=$this->post('https://localhost/activate',['code'=>'ABCDEF123456'])->assertRedirect('/');
        $this->assertTrue($response->getCookie(config('device.cookie'))->isSecure());
    }
}
