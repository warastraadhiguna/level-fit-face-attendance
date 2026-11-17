<?php
namespace Tests\Feature;

use App\Services\FaceVerificationService;
use Illuminate\Support\Facades\{Http,Log,Route};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OperationalInformationPrivacyTest extends TestCase
{
    public function test_error_responses_do_not_expose_internal_details_with_debug_enabled(): void
    {
        config(['app.debug'=>true]);Log::spy();
        Route::get('/privacy-test-error',function() { throw new \RuntimeException('SQLSTATE secret_database password=private-key /private/model.onnx'); });
        $json=$this->getJson('/privacy-test-error')->assertStatus(500)->assertHeader('Cache-Control','no-store, private');
        $this->assertSame(['message'],array_keys($json->json()));
        $html=$this->get('/privacy-test-error')->assertStatus(500)->assertSee('Silakan coba kembali');
        foreach ([$json,$html] as $response) foreach (['SQLSTATE','secret_database','private-key','model.onnx'] as $secret) $response->assertDontSee($secret);
    }

    public function test_validation_still_returns_actionable_field_errors(): void
    {
        Route::post('/privacy-test-validation',function() { request()->validate(['type'=>'required']); });
        $this->postJson('/privacy-test-validation')->assertUnprocessable()->assertJsonValidationErrors('type');
    }

    public function test_upstream_errors_are_private_and_safe_photo_guidance_is_retained(): void
    {
        Log::spy();Http::fake(['*'=>Http::sequence()->push(['success'=>false,'message'=>'SQLSTATE password=secret /private/model.onnx'],503)->push(['success'=>false,'code'=>'face_blurry','message'=>'private diagnostic'],422)]);
        try { app(FaceVerificationService::class)->process('enroll','photo');$this->fail('Expected validation error'); }
        catch (ValidationException $e) {
            $text=json_encode($e->errors());$this->assertStringNotContainsString('SQLSTATE',$text);$this->assertStringNotContainsString('model.onnx',$text);
        }
        try { app(FaceVerificationService::class)->process('enroll','photo');$this->fail('Expected validation error'); }
        catch (ValidationException $e) { $this->assertStringContainsString('Foto buram',$e->errors()['photo'][0]); }
    }
}
