<?php

namespace Tests\Feature;

use App\Jobs\ArchiveMediaOriginalToNas;
use App\Jobs\ProcessImageMedia;
use App\Models\Media;
use App\Models\Order;
use App\Services\PlateRecognition\PlateRecognitionManager;
use App\Services\PlateRecognitionSettings;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlateRecognitionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('nas');
        Bus::fake([ArchiveMediaOriginalToNas::class]);
    }

    private function enablePlateRecognition(string $mode = 'auto_blur', int $minConfidence = 70): void
    {
        app(PlateRecognitionSettings::class)->update(true, $mode, $minConfidence, 'platerecognizer', 'test-key');
    }

    private function fakePlateResponse(float $score = 0.92): void
    {
        Http::fake([
            'api.platerecognizer.com/*' => Http::response([
                'results' => [[
                    'plate' => 'abc123',
                    'score' => $score,
                    'box' => ['xmin' => 100, 'ymin' => 200, 'xmax' => 260, 'ymax' => 250],
                ]],
            ], 201),
        ]);
    }

    private function processPhoto(): Media
    {
        $file = UploadedFile::fake()->image('car.jpg', 1000, 800);
        Storage::disk('local')->put('originals/1/car.jpg', file_get_contents($file->getRealPath()));

        $media = Media::factory()->photo()->create([
            'status' => Media::STATUS_PROCESSING,
            'original_s3_key' => 'originals/1/car.jpg',
        ]);

        app()->call([new ProcessImageMedia($media->id), 'handle']);

        return $media->fresh();
    }

    public function test_disabled_recognition_never_calls_the_api_and_leaves_status_pending(): void
    {
        Http::fake();

        $media = $this->processPhoto();

        Http::assertNothingSent();
        $this->assertSame(Media::PLATE_PENDING, $media->license_plate_status);
        $this->assertFalse($media->license_plate_blurred);
    }

    public function test_high_confidence_detection_is_stored_and_the_region_is_blurred(): void
    {
        $this->enablePlateRecognition('auto_blur', 70);
        $this->fakePlateResponse(0.92);

        $media = $this->processPhoto();

        $this->assertSame('ABC123', $media->license_plate);
        $this->assertSame(Media::PLATE_DETECTED, $media->license_plate_status);
        $this->assertTrue($media->license_plate_blurred);
        $this->assertEqualsWithDelta(0.92, (float) $media->license_plate_confidence, 0.01);
        $this->assertSame(['x' => 100, 'y' => 200, 'w' => 160, 'h' => 50], $media->license_plate_bbox);
        Storage::disk('public')->assertExists($media->watermarked_s3_key);
    }

    public function test_low_confidence_detection_is_flagged_but_not_blurred(): void
    {
        $this->enablePlateRecognition('auto_blur', 80);
        $this->fakePlateResponse(0.55);

        $media = $this->processPhoto();

        $this->assertSame(Media::PLATE_UNIDENTIFIABLE, $media->license_plate_status);
        $this->assertFalse($media->license_plate_blurred);
    }

    public function test_flag_only_mode_detects_but_does_not_blur(): void
    {
        $this->enablePlateRecognition('flag_only', 70);
        $this->fakePlateResponse(0.95);

        $media = $this->processPhoto();

        $this->assertSame(Media::PLATE_DETECTED, $media->license_plate_status);
        $this->assertFalse($media->license_plate_blurred);
    }

    public function test_no_plate_in_the_photo_marks_status_none(): void
    {
        $this->enablePlateRecognition();
        Http::fake(['api.platerecognizer.com/*' => Http::response(['results' => []], 201)]);

        $media = $this->processPhoto();

        $this->assertSame(Media::PLATE_NONE, $media->license_plate_status);
        $this->assertNull($media->license_plate);
    }

    public function test_api_failure_does_not_break_processing(): void
    {
        $this->enablePlateRecognition();
        Http::fake(['api.platerecognizer.com/*' => Http::response('boom', 500)]);

        $media = $this->processPhoto();

        $this->assertSame(Media::STATUS_READY, $media->status);
        $this->assertSame(Media::PLATE_NONE, $media->license_plate_status);
    }

    public function test_manager_is_skipped_when_key_missing_even_if_enabled(): void
    {
        app(PlateRecognitionSettings::class)->update(true, 'auto_blur', 70); // nincs kulcs

        $analysis = app(PlateRecognitionManager::class)->analyze(__FILE__);

        $this->assertFalse($analysis->performed);
    }

    public function test_download_serves_the_original_unblurred_file_when_buyer_consents(): void
    {
        $media = Media::factory()->photo()->create([
            'status' => Media::STATUS_READY,
            'license_plate_blurred' => true,
            'original_s3_key' => 'originals/9/car.jpg',
            'download_jpeg_s3_key' => 'downloads/9.jpg',
        ]);
        Storage::disk('local')->put('originals/9/car.jpg', 'PRISTINE-ORIGINAL');
        Storage::disk('local')->put('downloads/9.jpg', 'BLURRED-DOWNLOAD');

        $order = Order::factory()->paid()->create(['plate_consent' => true]);
        $order->media()->attach($media->id, ['price_cents' => 1490]);
        $order->issueDownloadToken();
        $token = $order->fresh()->download_token;

        // A letöltésbe forensic jel kerül (a rendeléshez kötve) — a fájl eleje a pristine eredeti.
        $body = $this->get("/download/{$token}/media/{$media->id}/jpeg")->getContent();

        $this->assertStringStartsWith('PRISTINE-ORIGINAL', $body);
    }

    public function test_download_serves_the_blurred_file_without_consent(): void
    {
        $media = Media::factory()->photo()->create([
            'status' => Media::STATUS_READY,
            'license_plate_blurred' => true,
            'original_s3_key' => 'originals/8/car.jpg',
            'download_jpeg_s3_key' => 'downloads/8.jpg',
        ]);
        Storage::disk('local')->put('originals/8/car.jpg', 'PRISTINE-ORIGINAL');
        Storage::disk('local')->put('downloads/8.jpg', 'BLURRED-DOWNLOAD');

        $order = Order::factory()->paid()->create(['plate_consent' => false]);
        $order->media()->attach($media->id, ['price_cents' => 1490]);
        $order->issueDownloadToken();
        $token = $order->fresh()->download_token;

        $body = $this->get("/download/{$token}/media/{$media->id}/jpeg")->getContent();

        $this->assertStringStartsWith('BLURRED-DOWNLOAD', $body);
    }

    public function test_checkout_persists_plate_consent(): void
    {
        $media = Media::factory()->photo()->create(['status' => Media::STATUS_READY, 'license_plate_blurred' => true]);

        $this->postJson('/checkout', [
            'media_ids' => [$media->id],
            'email' => 'buyer@example.com',
            'plate_consent' => true,
            'terms_accepted' => true,
        ]);

        $this->assertTrue((bool) Order::query()->latest('id')->first()->plate_consent);
    }
}
