<?php

namespace Tests\Feature;

use App\Enums\DepartureStatus;
use App\Mail\ReservationRequestReceived;
use App\Models\District;
use App\Models\Province;
use App\Models\ReservationRequest;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Models\TourDeparture;
use App\Models\TourGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public static function publicUrls(): array
    {
        return collect([
            '/', '/turlar', '/turlar?sure=gunubirlik', '/turlar/yayla-turlari', '/turlar/gol-ve-vadi-turlari',
            '/turlar/kultur-turlari', '/turlar/batum-ve-gurcistan-turlari', '/tur-takvimi',
            '/ayder-yaylasi-turu', '/uzungol-turu', '/sumela-karaca-magarasi-hamsikoy-turu', '/gunubirlik-batum-turu',
            '/pokut-ve-sal-yaylasi-turu', '/huser-yaylasi-gun-batimi-turu', '/zilkale-ve-palovit-selalesi-turu',
            '/trabzon-sehir-turu', '/batum-tiflis-turu',
            '/bolgeler', '/rize', '/rize/rize-merkez', '/rize/ardesen',
            '/rize/camlihemsin', '/trabzon', '/trabzon/ortahisar', '/trabzon/of', '/trabzon/macka',
            '/galeri', '/galeri?kategori=yaylalar', '/hakkimizda', '/sss', '/iletisim', '/blog', '/rezervasyon',
            '/rezervasyon?tur=ayder-yaylasi-turu', '/rezervasyon/tesekkurler', '/kvkk-aydinlatma-metni',
            '/tur-sozlesmesi-ve-iptal-kosullari', '/sitemap.xml', '/robots.txt',
        ])->mapWithKeys(fn ($url) => [$url => [$url]])->all();
    }

    #[DataProvider('publicUrls')]
    public function test_public_page_loads(string $url): void
    {
        $this->get($url)->assertOk();
    }

    public function test_every_seeded_district_page_loads(): void
    {
        $this->assertSame(30, District::count());

        foreach (District::with('province')->get() as $district) {
            $this->get('/'.$district->province->slug.'/'.$district->slug)
                ->assertOk()
                ->assertSee($district->name.' Çıkışlı Günübirlik Turlar');
        }
    }

    public function test_every_seeded_tour_page_loads(): void
    {
        $this->assertSame(9, Tour::count());

        foreach (Tour::all() as $tour) {
            $this->get('/'.$tour->slug)
                ->assertOk()
                ->assertSee($tour->title)
                ->assertSee('<link rel="canonical" href="'.url('/'.$tour->slug).'">', false);
        }
    }

    public function test_unknown_urls_return_404(): void
    {
        $this->get('/olmayan-sayfa')->assertNotFound();
        $this->get('/rize/olmayan-ilce')->assertNotFound();
        $this->get('/olmayan-il/ardesen')->assertNotFound();
        $this->get('/turlar/olmayan-kategori')->assertNotFound();
    }

    public function test_removed_montaj_urls_are_gone(): void
    {
        // Eski alan adından kalan adresler yeni sitede yayınlanmamalı.
        foreach (['/hizmetler', '/markalar', '/teklif-al', '/gardirop-montaji'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    public function test_home_has_primary_conversion_points_and_seo_tags(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('Turları İncele');
        $response->assertSee('Rezervasyon Yap');
        $response->assertSee("WhatsApp'tan Yazın", false);
        $response->assertSee(route('reservation.create'), false);
        $response->assertSee(route('tours.calendar'), false);
        $response->assertSee('https://wa.me/', false);
        $response->assertSee('href="tel:+', false);
        $response->assertSee('<link rel="canonical" href="'.url('/').'">', false);
        $response->assertSee('<meta name="robots" content="index, follow">', false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('"@type":"TravelAgency"', false);
        $response->assertSee('loading="lazy"', false);
        $response->assertSee('Visit Tur');
    }

    public function test_home_lists_featured_tours_categories_and_upcoming_departures(): void
    {
        $response = $this->get('/')->assertOk();

        // Ana sayfa en çok altı öne çıkan tur gösterir; tamamı /turlar sayfasındadır.
        $response->assertViewHas('tours', fn ($tours) => $tours->count() === 6);
        $response->assertViewHas('categories', fn ($categories) => $categories->count() === 4);
        // En yakın altı sefer; hepsi satışta olmalı.
        $response->assertViewHas('departures', fn ($departures) => $departures->count() === 6
            && $departures->every(fn (TourDeparture $d) => $d->is_public && $d->status === DepartureStatus::Open && $d->starts_at->isFuture()));

        $response->assertSee('Öne Çıkan Turlar');
        $response->assertSee('Yaklaşan Turlar');
        $response->assertSee('Ayder Yaylası Turu');
        $response->assertSee(url('/turlar/yayla-turlari'), false);
    }

    public function test_phone_and_whatsapp_come_from_settings_not_code(): void
    {
        Setting::set('phone', '+90 532 111 22 33');
        Setting::set('whatsapp', '905321112233');
        Setting::flush();

        $this->get('/')
            ->assertSee('tel:+905321112233', false)
            ->assertSee('https://wa.me/905321112233', false)
            ->assertSee('+90 532 111 22 33');
    }

    public function test_district_page_has_local_content_and_prefilled_whatsapp_message(): void
    {
        $response = $this->get('/rize/ardesen')->assertOk();

        $response->assertSee('Ardeşen Çıkışlı Günübirlik Turlar');
        $response->assertSee('Ardeşen için sık sorulan sorular');
        $response->assertSee('Ardeşen Çıkışlı Turlarımız');
        $response->assertSee('Ayder Yaylası Turu');
        // WhatsApp mesajı bölgeyi hazır getirir.
        $response->assertSee(rawurlencode('Katılacağım bölge: Ardeşen, Rize'), false);
        $response->assertSee('BreadcrumbList', false);
        $response->assertSee('FAQPage', false);
    }

    public function test_pickup_points_are_shown_only_when_entered(): void
    {
        // Biniş noktaları bilerek boş tohumlanır: uydurma durak yayınlanmaz.
        $this->get('/rize/ardesen')->assertOk()->assertDontSee('Ardeşen biniş noktaları');

        District::where('slug', 'ardesen')->firstOrFail()->update(['pickup_points' => ['Ardeşen Otogar', 'Belediye Meydanı önü']]);

        $this->get('/rize/ardesen')
            ->assertOk()
            ->assertSee('Ardeşen biniş noktaları')
            ->assertSee('Ardeşen Otogar')
            ->assertSee('Belediye Meydanı önü');
    }

    /** Tohumlanan örnek seferleri temizler (grup kaydı olan sefer doğrudan silinemez). */
    private function clearDepartures(): void
    {
        TourGroup::query()->delete();
        TourDeparture::query()->delete();
    }

    /** Sefere, verilen sayıda yolcusu olan bir grup yazar (yolcu sayısı satırlardan türer). */
    private function fillSeats(TourDeparture $departure, int $passengers): TourGroup
    {
        $group = $departure->groups()->create([
            'name' => 'Test grubu', 'contact_name' => 'Test Kişi', 'contact_phone' => '0532 000 00 01',
        ]);

        foreach (range(1, $passengers) as $i) {
            $group->passengers()->create(['first_name' => 'Yolcu', 'last_name' => (string) $i, 'sort_order' => $i]);
        }

        return $group->fresh();
    }

    // ---------- Tur sayfası ----------

    public function test_tour_page_has_required_sections(): void
    {
        $this->get('/ayder-yaylasi-turu')
            ->assertOk()
            ->assertSee('Bu turda öne çıkanlar')
            ->assertSee('Tur programı')
            ->assertSee('Fiyata neler dahil?')
            ->assertSee('Dahil olanlar')
            ->assertSee('Dahil olmayanlar')
            ->assertSee('Ayder Yaylası Turu hakkında sık sorulanlar')
            ->assertSee('Bu tura nereden katılabilirim?')
            ->assertSee('Tur tarihleri')
            ->assertSee('Rezervasyon Yap')
            ->assertSee(route('reservation.create', ['tur' => 'ayder-yaylasi-turu']), false)
            ->assertSee('<link rel="canonical" href="'.url('/ayder-yaylasi-turu').'">', false)
            ->assertSee('"@type":"TouristTrip"', false)
            ->assertSee('"@type":"Offer"', false)
            ->assertSee('BreadcrumbList', false)
            ->assertSee('FAQPage', false);
    }

    public function test_tour_page_shows_price_itinerary_and_upcoming_departure_dates(): void
    {
        // Batum Tiflis: tohumdaki tek çok günlü ve eski fiyatı (indirimi) olan tur.
        $tour = Tour::where('slug', 'batum-tiflis-turu')->firstOrFail();
        $tour->departures()->delete();

        $open = $tour->departures()->create(['starts_at' => now()->addDays(20)->setTime(6, 30), 'price' => 9250]);
        $past = $tour->departures()->create(['starts_at' => now()->subDays(3)->setTime(6, 30), 'price' => 7111]);
        $closed = $tour->departures()->create(['starts_at' => now()->addDays(40)->setTime(6, 30), 'price' => 7222, 'status' => DepartureStatus::Closed]);
        $hidden = $tour->departures()->create(['starts_at' => now()->addDays(50)->setTime(6, 30), 'price' => 7333, 'is_public' => false]);

        $response = $this->get('/batum-tiflis-turu')->assertOk();

        // Fiyat: katalog fiyatı, üstü çizili eski fiyat ve fiyat notu.
        $response->assertSee('9.800 ₺');
        $response->assertSee('10.500 ₺');
        $response->assertSee('Kişi başı, çift kişilik odada. Yurt dışı çıkış harcı dahil değildir.');

        // Program gün gün yazılıdır.
        $response->assertSee('1. Gün: Sarp ve Batum');
        $response->assertSee('2. Gün: Tiflis');
        $response->assertSee('3. Gün: Narikala ve dönüş');

        // Yalnız satıştaki sefer listelenir; sefere özel fiyat ve ön seçimli bağlantı ile.
        $response->assertViewHas('departures', fn ($departures) => $departures->pluck('id')->all() === [$open->id]);
        $response->assertSee($open->fresh()->date_range_label);
        $response->assertSee('9.250 ₺');
        $response->assertSee('sefer='.$open->id, false);
        $response->assertSee('1 tarih açık');

        foreach ([$past, $closed, $hidden] as $departure) {
            $response->assertDontSee('sefer='.$departure->id, false);
        }

        $response->assertDontSee('7.111 ₺')->assertDontSee('7.222 ₺')->assertDontSee('7.333 ₺');
    }

    public function test_tour_without_departures_invites_the_visitor_to_ask(): void
    {
        $tour = Tour::where('slug', 'trabzon-sehir-turu')->firstOrFail();
        $tour->departures()->delete();

        $this->get('/trabzon-sehir-turu')
            ->assertOk()
            ->assertSee('yeni tarihler yakında açıklanacak')
            ->assertDontSee(' tarih açık<', false)
            // Sefer yoksa şema katalog fiyatına düşer; uydurma tarih basılmaz.
            ->assertSee('"price":"1100.00"', false)
            ->assertDontSee('availabilityEnds', false);
    }

    public function test_inactive_tour_is_hidden(): void
    {
        Tour::where('slug', 'huser-yaylasi-gun-batimi-turu')->update(['is_active' => false]);

        $this->get('/huser-yaylasi-gun-batimi-turu')->assertNotFound();
        $this->get('/turlar')->assertOk()->assertDontSee('Huser Yaylası Gün Batımı Turu');
        $this->get('/')->assertOk()->assertDontSee('Huser Yaylası Gün Batımı Turu');
        $this->get('/tur-takvimi')->assertOk()->assertDontSee('Huser Yaylası Gün Batımı Turu');
        $this->get('/rezervasyon')->assertOk()->assertDontSee('Huser Yaylası Gün Batımı Turu');
        $this->get('/sitemap.xml')->assertOk()->assertDontSee(url('/huser-yaylasi-gun-batimi-turu'), false);
    }

    // ---------- Tur listesi ve kategoriler ----------

    public function test_tour_list_shows_all_active_tours_and_is_indexable(): void
    {
        $response = $this->get('/turlar')->assertOk();

        $response->assertViewHas('tours', fn ($tours) => $tours->count() === 9);
        $response->assertSee('<link rel="canonical" href="'.url('/turlar').'">', false);
        $response->assertSee('<meta name="robots" content="index, follow">', false);

        foreach (TourCategory::all() as $category) {
            $response->assertSee($category->url, false);
        }
    }

    public function test_duration_filter_narrows_the_list_and_is_noindex(): void
    {
        $response = $this->get('/turlar?sure=gunubirlik')->assertOk();

        // Batum Tiflis dışındaki sekiz tur günübirliktir.
        $response->assertViewHas('tours', fn ($tours) => $tours->pluck('slug')->sort()->values()->all() === [
            'ayder-yaylasi-turu', 'gunubirlik-batum-turu', 'huser-yaylasi-gun-batimi-turu', 'pokut-ve-sal-yaylasi-turu',
            'sumela-karaca-magarasi-hamsikoy-turu', 'trabzon-sehir-turu', 'uzungol-turu', 'zilkale-ve-palovit-selalesi-turu',
        ]);
        // Filtreli liste ana listenin alt kümesidir: dizine girmez, canonical ana listeyi gösterir.
        $response->assertSee('<meta name="robots" content="noindex, follow">', false);
        $response->assertSee('<link rel="canonical" href="'.url('/turlar').'">', false);

        $this->get('/turlar?sure=kisa')->assertOk()
            ->assertViewHas('tours', fn ($tours) => $tours->pluck('slug')->all() === ['batum-tiflis-turu']);

        // Tohumda 3 gece ve üzeri tur yok: filtre boş döner; böyle bir tur eklenince yalnız o listelenir.
        $this->get('/turlar?sure=uzun')->assertOk()->assertViewHas('tours', fn ($tours) => $tours->isEmpty());

        Tour::create(['title' => 'Kafkasya Turu', 'slug' => 'kafkasya-turu', 'duration_days' => 5, 'duration_nights' => 4, 'price' => 15000]);

        $this->get('/turlar?sure=uzun')->assertOk()
            ->assertViewHas('tours', fn ($tours) => $tours->pluck('slug')->all() === ['kafkasya-turu']);
    }

    public function test_unknown_duration_filter_is_ignored(): void
    {
        $this->get('/turlar?sure=uydurma')
            ->assertOk()
            ->assertViewHas('tours', fn ($tours) => $tours->count() === 9)
            ->assertSee('<meta name="robots" content="index, follow">', false);
    }

    public function test_category_page_lists_only_its_own_tours(): void
    {
        $category = TourCategory::where('slug', 'yayla-turlari')->firstOrFail();

        $response = $this->get('/turlar/yayla-turlari')->assertOk();

        $response->assertViewHas('tours', fn ($tours) => $tours->count() === 3
            && $tours->every(fn (Tour $t) => $t->tour_category_id === $category->id));
        $response->assertSee('Yayla Turları hakkında');
        $response->assertSee('<link rel="canonical" href="'.url('/turlar/yayla-turlari').'">', false);
        $response->assertSee('BreadcrumbList', false);
        $response->assertSee('"@type":"ItemList"', false);

        // Süre filtresi kategori içinde de çalışır ve dizine girmez.
        $this->get('/turlar/batum-ve-gurcistan-turlari?sure=kisa')
            ->assertOk()
            ->assertViewHas('tours', fn ($tours) => $tours->pluck('slug')->all() === ['batum-tiflis-turu'])
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    public function test_inactive_category_is_hidden(): void
    {
        TourCategory::where('slug', 'gol-ve-vadi-turlari')->update(['is_active' => false]);

        $this->get('/turlar/gol-ve-vadi-turlari')->assertNotFound();
        $this->get('/turlar')->assertOk()->assertDontSee(url('/turlar/gol-ve-vadi-turlari'), false);
        $this->get('/')->assertOk()->assertDontSee(url('/turlar/gol-ve-vadi-turlari'), false);
        $this->get('/sitemap.xml')->assertOk()->assertDontSee(url('/turlar/gol-ve-vadi-turlari'), false);

        $this->get('/rize/ardesen')->assertOk()->assertDontSee(url('/turlar/gol-ve-vadi-turlari'), false);

        // Kategorisi kapatılan tur yayında kalır; ama 404 dönen kategori sayfasına bağlantı vermez.
        $this->get('/uzungol-turu')
            ->assertOk()
            ->assertSee('Uzungöl Turu')
            ->assertDontSee(url('/turlar/gol-ve-vadi-turlari'), false);
    }

    // ---------- Tur takvimi ----------

    public function test_calendar_lists_bookable_departures_and_hides_the_rest(): void
    {
        $this->clearDepartures();

        $ayder = Tour::where('slug', 'ayder-yaylasi-turu')->firstOrFail();
        $uzungol = Tour::where('slug', 'uzungol-turu')->firstOrFail();
        $inactive = Tour::where('slug', 'trabzon-sehir-turu')->firstOrFail();
        $inactive->update(['is_active' => false]);

        $later = $ayder->departures()->create(['starts_at' => now()->addDays(45)->setTime(8, 30)]);
        $sooner = $uzungol->departures()->create(['starts_at' => now()->addDays(10)->setTime(9, 0), 'price' => 2400]);

        $hidden = [
            'geçmiş' => $ayder->departures()->create(['starts_at' => now()->subDay()]),
            'kayda kapalı' => $ayder->departures()->create(['starts_at' => now()->addDays(12), 'status' => DepartureStatus::Closed]),
            'iptal' => $ayder->departures()->create(['starts_at' => now()->addDays(13), 'status' => DepartureStatus::Cancelled]),
            'tamamlandı' => $ayder->departures()->create(['starts_at' => now()->addDays(14), 'status' => DepartureStatus::Completed]),
            'sitede gizli' => $ayder->departures()->create(['starts_at' => now()->addDays(15), 'is_public' => false]),
            'yayında olmayan tur' => $inactive->departures()->create(['starts_at' => now()->addDays(16)]),
        ];

        $response = $this->get('/tur-takvimi')->assertOk();

        // Tarih sırasıyla, yalnız satıştaki seferler.
        $response->assertViewHas('months', fn ($months) => $months->flatten()->pluck('id')->all() === [$sooner->id, $later->id]);
        $response->assertSee('Uzungöl Turu');
        $response->assertSee('2.400 ₺');          // sefere özel fiyat
        $response->assertSee('1.400 ₺');          // fiyat girilmemişse turun fiyatı
        $response->assertSee('sefer='.$sooner->id, false);
        $response->assertSee('sefer='.$later->id, false);
        $response->assertSee('<link rel="canonical" href="'.url('/tur-takvimi').'">', false);

        foreach ($hidden as $why => $departure) {
            $response->assertDontSee('sefer='.$departure->id, false);
        }
    }

    public function test_calendar_marks_full_and_nearly_full_departures(): void
    {
        $this->clearDepartures();

        $tour = Tour::where('slug', 'ayder-yaylasi-turu')->firstOrFail();
        $full = $tour->departures()->create(['starts_at' => now()->addDays(10), 'quota' => 4]);
        $low = $tour->departures()->create(['starts_at' => now()->addDays(20), 'quota' => 10]);

        $this->fillSeats($full, 4);
        $this->fillSeats($low, 7);

        $response = $this->get('/tur-takvimi')->assertOk();

        $response->assertSee('Doldu');
        $response->assertSee('Son 3 koltuk');
        // Dolu sefere "Yer ayırt" bağlantısı verilmez.
        $response->assertDontSee('sefer='.$full->id, false);
        $response->assertSee('sefer='.$low->id, false);
    }

    public function test_empty_calendar_shows_a_helpful_message(): void
    {
        $this->clearDepartures();

        $this->get('/tur-takvimi')->assertOk()->assertSee('Şu anda kayda açık tarih yok');
    }

    // ---------- Rezervasyon formu ----------

    private function reservationPayload(array $overrides = []): array
    {
        $province = Province::where('slug', 'rize')->firstOrFail();

        return array_merge([
            'name' => 'Test Misafir',
            'phone' => '0532 111 22 33',
            'email' => 'misafir@ornek.test',
            'tour_id' => Tour::where('slug', 'ayder-yaylasi-turu')->value('id'),
            'people_count' => 3,
            'province_id' => $province->id,
            'district_id' => District::where('province_id', $province->id)->where('slug', 'ardesen')->value('id'),
            'message' => '2 yetişkin, 1 çocuk.',
            'kvkk' => '1',
        ], $overrides);
    }

    public function test_reservation_form_is_shown_on_reservation_and_contact_pages(): void
    {
        foreach (['/rezervasyon', '/iletisim'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('data-reservation-form', false)
                ->assertSee('action="'.route('reservation.store').'"', false)
                ->assertSee('name="people_count"', false)
                ->assertSee('name="tour_departure_id"', false)
                ->assertSee('name="kvkk"', false)
                ->assertSee('name="website"', false)
                ->assertSee(url('/kvkk-aydinlatma-metni'), false)
                // Fotoğraf yükleme alanı artık yok.
                ->assertDontSee('type="file"', false);
        }
    }

    public function test_reservation_form_stores_a_new_request(): void
    {
        $tour = Tour::where('slug', 'ayder-yaylasi-turu')->firstOrFail();
        $departure = $tour->upcomingDepartures()->firstOrFail();

        $this->post('/rezervasyon', $this->reservationPayload(['tour_departure_id' => $departure->id, 'source' => 'contact']))
            ->assertRedirect(route('reservation.thanks'))
            ->assertSessionHas('reservation_sent', true);

        $reservation = ReservationRequest::firstOrFail();

        $this->assertSame(ReservationRequest::STATUS_NEW, $reservation->status);
        $this->assertSame('Test Misafir', $reservation->name);
        $this->assertSame($tour->id, $reservation->tour_id);
        $this->assertSame($departure->id, $reservation->tour_departure_id);
        $this->assertSame(3, $reservation->people_count);
        $this->assertTrue($reservation->kvkk_accepted);
        $this->assertSame('contact', $reservation->source);
        $this->assertSame('Ardeşen / Rize', $reservation->location_label);
        $this->assertNull($reservation->assigned_to);
    }

    public function test_tour_is_derived_from_the_departure_when_only_a_date_is_chosen(): void
    {
        $tour = Tour::where('slug', 'ayder-yaylasi-turu')->firstOrFail();
        $departure = $tour->upcomingDepartures()->firstOrFail();

        $this->post('/rezervasyon', $this->reservationPayload(['tour_id' => null, 'tour_departure_id' => $departure->id]))
            ->assertRedirect(route('reservation.thanks'));

        $this->assertSame($tour->id, ReservationRequest::firstOrFail()->tour_id);
    }

    public function test_general_request_without_a_tour_is_accepted(): void
    {
        $this->post('/rezervasyon', [
            'name' => 'Test Misafir', 'phone' => '0532 111 22 33', 'people_count' => 1, 'kvkk' => '1',
        ])->assertRedirect(route('reservation.thanks'));

        $this->assertDatabaseHas('reservation_requests', ['name' => 'Test Misafir', 'tour_id' => null, 'status' => 'new', 'source' => 'form']);
    }

    public function test_honeypot_rejects_bots(): void
    {
        $this->post('/rezervasyon', $this->reservationPayload(['website' => 'https://spam.example']))
            ->assertSessionHasErrors('website');

        $this->assertDatabaseCount('reservation_requests', 0);
    }

    public function test_kvkk_consent_is_required(): void
    {
        $this->post('/rezervasyon', $this->reservationPayload(['kvkk' => null]))->assertSessionHasErrors('kvkk');
        $this->post('/rezervasyon', $this->reservationPayload(['kvkk' => '0']))->assertSessionHasErrors('kvkk');

        $this->assertDatabaseCount('reservation_requests', 0);
    }

    public function test_people_count_is_required_and_bounded(): void
    {
        $this->post('/rezervasyon', $this->reservationPayload(['people_count' => null]))->assertSessionHasErrors('people_count');
        $this->post('/rezervasyon', $this->reservationPayload(['people_count' => 0]))->assertSessionHasErrors('people_count');
        $this->post('/rezervasyon', $this->reservationPayload(['people_count' => 61]))->assertSessionHasErrors('people_count');
        $this->post('/rezervasyon', $this->reservationPayload(['people_count' => 'üç']))->assertSessionHasErrors('people_count');

        $this->assertDatabaseCount('reservation_requests', 0);

        $this->post('/rezervasyon', $this->reservationPayload(['people_count' => 60]))->assertSessionDoesntHaveErrors();
        $this->assertDatabaseCount('reservation_requests', 1);
    }

    public function test_name_and_a_valid_phone_are_required(): void
    {
        $this->post('/rezervasyon', $this->reservationPayload(['name' => '']))->assertSessionHasErrors('name');
        $this->post('/rezervasyon', $this->reservationPayload(['phone' => '']))->assertSessionHasErrors('phone');
        $this->post('/rezervasyon', $this->reservationPayload(['phone' => 'telefonum yok']))->assertSessionHasErrors('phone');

        $this->assertDatabaseCount('reservation_requests', 0);
    }

    public function test_departure_that_is_not_bookable_is_rejected(): void
    {
        $tour = Tour::where('slug', 'ayder-yaylasi-turu')->firstOrFail();

        $notBookable = [
            'kayda kapalı' => $tour->departures()->create(['starts_at' => now()->addDays(12), 'status' => DepartureStatus::Closed]),
            'sitede gizli' => $tour->departures()->create(['starts_at' => now()->addDays(13), 'is_public' => false]),
            'geçmiş' => $tour->departures()->create(['starts_at' => now()->subDays(2)]),
        ];

        foreach ($notBookable as $why => $departure) {
            $this->post('/rezervasyon', $this->reservationPayload(['tour_departure_id' => $departure->id]))
                ->assertSessionHasErrors('tour_departure_id');
        }

        $this->post('/rezervasyon', $this->reservationPayload(['tour_departure_id' => 999999]))
            ->assertSessionHasErrors('tour_departure_id');

        $this->assertDatabaseCount('reservation_requests', 0);
    }

    public function test_departure_of_another_tour_is_rejected(): void
    {
        $other = Tour::where('slug', 'uzungol-turu')->firstOrFail()->upcomingDepartures()->firstOrFail();

        $this->post('/rezervasyon', $this->reservationPayload(['tour_departure_id' => $other->id]))
            ->assertSessionHasErrors('tour_departure_id');

        $this->assertDatabaseCount('reservation_requests', 0);
    }

    public function test_district_must_belong_to_the_selected_province(): void
    {
        $ortahisar = District::where('slug', 'ortahisar')->value('id'); // Trabzon

        $this->post('/rezervasyon', $this->reservationPayload(['district_id' => $ortahisar]))
            ->assertSessionHasErrors('district_id');

        $this->assertDatabaseCount('reservation_requests', 0);
    }

    public function test_page_url_only_accepts_this_sites_addresses(): void
    {
        // page_url panelde tıklanabilir bağlantı olarak gösterilir.
        $this->post('/rezervasyon', $this->reservationPayload(['page_url' => 'javascript:alert(1)']))->assertRedirect(route('reservation.thanks'));
        $this->post('/rezervasyon', $this->reservationPayload(['page_url' => 'https://kotu-site.example/sayfa']))->assertRedirect(route('reservation.thanks'));
        $this->post('/rezervasyon', $this->reservationPayload(['page_url' => url('/ayder-yaylasi-turu')]))->assertRedirect(route('reservation.thanks'));

        $this->assertSame([null, null, url('/ayder-yaylasi-turu')], ReservationRequest::orderBy('id')->pluck('page_url')->all());
    }

    public function test_tour_and_departure_are_preselected_from_the_query_string(): void
    {
        $tour = Tour::where('slug', 'ayder-yaylasi-turu')->firstOrFail();
        $departure = $tour->upcomingDepartures()->firstOrFail();

        $response = $this->get('/rezervasyon?tur=ayder-yaylasi-turu&sefer='.$departure->id)->assertOk();

        $response->assertViewHas('selectedTour', fn ($selected) => $selected?->is($tour));
        $response->assertViewHas('selectedDeparture', fn ($selected) => $selected?->is($departure));
        $response->assertSee('Ayder Yaylası Turu için rezervasyon talebi');
        $response->assertSee('<option value="'.$tour->id.'" selected>', false);
        $response->assertSee('data-selected="'.$departure->id.'"', false);
    }

    public function test_preselection_ignores_unknown_tours_and_foreign_or_closed_departures(): void
    {
        $tour = Tour::where('slug', 'ayder-yaylasi-turu')->firstOrFail();
        $foreign = Tour::where('slug', 'uzungol-turu')->firstOrFail()->upcomingDepartures()->firstOrFail();
        $closed = $tour->departures()->create(['starts_at' => now()->addDays(12), 'status' => DepartureStatus::Closed]);

        $this->get('/rezervasyon?tur=olmayan-tur&sefer='.$foreign->id)->assertOk()
            ->assertViewHas('selectedTour', null)
            ->assertViewHas('selectedDeparture', null);

        // Başka tura ait ya da kayda kapalı sefer ön seçilmez.
        foreach ([$foreign, $closed] as $departure) {
            $this->get('/rezervasyon?tur=ayder-yaylasi-turu&sefer='.$departure->id)->assertOk()
                ->assertViewHas('selectedTour', fn ($selected) => $selected?->is($tour))
                ->assertViewHas('selectedDeparture', null)
                ->assertSee('data-selected=""', false);
        }

        // Yayından kaldırılan tur ön seçilmez.
        $tour->update(['is_active' => false]);
        $this->get('/rezervasyon?tur=ayder-yaylasi-turu')->assertOk()->assertViewHas('selectedTour', null);
    }

    public function test_full_departures_are_not_offered_in_the_form(): void
    {
        $tour = Tour::where('slug', 'ayder-yaylasi-turu')->firstOrFail();
        $full = $tour->departures()->create(['starts_at' => now()->addDays(11), 'quota' => 2]);
        $this->fillSeats($full, 2);
        $open = $tour->upcomingDepartures()->whereKeyNot($full->id)->firstOrFail();

        $html = $this->get('/rezervasyon')->assertOk()->getContent();

        $this->assertSame(1, preg_match('#<script type="application/json" data-departures>(.*?)</script>#s', $html, $match));
        $offered = collect(json_decode($match[1], true)[$tour->id] ?? [])->pluck('id');

        $this->assertTrue($offered->contains($open->id));
        $this->assertFalse($offered->contains($full->id), 'dolu sefer formda sunulmamalı');
    }

    public function test_notification_mail_is_sent_when_an_address_is_configured(): void
    {
        Mail::fake();
        Setting::set('notification_email', 'rezervasyon@ornek.test');
        Setting::flush();

        $this->post('/rezervasyon', $this->reservationPayload())->assertRedirect(route('reservation.thanks'));

        Mail::assertSent(ReservationRequestReceived::class, function (ReservationRequestReceived $mail) {
            return $mail->hasTo('rezervasyon@ornek.test')
                && $mail->reservation->is(ReservationRequest::first())
                && $mail->hasSubject('Yeni rezervasyon talebi: Test Misafir · Ayder Yaylası Turu (3 kişi)');
        });
    }

    public function test_notification_mail_renders_the_request_details(): void
    {
        $this->post('/rezervasyon', $this->reservationPayload())->assertRedirect(route('reservation.thanks'));

        $html = (new ReservationRequestReceived(ReservationRequest::firstOrFail()))->render();

        $this->assertStringContainsString('Test Misafir', $html);
        $this->assertStringContainsString('Ayder Yaylası Turu', $html);
        $this->assertStringContainsString('0532 111 22 33', $html);
    }

    public function test_no_mail_is_sent_without_a_notification_address(): void
    {
        Mail::fake();
        Setting::set('notification_email', '');
        Setting::flush();
        config(['site.notification_email' => null]);

        $this->post('/rezervasyon', $this->reservationPayload())->assertRedirect(route('reservation.thanks'));

        Mail::assertNothingSent();
        $this->assertDatabaseCount('reservation_requests', 1);
    }

    public function test_thanks_page_is_not_indexed(): void
    {
        $this->get('/rezervasyon/tesekkurler')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    // ---------- Yorumlar, site haritası, robots ----------

    public function test_testimonials_section_only_shows_real_reviews(): void
    {
        $this->assertSame(0, Testimonial::count(), 'uydurma yorum tohumlanmamalı');
        $this->get('/')->assertDontSee('Bizimle Yola Çıkanlar Ne Diyor?');

        Testimonial::create(['name' => 'Ayşe K.', 'location' => 'Ardeşen', 'comment' => 'Ayder turu baştan sona çok düzenliydi.', 'rating' => 5]);

        $this->get('/')->assertSee('Bizimle Yola Çıkanlar Ne Diyor?')->assertSee('Ayder turu baştan sona çok düzenliydi.');
    }

    public function test_sitemap_lists_tours_categories_and_regions(): void
    {
        $response = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        foreach ([
            '/', '/turlar', '/tur-takvimi', '/rezervasyon', '/ayder-yaylasi-turu', '/batum-tiflis-turu',
            '/turlar/yayla-turlari', '/turlar/batum-ve-gurcistan-turlari', '/rize', '/rize/ardesen',
            '/trabzon/ortahisar', '/kvkk-aydinlatma-metni', '/tur-sozlesmesi-ve-iptal-kosullari',
        ] as $path) {
            $response->assertSee('<loc>'.url($path).'</loc>', false);
        }

        // Teşekkür sayfası ve eski alan adresleri haritada olmamalı.
        $response->assertDontSee('tesekkurler', false);
        $response->assertDontSee('/hizmetler', false);
        $response->assertDontSee('/teklif-al', false);
        $response->assertDontSee('/markalar', false);
    }

    public function test_robots_txt_points_to_absolute_sitemap_and_hides_admin(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Sitemap: '.url('/sitemap.xml'), false)
            ->assertSee('Disallow: /admin', false)
            ->assertSee('Disallow: /rezervasyon/tesekkurler', false);
    }
}
