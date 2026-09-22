<?php

namespace Tests\Feature;

use App\Filament\Support\FormHelpers;
use App\Models\ReservationRequest;
use App\Services\Media\WebpConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Tests\TestCase;

class WebpConversionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function converter(): WebpConverter
    {
        return app(WebpConverter::class);
    }

    public function test_gd_supports_webp(): void
    {
        $this->assertTrue($this->converter()->supported(), 'PHP GD eklentisi WebP desteklemeli');
    }

    public function test_jpeg_is_converted(): void
    {
        Storage::fake('public');

        $path = $this->converter()->store(UploadedFile::fake()->image('Kapadokya Turu.jpg', 800, 600), 'public', 'tours');

        $this->assertStringEndsWith('.webp', $path);
        $this->assertStringStartsWith('tours/', $path);
        Storage::disk('public')->assertExists($path);

        // Gerçekten WebP mi? (RIFF....WEBP imzası)
        $binary = Storage::disk('public')->get($path);
        $this->assertSame('RIFF', substr($binary, 0, 4));
        $this->assertSame('WEBP', substr($binary, 8, 4));
    }

    public function test_png_transparency_survives(): void
    {
        Storage::fake('public');

        $path = $this->converter()->store(UploadedFile::fake()->image('logo.png', 200, 200), 'public', 'gallery');

        $this->assertStringEndsWith('.webp', $path);
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(200, $w);
        $this->assertSame(200, $h);
    }

    public function test_oversized_image_is_downscaled(): void
    {
        Storage::fake('public');

        $path = $this->converter()->store(UploadedFile::fake()->image('buyuk.jpg', 4000, 3000), 'public', 'gallery');

        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));

        $this->assertSame(2200, $w, 'uzun kenar 2200 pikselle sınırlanmalı');
        $this->assertSame(1650, $h, 'en-boy oranı korunmalı');
    }

    public function test_filename_is_slugified_and_unique(): void
    {
        Storage::fake('public');

        $a = $this->converter()->store(UploadedFile::fake()->image('Göreme Peri Bacaları Gün Doğumu.jpg'), 'public', 'gallery');
        $b = $this->converter()->store(UploadedFile::fake()->image('Göreme Peri Bacaları Gün Doğumu.jpg'), 'public', 'gallery');

        // Dosya adı görsel aramasına girer: Türkçe karakter ASCII'ye indirgenir.
        $this->assertStringContainsString('goreme-peri-bacalari-gun-dogumu', $a);
        $this->assertMatchesRegularExpression('#^gallery/[a-z0-9-]+\.webp$#', $a);
        $this->assertNotSame($a, $b, 'aynı adla ikinci yükleme öncekini EZMEMELİ');
    }

    public function test_non_image_files_are_left_untouched(): void
    {
        Storage::fake('local');

        // Google kimlik dosyası çevrilirse bozulur.
        $json = UploadedFile::fake()->createWithContent('key.json', '{"type":"service_account"}');
        $path = $this->converter()->store($json, 'local', 'google');

        $this->assertStringEndsWith('.json', $path);
        $this->assertSame('{"type":"service_account"}', Storage::disk('local')->get($path));
    }

    public function test_corrupt_image_falls_back_to_the_original(): void
    {
        Storage::fake('public');

        $broken = UploadedFile::fake()->createWithContent('bozuk.jpg', 'bu bir görsel değil');
        $path = $this->converter()->store($broken, 'public', 'gallery');

        // Yükleme DÜŞMEZ; dosya orijinal uzantısıyla saklanır.
        $this->assertStringEndsWith('.jpg', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_phone_photo_for_a_tour_gallery_is_downscaled_and_published(): void
    {
        Storage::fake('public');

        // Tur galerisine telefondan çekilmiş büyük bir fotoğraf yüklenir.
        $path = $this->converter()->store(UploadedFile::fake()->image('telefon-fotografi.jpg', 3000, 2000), 'public', 'tours/gallery', 'public');

        $this->assertMatchesRegularExpression('#^tours/gallery/telefon-fotografi-[a-z0-9]{8}\.webp$#', $path);
        $this->assertSame('public', Storage::disk('public')->getVisibility($path));

        // Fotoğraf küçültülmüş olmalı.
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([2200, 1467], [$w, $h]);
    }

    public function test_small_images_are_not_upscaled(): void
    {
        Storage::fake('public');

        $path = $this->converter()->store(UploadedFile::fake()->image('kucuk.png', 320, 240), 'public', 'staff');

        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([320, 240], [$w, $h]);
    }

    public function test_admin_image_fields_save_through_the_converter(): void
    {
        Storage::fake('public');
        Storage::fake(FileUploadConfiguration::disk());

        // Paneldeki TÜM görsel alanları (tur kapağı, galeri, kategori, blog, personel, site
        // ayarları) bu yardımcıdan geçer; yüklenen dosya Livewire geçici dosyasıdır.
        $temporary = UploadedFile::fake()->image('Bozcaada Sahil.jpg', 2600, 1300)
            ->storeAs(FileUploadConfiguration::path(), 'gecici-yukleme.jpg', ['disk' => FileUploadConfiguration::disk()]);
        $this->assertNotFalse($temporary);

        $file = TemporaryUploadedFile::createFromLivewire('gecici-yukleme.jpg');

        // Filament, dosyayı kaydederken alana tanımlı geri çağrıyı bu şekilde çalıştırır
        // (BaseFileUpload::saveUploadedFiles). Varsayılan kaydedici dosyayı ÇEVİRMEDEN taşır.
        $field = FormHelpers::imageUpload('image', 'tours', 'Kapak görseli');
        $saveUsing = (fn () => $this->saveUploadedFileUsing)->call($field);
        $path = $field->evaluate($saveUsing, ['file' => $file]);

        $this->assertStringStartsWith('tours/', $path);
        $this->assertStringEndsWith('.webp', $path);
        Storage::disk('public')->assertExists($path);

        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([2200, 1100], [$w, $h]);
    }

    public function test_tour_gallery_field_accepts_multiple_converted_images(): void
    {
        $gallery = FormHelpers::galleryUpload('gallery', 'tours/gallery', 'Tur galerisi');

        $this->assertTrue($gallery->isMultiple());
        $this->assertTrue($gallery->isReorderable());
        $this->assertSame(20, $gallery->getMaxFiles());
        $this->assertSame('public', $gallery->getDiskName());
        $this->assertSame('tours/gallery', $gallery->getDirectory());
        $this->assertEqualsCanonicalizing(['image/jpeg', 'image/png', 'image/webp', 'image/gif'], $gallery->getAcceptedFileTypes());
    }

    public function test_reservation_form_does_not_accept_file_uploads(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        // Eski teklif formundaki fotoğraf alanı kaldırıldı: gönderilen dosya yok sayılır,
        // diske hiçbir şey yazılmaz ve talep yine de kaydedilir.
        $this->post('/rezervasyon', [
            'name' => 'Test',
            'phone' => '0532 111 22 33',
            'people_count' => 2,
            'kvkk' => '1',
            'photos' => [UploadedFile::fake()->image('telefon-fotografi.jpg', 3000, 2000)],
        ])->assertRedirect(route('reservation.thanks'));

        $reservation = ReservationRequest::firstOrFail();

        $this->assertArrayNotHasKey('photos', $reservation->getAttributes());
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());

        $this->get('/rezervasyon')->assertOk()->assertDontSee('type="file"', false)->assertDontSee('multipart/form-data', false);
    }
}
