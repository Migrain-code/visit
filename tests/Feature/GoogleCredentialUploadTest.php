<?php

namespace Tests\Feature;

use App\Filament\Pages\SeoAiSettings;
use App\Models\Setting;
use App\Models\User;
use App\Services\Google\CredentialFile;
use App\Services\Google\GoogleClient;
use App\Support\SeoConfig;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class GoogleCredentialUploadTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function serviceAccountJson(string $email = 'montaj-seo@ornek-proje.iam.gserviceaccount.com'): string
    {
        return json_encode([
            'type' => 'service_account',
            'project_id' => 'ornek-proje',
            'private_key_id' => 'abc123',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nSAHTEANAHTAR\n-----END PRIVATE KEY-----\n",
            'client_email' => $email,
            'client_id' => '1234567890',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ], JSON_PRETTY_PRINT);
    }

    private function upload(string $contents, string $name = 'key.json'): string
    {
        $file = UploadedFile::fake()->createWithContent($name, $contents);

        return Storage::disk('local')->putFileAs(CredentialFile::DIRECTORY, $file, $name);
    }

    // ---------- Doğrulama ----------

    public function test_valid_service_account_is_accepted(): void
    {
        Storage::fake('local');
        $path = $this->upload($this->serviceAccountJson());

        $info = app(CredentialFile::class)->validate($path);

        $this->assertSame('montaj-seo@ornek-proje.iam.gserviceaccount.com', $info['client_email']);
        $this->assertSame('ornek-proje', $info['project_id']);
    }

    public static function invalidFiles(): array
    {
        return [
            'JSON değil' => ['bu bir json değil', 'geçerli bir JSON değil'],
            'boş nesne' => ['{}', 'alanı eksik'],
            'OAuth istemcisi' => ['{"type":"authorized_user","project_id":"x","private_key":"PRIVATE KEY","client_email":"a@b.c"}', 'service_account'],
            'anahtarsız' => ['{"type":"service_account","project_id":"x","private_key":"bos","client_email":"a@b.c"}', 'özel anahtar'],
        ];
    }

    #[DataProvider('invalidFiles')]
    public function test_invalid_files_are_rejected(string $contents, string $expectedMessage): void
    {
        Storage::fake('local');
        $path = $this->upload($contents);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/'.preg_quote($expectedMessage, '/').'/iu');

        app(CredentialFile::class)->validate($path);
    }

    // ---------- Güvenlik ----------

    public function test_credentials_are_stored_outside_the_public_directory(): void
    {
        $root = config('filesystems.disks.local.root');

        // Özel anahtar içeren dosya web'den ERİŞİLEBİLİR bir klasöre yazılmamalı.
        $this->assertStringContainsString('private', $root);
        $this->assertStringNotContainsString('public', $root);
        $this->assertFalse(
            str_starts_with(realpath($root) ?: $root, realpath(public_path()) ?: public_path()),
            'kimlik dosyası public/ altında olmamalı',
        );
    }

    public function test_uploaded_credential_is_not_reachable_over_http(): void
    {
        Storage::fake('local');
        $path = $this->upload($this->serviceAccountJson());

        /*
         * Asıl güvenlik özelliği: dosyanın İÇERİĞİ hiçbir adresten dönmemeli.
         * Durum kodunun 403 mü 404 mü olduğu ikincildir (public/storage sembolik
         * bağlantısı public diske işaret eder, özel diske değil).
         */
        foreach (['/storage/'.$path, '/'.$path, '/storage/app/private/'.$path] as $url) {
            $response = $this->get($url);

            $this->assertNotSame(200, $response->getStatusCode(), $url.' servis edilmemeli');
            $this->assertStringNotContainsString('PRIVATE KEY', $response->getContent(), $url.' özel anahtar sızdırmamalı');
            $this->assertStringNotContainsString('gserviceaccount.com', $response->getContent());
        }
    }

    // ---------- Panel akışı ----------

    public function test_uploading_a_valid_key_through_the_panel_configures_google(): void
    {
        Storage::fake('local');
        $this->actingAs(User::first());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $path = $this->upload($this->serviceAccountJson());

        Livewire::test(SeoAiSettings::class)
            ->fillForm(['google_credentials' => [$path], 'google_property' => 'sc-domain:ornek.com'])
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::flush();

        $this->assertSame($path, SeoConfig::raw('google_credentials'));
        $this->assertSame('sc-domain:ornek.com', SeoConfig::raw('google_property'));

        $google = app(GoogleClient::class);
        $this->assertTrue($google->isConfigured(), 'yükleme sonrası Google yapılandırılmış sayılmalı');
        $this->assertSame('montaj-seo@ornek-proje.iam.gserviceaccount.com', $google->serviceAccountEmail());
    }

    public function test_invalid_upload_is_discarded_and_previous_setting_survives(): void
    {
        Storage::fake('local');
        $this->actingAs(User::first());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Önce geçerli bir dosya kurulu olsun.
        $good = $this->upload($this->serviceAccountJson(), 'iyi.json');
        SeoConfig::set('google_credentials', $good);
        Setting::flush();

        $bad = $this->upload('{"type":"authorized_user"}', 'kotu.json');

        Livewire::test(SeoAiSettings::class)
            ->fillForm(['google_credentials' => [$bad]])
            ->call('save');

        Setting::flush();

        // Bozuk yol KAYDEDİLMEMELİ, eski ayar korunmalı.
        $this->assertSame($good, SeoConfig::raw('google_credentials'));
        // Reddedilen dosya diskte bırakılmamalı.
        Storage::disk('local')->assertMissing($bad);
        Storage::disk('local')->assertExists($good);
    }

    public function test_replacing_the_key_removes_the_old_file(): void
    {
        Storage::fake('local');
        $this->actingAs(User::first());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $old = $this->upload($this->serviceAccountJson('eski@ornek.iam.gserviceaccount.com'), 'eski.json');
        SeoConfig::set('google_credentials', $old);
        Setting::flush();

        $new = $this->upload($this->serviceAccountJson('yeni@ornek.iam.gserviceaccount.com'), 'yeni.json');

        Livewire::test(SeoAiSettings::class)->fillForm(['google_credentials' => [$new]])->call('save');
        Setting::flush();

        $this->assertSame($new, SeoConfig::raw('google_credentials'));
        Storage::disk('local')->assertMissing($old);
    }

    public function test_gsc_commands_stay_silent_until_a_key_is_uploaded(): void
    {
        $this->artisan('seo:sync-search-console')->assertSuccessful();
        $this->artisan('seo:sync-rankings')->assertSuccessful();
        $this->artisan('seo:check-index')->assertSuccessful();

        $this->assertFalse(app(GoogleClient::class)->isConfigured());
    }

    public function test_mount_hydrates_a_stored_string_path(): void
    {
        Storage::fake('local');
        $this->actingAs(User::first());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        // Veritabanında yol düz metin olarak durur; sayfa açılışında bozulmamalı.
        $path = $this->upload($this->serviceAccountJson());
        SeoConfig::set('google_credentials', $path);
        SeoConfig::set('google_property', 'sc-domain:ornek.com');
        Setting::flush();

        Livewire::test(SeoAiSettings::class)
            ->call('save')
            ->assertHasNoFormErrors();

        Setting::flush();

        $this->assertSame($path, SeoConfig::raw('google_credentials'), 'kaydet basınca yol kaybolmamalı');
        Storage::disk('local')->assertExists($path);
    }
}
