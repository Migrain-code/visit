<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\Districts\Pages\EditDistrict;
use App\Filament\Resources\Provinces\Pages\EditProvince;
use App\Filament\Resources\TourCategories\Pages\EditTourCategory;
use App\Filament\Resources\Tours\Pages\CreateTour;
use App\Filament\Resources\Tours\Pages\EditTour;
use App\Models\District;
use App\Models\GalleryItem;
use App\Models\Page;
use App\Models\Province;
use App\Models\ReservationRequest;
use App\Models\Setting;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Models\TourDeparture;
use App\Models\TourGroup;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        foreach (['/admin', '/admin/tours', '/admin/tur-kayitlari', '/admin/gruplar', '/admin/yolcular', '/admin/rezervasyon-talepleri', '/admin/site-settings'] as $url) {
            $this->get($url)->assertRedirect('/admin/login');
        }

        $this->get('/admin/login')->assertOk();
    }

    public function test_all_admin_pages_render_for_admin(): void
    {
        $request = ReservationRequest::create(['name' => 'Test', 'phone' => '05321112233', 'people_count' => 3, 'kvkk_accepted' => true]);
        $departure = TourDeparture::query()->orderBy('starts_at')->firstOrFail();
        $group = TourGroup::query()->firstOrFail();

        $urls = [
            '/admin',
            '/admin/site-settings',
            // Operasyon
            '/admin/tur-kayitlari', '/admin/tur-kayitlari/create',
            '/admin/tur-kayitlari/'.$departure->id, '/admin/tur-kayitlari/'.$departure->id.'/edit',
            '/admin/tur-kayitlari/'.$departure->id.'/arac-dagilimi',
            '/admin/gruplar', '/admin/gruplar/create', '/admin/gruplar/create?departure='.$departure->id,
            '/admin/gruplar/create?request='.$request->id,
            '/admin/gruplar/'.$group->id, '/admin/gruplar/'.$group->id.'/edit',
            '/admin/yolcular',
            '/admin/vehicles',
            '/admin/rezervasyon-talepleri', '/admin/rezervasyon-talepleri/'.$request->id, '/admin/rezervasyon-talepleri/'.$request->id.'/edit',
            // Katalog ve içerik
            '/admin/tours', '/admin/tours/create', '/admin/tours/'.Tour::first()->id.'/edit',
            '/admin/tour-categories', '/admin/tour-categories/create', '/admin/tour-categories/'.TourCategory::first()->id.'/edit',
            '/admin/provinces', '/admin/provinces/create', '/admin/provinces/'.Province::first()->id.'/edit',
            '/admin/districts', '/admin/districts/create', '/admin/districts/'.District::first()->id.'/edit',
            '/admin/gallery-items', '/admin/gallery-items/create', '/admin/gallery-items/'.GalleryItem::first()->id.'/edit',
            '/admin/gallery-categories',
            '/admin/testimonials',
            '/admin/faqs',
            '/admin/features',
            '/admin/pages', '/admin/pages/create', '/admin/pages/'.Page::first()->id.'/edit',
            '/admin/users',
        ];

        $this->actingAs($this->admin());

        foreach ($urls as $url) {
            $response = $this->get($url);
            $this->assertSame(200, $response->getStatusCode(), $url.' açılmadı (HTTP '.$response->getStatusCode().').');
        }
    }

    public function test_settings_page_saves_and_site_reflects_change(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SiteSettings::class)
            ->fillForm([
                'site_name' => 'Deneme Turizm',
                'phone' => '+90 532 999 88 77',
                'whatsapp' => '905329998877',
                'hero_title' => 'Yeni Başlık Trakya Turları',
                'tursab_no' => 'A-12345',
                'company_title' => 'Deneme Turizm Seyahat Acentası Ltd. Şti.',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Deneme Turizm', Setting::where('key', 'site_name')->value('value'));

        $this->get('/')
            ->assertSee('Deneme Turizm')
            ->assertSee('tel:+905329998877', false)
            ->assertSee('https://wa.me/905329998877', false)
            // Seyahat acentesi belge numarası alt bilgide görünmelidir (yasal zorunluluk).
            ->assertSee('A-12345')
            ->assertSee('Deneme Turizm Seyahat Acentası Ltd. Şti.');
    }

    public function test_tour_can_be_created_and_slug_collisions_are_blocked(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateTour::class)
            ->fillForm([
                'title' => 'Edirne Kültür Turu',
                'slug' => 'edirne-kultur-turu',
                'short_description' => 'Selimiye Camii ve Osmanlı başkenti Edirne.',
                'duration_days' => 1,
                'duration_nights' => 0,
                'price' => 1500,
                'currency' => 'TRY',
                'highlights' => [['item' => 'Selimiye Camii'], ['item' => 'Meriç Köprüsü']],
                'itinerary' => [['title' => 'Sabah: Selimiye', 'description' => 'Rehberli cami ve külliye gezisi.']],
                'faqs' => [['question' => 'Yemek dahil mi?', 'answer' => 'Hayır, serbest zaman verilir.']],
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = Tour::where('slug', 'edirne-kultur-turu')->firstOrFail();
        $this->assertSame(['Selimiye Camii', 'Meriç Köprüsü'], $created->highlights, 'basit liste düz dizi olarak saklanmalı');
        $this->assertSame('Günübirlik', $created->duration_label);

        $this->get('/edirne-kultur-turu')->assertOk()
            ->assertSee('Selimiye Camii')
            ->assertSee('Sabah: Selimiye')
            ->assertSee('Yemek dahil mi?')
            ->assertSee('1.500 ₺');

        // Kök dizindeki adresler birbiriyle çakışamaz: "rize" bir ilin adresidir.
        Livewire::test(CreateTour::class)
            ->fillForm(['title' => 'Rize', 'slug' => 'rize', 'duration_days' => 1, 'duration_nights' => 0, 'currency' => 'TRY'])
            ->call('create')
            ->assertHasFormErrors(['slug']);

        // Sabit rotalar da ayrılmıştır.
        Livewire::test(CreateTour::class)
            ->fillForm(['title' => 'Rezervasyon', 'slug' => 'rezervasyon', 'duration_days' => 1, 'duration_nights' => 0, 'currency' => 'TRY'])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    public function test_old_price_must_be_higher_than_price(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateTour::class)
            ->fillForm([
                'title' => 'İndirimsiz İndirim', 'slug' => 'indirimsiz-indirim',
                'duration_days' => 1, 'duration_nights' => 0, 'currency' => 'TRY',
                'price' => 2000, 'old_price' => 1500,
            ])
            ->call('create')
            ->assertHasFormErrors(['old_price']);
    }

    public function test_seeded_records_can_be_saved_unchanged_from_the_panel(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        foreach (Tour::all() as $tour) {
            Livewire::test(EditTour::class, ['record' => $tour->getKey()])
                ->call('save')
                ->assertHasNoFormErrors();

            $fresh = $tour->fresh();
            $this->assertSame($tour->highlights, $fresh->highlights, $tour->slug.': liste bozulmamalı');
            $this->assertSame($tour->included, $fresh->included);
            $this->assertSame($tour->itinerary, $fresh->itinerary);
            $this->assertSame($tour->faqs, $fresh->faqs);
            $this->assertSame($tour->image, $fresh->image);
            $this->assertSame((string) $tour->price, (string) $fresh->price, $tour->slug.': fiyat değişmemeli');
        }

        foreach (TourCategory::all() as $category) {
            Livewire::test(EditTourCategory::class, ['record' => $category->getKey()])
                ->call('save')
                ->assertHasNoFormErrors();

            $this->assertSame($category->faqs, $category->fresh()->faqs);
        }

        foreach (Province::all() as $province) {
            Livewire::test(EditProvince::class, ['record' => $province->getKey()])
                ->call('save')
                ->assertHasNoFormErrors();
        }

        foreach (District::all() as $district) {
            Livewire::test(EditDistrict::class, ['record' => $district->getKey()])
                ->call('save')
                ->assertHasNoFormErrors();

            $fresh = $district->fresh();
            $this->assertSame($district->pickup_points, $fresh->pickup_points, $district->slug.': biniş noktaları bozulmamalı');
            $this->assertSame($district->faqs, $fresh->faqs);
        }
    }

    public function test_seeded_settings_can_be_saved_unchanged(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $before = Setting::pluck('value', 'key')->all();

        Livewire::test(SiteSettings::class)->call('save')->assertHasNoFormErrors();

        foreach (['hero_image', 'about_image', 'hero_title', 'whatsapp_message'] as $key) {
            $this->assertSame($before[$key], Setting::where('key', $key)->value('value'), $key.' değişmemeli');
        }

        // Zengin metin editörü kesme işaretini &#039; olarak kodlar; tarayıcıda aynı görünür.
        // İçeriğin kendisi (varlıklar çözüldükten sonra) birebir aynı kalmalıdır.
        $this->assertSame(
            $before['about_text'],
            html_entity_decode(Setting::where('key', 'about_text')->value('value'), ENT_QUOTES | ENT_HTML5),
            'about_text içeriği değişmemeli'
        );
    }

    public function test_tour_with_departures_cannot_be_deleted(): void
    {
        $tour = TourDeparture::query()->firstOrFail()->tour;

        // Veritabanı da korur: yolcu kayıtları sefere, sefer tura bağlıdır.
        $this->expectException(QueryException::class);

        $tour->delete();
    }
}
