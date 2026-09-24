<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\TourDeparture;
use App\Services\Media\ImageVariants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PageSpeed bulgularına karşı alınan önlemlerin geri gelmemesi için.
 *
 * Her test bir PageSpeed maddesine karşılık gelir; açıklamalar hangisi olduğunu söyler.
 */
class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    // ---------- Sunucu yanıt süresi ----------

    public function test_settings_are_read_once_per_request_not_once_per_call(): void
    {
        // Önbellek sürücüsü "database" iken her Cache::get bir sorgudur. setting() ana
        // sayfada onlarca kez çağrılır; her çağrı bir sorgu atmamalı.
        config(['cache.default' => 'database']);
        Setting::flush();

        $reads = 0;
        DB::listen(function ($query) use (&$reads) {
            if (str_contains($query->sql, 'from "cache"') || str_contains($query->sql, 'from `cache`')) {
                $reads++;
            }
        });

        for ($i = 0; $i < 50; $i++) {
            setting('site_name');
            setting('hero_title');
        }

        $this->assertLessThanOrEqual(1, $reads, "Ayarlar için {$reads} önbellek sorgusu atıldı.");
    }

    public function test_saving_a_setting_is_visible_in_the_same_request(): void
    {
        setting('hero_title');                     // belleğe al
        Setting::set('hero_title', 'Yeni başlık'); // kaydetmek belleği temizlemeli

        $this->assertSame('Yeni başlık', setting('hero_title'));
    }

    public function test_page_query_budgets(): void
    {
        // Tur kartları koltuk özetiyle tek sorguda gelir; sayfa başına bütçe küçük kalmalı.
        foreach (['/' => 8, '/iletisim' => 8, '/is-basvurusu' => 6] as $url => $budget) {
            $count = $this->countQueries($url);

            $this->assertLessThanOrEqual($budget, $count, "{$url} {$count} sorgu attı.");
        }
    }

    public function test_query_count_does_not_grow_with_the_number_of_tours(): void
    {
        // N+1 koruması: tur eklendikçe sorgu sayısı artıyorsa bir ilişki satır satır yükleniyordur.
        $this->get('/')->assertOk(); // ısınma: ayar önbelleği ilk istekte dolar

        $before = ['/' => $this->countQueries('/'), '/iletisim' => $this->countQueries('/iletisim')];

        foreach ([100, 110, 120, 130] as $days) {
            TourDeparture::create(['title' => 'Ek Tur '.$days, 'starts_at' => now()->addDays($days)->setTime(8, 0), 'price' => 1000]);
        }

        foreach ($before as $url => $count) {
            $after = $this->countQueries($url);

            $this->assertLessThanOrEqual($count, $after, "{$url}: tur sayısı artınca sorgu sayısı {$count} → {$after} oldu.");
        }
    }

    // ---------- Sayfa çıktısı ----------

    public function test_hero_image_is_an_early_discoverable_high_priority_img(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('site/hero.webp', $this->fakeWebp(1920, 1440));
        Setting::set('hero_image', 'site/hero.webp');

        $html = $this->get('/')->assertOk()->getContent();

        // PageSpeed "LCP istek keşfi": CSS arka planı değil, HTML'de öncelikli <img>.
        $this->assertStringContainsString('fetchpriority="high"', $html);
        $this->assertStringContainsString('<source media="(max-width: 767.98px)"', $html);
        $this->assertDoesNotMatchRegularExpression('/style="background-image:url/', $html);
    }

    public function test_no_render_blocking_third_party_fonts_or_icons(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Google Fonts yok: yazı tipleri kendi sunucumuzdan.
        $this->assertStringNotContainsString('fonts.googleapis.com', $html);

        // İkon stilleri engellemeyen biçimde yüklenir.
        $this->assertMatchesRegularExpression('/<link rel="stylesheet" href="[^"]*icons-[^"]*\.css" media="print" onload="this\.media=\'all\'">/', $html);
    }

    public function test_fonts_are_self_hosted(): void
    {
        // Plus Jakarta Sans paket içinden (@fontsource) derlenir; uzak @import yok.
        $scss = file_get_contents(resource_path('scss/app.scss'));

        $this->assertStringContainsString("@import '@fontsource/plus-jakarta-sans/", $scss);

        foreach (glob(resource_path('scss/*.scss')) as $file) {
            $this->assertDoesNotMatchRegularExpression('#fonts\.(googleapis|gstatic)\.com#', file_get_contents($file), basename($file).' uzak yazı tipi yüklüyor.');
        }

        foreach (['/', '/iletisim'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $this->assertStringNotContainsString('fonts.googleapis.com', $html);
            $this->assertStringNotContainsString('fonts.gstatic.com', $html);
        }
    }

    public function test_logos_are_vector_and_versioned(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // Marka işareti küçük bir SVG'dir; adı sabit olduğu için sürüm eki ile önbellek kırılır.
        $this->assertMatchesRegularExpression('#images/brand/logo-mark\.svg\?v=\d+#', $html);
        $this->assertStringNotContainsString('logo-horizontal', $html);

        foreach (['logo.svg', 'logo-light.svg', 'logo-mark.svg'] as $file) {
            $path = public_path('images/brand/'.$file);

            $this->assertFileExists($path);
            $this->assertLessThan(20 * 1024, filesize($path), "{$file} 20 KB sınırını aşıyor.");
        }
    }

    public function test_tour_card_images_are_lazy_with_dimensions(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        preg_match_all('#<article class="tour-card[^"]*">.*?<img([^>]+)>#s', $html, $cards);

        $this->assertSame(TourDeparture::query()->bookable()->count(), count($cards[1]));
        $this->assertGreaterThan(0, count($cards[1]));

        foreach ($cards[1] as $attributes) {
            $this->assertStringContainsString('loading="lazy"', $attributes);
            $this->assertStringContainsString('width="640" height="480"', $attributes);
            $this->assertStringNotContainsString('fetchpriority', $attributes);
        }
    }

    // ---------- Görsel kopyaları ----------

    public function test_variant_is_generated_once_and_cropped_to_the_requested_ratio(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('site/hero.webp', $this->fakeWebp(1920, 1440));

        $url = app(ImageVariants::class)->url('site/hero.webp', 720, 960, 60);

        $this->assertStringContainsString(ImageVariants::DIRECTORY.'/', $url);

        $files = Storage::disk('public')->files(ImageVariants::DIRECTORY);
        $this->assertCount(1, $files);
        [$w, $h] = getimagesize(Storage::disk('public')->path($files[0]));
        $this->assertSame([720, 960], [$w, $h]);

        // İkinci çağrı yeni dosya üretmez.
        app(ImageVariants::class)->url('site/hero.webp', 720, 960, 60);
        $this->assertCount(1, Storage::disk('public')->files(ImageVariants::DIRECTORY));
    }

    public function test_variant_never_upscales(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('site/small.webp', $this->fakeWebp(800, 600));

        app(ImageVariants::class)->url('site/small.webp', 1920);

        $file = Storage::disk('public')->files(ImageVariants::DIRECTORY)[0];
        $this->assertSame(800, getimagesize(Storage::disk('public')->path($file))[0]);
    }

    public function test_replacing_the_image_produces_a_new_variant_name(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');
        $disk->put('site/hero.webp', $this->fakeWebp(1600, 1200));
        $first = app(ImageVariants::class)->url('site/hero.webp', 1280);

        $disk->put('site/hero.webp', $this->fakeWebp(1700, 1200));
        touch($disk->path('site/hero.webp'), time() + 5);
        $second = app(ImageVariants::class)->url('site/hero.webp', 1280);

        // Tarayıcılar kopyaları bir yıl önbellekte tutar; aynı ad eski görseli gösterirdi.
        $this->assertNotSame($first, $second);
    }

    public function test_variant_falls_back_to_the_original_when_it_cannot_be_made(): void
    {
        Storage::fake('public');
        $variants = app(ImageVariants::class);

        $this->assertNull($variants->url(null, 720));
        $this->assertSame('https://ornek.com/a.webp', $variants->url('https://ornek.com/a.webp', 720));
        $this->assertSame(Storage::disk('public')->url('yok/boyle.webp'), $variants->url('yok/boyle.webp', 720));

        Storage::disk('public')->put('site/bozuk.webp', 'görsel değil');
        $this->assertSame(Storage::disk('public')->url('site/bozuk.webp'), $variants->url('site/bozuk.webp', 720));
    }

    public function test_versioned_asset_changes_when_the_file_changes(): void
    {
        $url = versioned_asset('images/brand/logo.svg');

        $this->assertMatchesRegularExpression('#/images/brand/logo\.svg\?v=\d+$#', $url);
        $this->assertStringEndsWith('?v='.filemtime(public_path('images/brand/logo.svg')), $url);
        $this->assertStringEndsWith('/images/yok.webp', versioned_asset('images/yok.webp'));
    }

    // ---------- Cloudflare ve JS ----------

    public function test_visible_emails_are_excluded_from_cloudflare_obfuscation(): void
    {
        // Cloudflare gizlediği her e-posta için kritik yola email-decode.min.js ekliyordu.
        Setting::set('email', 'info@ornek.test');

        foreach (['/', '/iletisim'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            preg_match_all('/<a href="mailto:[^"]*">/', $html, $links);
            $this->assertNotEmpty($links[0], "{$url} sayfasında e-posta bağlantısı bulunamadı.");
            $this->assertSame(
                count($links[0]),
                preg_match_all('/<!--email_off--><a href="mailto:/', $html),
                "{$url} sayfasında email_off ile sarılmamış bir e-posta var."
            );
        }
    }

    public function test_site_js_does_not_read_the_scroll_position(): void
    {
        // window.scrollY okumak Chrome'u düzeni yeniden hesaplamaya zorluyordu
        // (PageSpeed "zorunlu yeniden düzenleme"). Kaydırma IntersectionObserver ile izlenir.
        $js = file_get_contents(resource_path('js/app.js'));
        $code = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $js);

        $this->assertStringNotContainsString('window.scrollY', $code);
        $this->assertStringNotContainsString('pageYOffset', $code);
    }

    public function test_recaptcha_loader_works_with_an_older_page_config(): void
    {
        // Yalnız derlenmiş dosyalar yüklenip şablonlar eski kaldığında yapılandırmada "src"
        // yoktu; form jetonsuz gidip reddediliyordu. Yükleyici adresi anahtardan üretmeli.
        $js = file_get_contents(resource_path('js/app.js'));

        $this->assertStringContainsString('cfg.src ||', $js);
        $this->assertStringContainsString('api.js?render=${encodeURIComponent(cfg.siteKey)}', $js);
    }

    /** Tek bir isteğin attığı sorgu sayısı. İstek başına tutulan veriler (ayarlar) önce sıfırlanır. */
    private function countQueries(string $url): int
    {
        $this->app->forgetScopedInstances();

        $count = 0;
        $counting = true;
        DB::listen(function () use (&$count, &$counting) {
            if ($counting) {
                $count++;
            }
        });

        $this->get($url)->assertOk();
        $counting = false;

        return $count;
    }

    private function fakeWebp(int $width, int $height): string
    {
        $file = UploadedFile::fake()->image('x.webp', $width, $height);

        return (string) file_get_contents($file->getRealPath());
    }
}
