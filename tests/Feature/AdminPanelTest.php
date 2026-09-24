<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\TourDepartures\Pages\CreateTourDeparture;
use App\Filament\Resources\TourDepartures\Pages\EditTourDeparture;
use App\Filament\Resources\TourDepartures\Pages\ListTourDepartures;
use App\Models\JobApplication;
use App\Models\ReservationRequest;
use App\Models\Setting;
use App\Models\TourDeparture;
use App\Models\TourGroup;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesStaff;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use CreatesStaff, RefreshDatabase;

    protected bool $seed = true;

    public function test_guests_are_redirected_to_login(): void
    {
        foreach (['/admin', '/admin/turlar', '/admin/gruplar', '/admin/yolcular', '/admin/iletisim-talepleri', '/admin/kasa', '/admin/site-settings'] as $url) {
            $this->get($url)->assertRedirect('/admin/login');
        }

        $this->get('/admin/login')->assertOk();
    }

    public function test_all_admin_pages_render_for_admin(): void
    {
        $request = ReservationRequest::create(['name' => 'Test', 'phone' => '05321112233', 'people_count' => 3, 'kvkk_accepted' => true]);
        JobApplication::create(['name' => 'Aday', 'phone' => '05321112233', 'position' => 'Rehber']);
        $departure = TourDeparture::query()->orderBy('starts_at')->firstOrFail();
        $group = TourGroup::query()->firstOrFail();

        $urls = [
            '/admin',
            '/admin/turlar', '/admin/turlar/create',
            '/admin/turlar/'.$departure->id, '/admin/turlar/'.$departure->id.'/edit',
            '/admin/turlar/'.$departure->id.'/arac-dagilimi',
            '/admin/gruplar', '/admin/gruplar/create', '/admin/gruplar/create?departure='.$departure->id,
            '/admin/gruplar/create?request='.$request->id,
            '/admin/gruplar/'.$group->id, '/admin/gruplar/'.$group->id.'/edit',
            '/admin/yolcular',
            '/admin/vehicles',
            '/admin/arac-sihirbazi', '/admin/arac-sihirbazi?tour='.$departure->id,
            '/admin/arac-gecmisi',
            '/admin/iletisim-talepleri', '/admin/iletisim-talepleri/'.$request->id, '/admin/iletisim-talepleri/'.$request->id.'/edit',
            '/admin/is-basvurulari',
            '/admin/personel',
            '/admin/kazanclarim',
            '/admin/kasa', '/admin/komisyon-raporu',
            '/admin/site-settings', '/admin/system-commands',
        ];

        $this->actingAs($this->admin());

        foreach ($urls as $url) {
            $response = $this->get($url);
            $this->assertSame(200, $response->getStatusCode(), $url.' açılmadı (HTTP '.$response->getStatusCode().').');
        }
    }

    public function test_removed_modules_are_gone_from_the_panel(): void
    {
        $this->actingAs($this->admin());

        foreach (['/admin/tours', '/admin/tour-categories', '/admin/blogs', '/admin/gallery-items', '/admin/pages', '/admin/provinces',
            '/admin/seo-dashboard', '/admin/seo-keywords', '/admin/analytics', '/admin/redirects', '/admin/not-found-logs', '/admin/rezervasyon-talepleri', '/admin/users'] as $url) {
            $this->get($url)->assertNotFound();
        }

        $html = $this->get('/admin')->getContent();

        foreach (['SEO & AI', 'Blog Yazıları', 'Galeri', 'Bölgeler', 'Gözlem', 'Sayfalar', 'Sık Sorulan Sorular'] as $label) {
            $this->assertStringNotContainsString($label, $html, $label.' menüde kalmamalı');
        }

        foreach (['Tüm Turlar', 'Yolcu Ekle', 'Araç Liste Sihirbazı', 'Araç Geçmişi', 'İletişim Talepleri', 'İş Başvuruları', 'Personel', 'Kazançlarım', 'Kasa', 'Komisyon Raporu'] as $label) {
            $this->assertStringContainsString($label, $html, $label.' menüde olmalı');
        }
    }

    public function test_dashboard_has_only_stats_and_the_chart(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('Yaklaşan tur', $html);
        $this->assertStringContainsString('Turlara göre yolcu', $html);
        $this->assertStringNotContainsString('Son rezervasyon talepleri', $html);
    }

    public function test_settings_page_saves_and_site_reflects_change(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SiteSettings::class)
            ->fillForm([
                'site_name' => 'Deneme Geziyor',
                'site_tagline' => 'Gez · Gör · Anlat',
                'phone' => '+90 532 999 88 77',
                'whatsapp' => '905329998877',
                'instagram_handle' => 'denemegeziyor',
                'hero_title' => 'Bu Ayın',
                'hero_highlight' => 'Rotaları',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Deneme Geziyor', Setting::where('key', 'site_name')->value('value'));

        $this->get('/')
            ->assertSee('DENEME')
            ->assertSee('GEZİYOR')
            ->assertSee('Gez · Gör · Anlat')
            ->assertSee('tel:+905329998877', false)
            ->assertSee('https://wa.me/905329998877', false)
            ->assertSee('@denemegeziyor')
            ->assertSee('https://www.instagram.com/denemegeziyor/', false)
            ->assertSee('Bu Ayın');
    }

    public function test_seeded_settings_can_be_saved_unchanged(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $before = Setting::pluck('value', 'key')->all();

        Livewire::test(SiteSettings::class)->call('save')->assertHasNoFormErrors();

        foreach (['hero_image', 'hero_title', 'whatsapp_message', 'site_name'] as $key) {
            $this->assertSame($before[$key], Setting::where('key', $key)->value('value'), $key.' değişmemeli');
        }
    }

    public function test_settings_pages_need_the_settings_permission(): void
    {
        $this->actingAs($this->operations())->get('/admin/site-settings')->assertForbidden();
        $this->actingAs($this->staff(['settings.manage']))->get('/admin/site-settings')->assertOk();
    }

    public function test_tour_can_be_created_from_the_panel_and_appears_on_the_site(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateTourDeparture::class)
            ->fillForm([
                'title' => 'Karadeniz Sahil Turu',
                'badge' => 'Yeni',
                'short_description' => 'Sahil boyunca bir gün.',
                'starts_at' => now()->addDays(12)->setTime(9, 0)->format('Y-m-d H:i:s'),
                'price' => 850,
                'status' => 'open',
                'is_public' => true,
                'meeting_point' => 'Yerleşke önü',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tour = TourDeparture::where('title', 'Karadeniz Sahil Turu')->firstOrFail();

        $this->assertSame('KAR-'.now()->addDays(12)->format('dmy'), $tour->code);
        $this->assertSame(now()->addDays(12)->toDateString(), $tour->ends_on->toDateString(), 'günübirlik: dönüş = kalkış günü');

        $this->get('/')->assertOk()
            ->assertSee('Karadeniz Sahil Turu')
            ->assertSee('Sahil boyunca bir gün.')
            ->assertSee('850 ₺')
            ->assertSee('Yeni');
    }

    public function test_seeded_tours_can_be_saved_unchanged_from_the_panel(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        foreach (TourDeparture::all() as $tour) {
            Livewire::test(EditTourDeparture::class, ['record' => $tour->getKey()])
                ->call('save')
                ->assertHasNoFormErrors();

            $fresh = $tour->fresh();
            $this->assertSame($tour->image, $fresh->image, $tour->title.': görsel değişmemeli');
            $this->assertSame((string) $tour->price, (string) $fresh->price, $tour->title.': fiyat değişmemeli');
            // Zengin metin editörü HTML'i yeniden biçimler (&#039;, <li><p>); metin birebir aynı kalmalı.
            $plain = fn (?string $html) => preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode((string) $html, ENT_QUOTES | ENT_HTML5)));
            $this->assertSame($plain($tour->description), $plain($fresh->description), $tour->title.': açıklama değişmemeli');
        }
    }

    public function test_tours_table_hides_code_waiting_and_guide_columns(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin/turlar')->assertOk()->getContent();

        $this->assertStringContainsString('Boş koltuk', $html);
        $this->assertStringNotContainsString('Yerleşmeyen', $html);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(ListTourDepartures::class)->assertTableFilterExists('dates')->assertTableFilterExists('upcoming');
        $this->assertStringNotContainsString('>Kod<', $html);
        $this->assertStringNotContainsString('>Rehber<', $html);
    }

    public function test_panel_carries_pwa_manifest_and_mobile_tabs(): void
    {
        $html = $this->actingAs($this->admin())->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('rel="manifest"', $html);
        $this->assertStringContainsString('apple-mobile-web-app-capable', $html);
        $this->assertStringContainsString('rg-tabs', $html);

        $this->get('/admin/manifest.webmanifest')->assertOk()
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('start_url', '/admin');
    }
}
