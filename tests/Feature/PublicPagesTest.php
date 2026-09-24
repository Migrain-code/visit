<?php

namespace Tests\Feature;

use App\Enums\DepartureStatus;
use App\Mail\JobApplicationReceived;
use App\Mail\ReservationRequestReceived;
use App\Models\JobApplication;
use App\Models\ReservationRequest;
use App\Models\Setting;
use App\Models\TourDeparture;
use App\Models\TourGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public static function publicUrls(): array
    {
        return collect(['/', '/iletisim', '/is-basvurusu', '/iletisim/tesekkurler', '/is-basvurusu/tesekkurler', '/sitemap.xml', '/robots.txt'])
            ->mapWithKeys(fn ($url) => [$url => [$url]])->all();
    }

    #[DataProvider('publicUrls')]
    public function test_public_page_loads(string $url): void
    {
        $this->get($url)->assertOk();
    }

    public function test_removed_pages_are_gone(): void
    {
        foreach (['/turlar', '/tur-takvimi', '/bolgeler', '/galeri', '/blog', '/hakkimizda', '/sss', '/rize', '/rize/ardesen', '/ayder-yaylasi-turu', '/kvkk-aydinlatma-metni'] as $url) {
            $this->get($url)->assertNotFound();
        }

        // Eski rezervasyon adresi iletişime yönlenir.
        $this->get('/rezervasyon')->assertRedirect('/iletisim');
    }

    public function test_home_is_a_single_page_with_tours_features_and_instagram(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('RTEÜ');
        $response->assertSee('GEZİYOR');
        $response->assertSee('Keşfet · Tanış · Yaşa');
        $response->assertSee('Bu Haftanın');
        $response->assertSee('Rotaları');
        $response->assertSee('Keşfetmeye Başla');
        $response->assertSee('Haftalık Turlar');
        $response->assertSee('Profesyonel Rehber');
        $response->assertSee('Öğrenci Dostu Fiyat');
        $response->assertSee('@RTEUGEZIYOR');
        $response->assertSee(route('contact'), false);
        $response->assertSee(route('jobs.create'), false);
        $response->assertSee('<link rel="canonical" href="'.url('/').'">', false);
        $response->assertSee('<meta name="robots" content="index, follow">', false);
        $response->assertSee('loading="lazy"', false);

        // Eski site bölümleri yok.
        $response->assertDontSee('Tur Takvimi');
        $response->assertDontSee('Kalkış Noktaları');
        $response->assertDontSee('Galeri');
        $response->assertDontSee('Blog');
    }

    public function test_home_lists_bookable_tours_in_panel_order(): void
    {
        TourGroup::query()->delete();
        TourDeparture::query()->delete();

        $second = TourDeparture::create(['title' => 'İkinci Sırada', 'starts_at' => now()->addDays(3), 'price' => 900, 'sort_order' => 2]);
        $first = TourDeparture::create(['title' => 'İlk Sırada', 'starts_at' => now()->addDays(10), 'price' => 1200, 'sort_order' => 1]);

        $hidden = [
            'geçmiş' => TourDeparture::create(['title' => 'Geçmiş Tur', 'starts_at' => now()->subDay()]),
            'kayda kapalı' => TourDeparture::create(['title' => 'Kapalı Tur', 'starts_at' => now()->addDays(12), 'status' => DepartureStatus::Closed]),
            'iptal' => TourDeparture::create(['title' => 'İptal Tur', 'starts_at' => now()->addDays(13), 'status' => DepartureStatus::Cancelled]),
            'sitede gizli' => TourDeparture::create(['title' => 'Gizli Tur', 'starts_at' => now()->addDays(15), 'is_public' => false]),
        ];

        $response = $this->get('/')->assertOk();

        // Panelde sürüklenen sıra tarihten önce gelir.
        $response->assertViewHas('tours', fn ($tours) => $tours->pluck('id')->all() === [$first->id, $second->id]);
        $response->assertSeeInOrder(['İlk Sırada', 'İkinci Sırada']);
        $response->assertSee('1.200 ₺');
        $response->assertSee('900 ₺');

        foreach ($hidden as $why => $tour) {
            $response->assertDontSee($tour->title);
        }
    }

    public function test_tour_card_opens_details_and_links_to_contact_with_the_tour_preselected(): void
    {
        $tour = TourDeparture::query()->bookable()->ordered()->firstOrFail();

        $this->get('/')->assertOk()
            ->assertSee('id="tour-'.$tour->id.'"', false)
            ->assertSee(route('contact', ['tur' => $tour->id]), false)
            ->assertSee('Katılmak İstiyorum');

        $this->get('/iletisim?tur='.$tour->id)->assertOk()
            ->assertSee('<option value="'.$tour->id.'" selected', false);
    }

    public function test_empty_tour_list_shows_a_friendly_message(): void
    {
        TourGroup::query()->delete();
        TourDeparture::query()->delete();

        $this->get('/')->assertOk()->assertSee('Yeni rotalar çok yakında');
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

    public function test_unknown_urls_return_404(): void
    {
        $this->get('/olmayan-sayfa')->assertNotFound();
        $this->get('/olmayan/alt-sayfa')->assertNotFound();
    }

    public function test_sitemap_and_robots_are_minimal(): void
    {
        $this->get('/sitemap.xml')->assertOk()
            ->assertSee(url('/'), false)
            ->assertSee(route('contact'), false)
            ->assertSee(route('jobs.create'), false)
            ->assertDontSee('/turlar', false);

        $this->get('/robots.txt')->assertOk()
            ->assertSee('Disallow: /admin')
            ->assertDontSee('llms.txt');
    }

    // ---------- İletişim formu ----------

    private function contactPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Öğrenci',
            'phone' => '0532 111 22 33',
            'email' => 'ogrenci@ornek.test',
            'tour_departure_id' => TourDeparture::query()->bookable()->value('id'),
            'people_count' => 3,
            'message' => '3 arkadaş katılmak istiyoruz.',
            'kvkk' => '1',
        ], $overrides);
    }

    public function test_contact_page_has_the_form_and_direct_channels(): void
    {
        Setting::set('phone', '+90 532 111 22 33');
        Setting::set('email', 'info@ornek.test');
        Setting::flush();

        $this->get('/iletisim')->assertOk()
            ->assertSee('data-contact-form', false)
            ->assertSee('action="'.route('contact.store').'"', false)
            ->assertSee('name="people_count"', false)
            ->assertSee('name="tour_departure_id"', false)
            ->assertSee('name="kvkk"', false)
            ->assertSee('name="website"', false)
            ->assertSee('tel:+905321112233', false)
            ->assertSee('<!--email_off--><a href="mailto:info@ornek.test">', false)
            ->assertDontSee('type="file"', false)
            ->assertDontSee('province_id', false);
    }

    public function test_contact_form_stores_a_request_and_notifies_by_email(): void
    {
        Mail::fake();
        Setting::set('notification_email', 'bildirim@ornek.test');
        Setting::flush();

        $tour = TourDeparture::query()->bookable()->firstOrFail();

        $this->post('/iletisim', $this->contactPayload(['tour_departure_id' => $tour->id]))
            ->assertRedirect(route('contact.thanks'))
            ->assertSessionHas('reservation_sent', true);

        $request = ReservationRequest::firstOrFail();

        $this->assertSame('Test Öğrenci', $request->name);
        $this->assertSame($tour->id, $request->tour_departure_id);
        $this->assertSame(3, $request->people_count);
        $this->assertSame(ReservationRequest::STATUS_NEW, $request->status);
        $this->assertSame('contact', $request->source);
        $this->assertTrue($request->kvkk_accepted);

        Mail::assertSent(ReservationRequestReceived::class, fn ($mail) => $mail->hasTo('bildirim@ornek.test') && $mail->reservation->is($request));

        $this->get('/iletisim/tesekkurler')->assertOk()->assertSee('Mesajınız bize ulaştı');
    }

    public function test_contact_form_validates_input_and_honeypot(): void
    {
        $this->post('/iletisim', $this->contactPayload(['name' => '', 'phone' => '123']))
            ->assertSessionHasErrors(['name', 'phone']);

        $this->post('/iletisim', $this->contactPayload(['kvkk' => null]))->assertSessionHasErrors('kvkk');
        $this->post('/iletisim', $this->contactPayload(['website' => 'spam']))->assertSessionHasErrors('website');
        $this->post('/iletisim', $this->contactPayload(['people_count' => 0]))->assertSessionHasErrors('people_count');

        // Kayda kapalı tura talep bırakılamaz.
        $closed = TourDeparture::create(['title' => 'Kapalı', 'starts_at' => now()->addDays(5), 'status' => DepartureStatus::Closed]);
        $this->post('/iletisim', $this->contactPayload(['tour_departure_id' => $closed->id]))->assertSessionHasErrors('tour_departure_id');

        $this->assertDatabaseCount('reservation_requests', 0);
    }

    public function test_contact_form_without_a_tour_is_a_general_request(): void
    {
        $this->post('/iletisim', $this->contactPayload(['tour_departure_id' => '']))->assertRedirect(route('contact.thanks'));

        $this->assertSame('Genel bilgi', ReservationRequest::firstOrFail()->tour_label);
    }

    // ---------- İş başvurusu ----------

    private function jobPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Aday Öğrenci',
            'phone' => '0532 111 22 33',
            'email' => 'aday@ornek.test',
            'birth_date' => now()->subYears(20)->toDateString(),
            'position' => 'Rehber',
            'message' => 'Turizm bölümü 2. sınıf.',
            'kvkk' => '1',
        ], $overrides);
    }

    public function test_job_application_form_is_shown(): void
    {
        $this->get('/is-basvurusu')->assertOk()
            ->assertSee('İş Başvurusu')
            ->assertSee('action="'.route('jobs.store').'"', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="cv"', false)
            ->assertSee('name="position"', false)
            ->assertSee('Rehber')
            ->assertSee('Şoför');
    }

    public function test_job_application_is_stored_with_a_private_cv_and_notified(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        Mail::fake();
        Setting::set('notification_email', 'bildirim@ornek.test');
        Setting::flush();

        $this->post('/is-basvurusu', $this->jobPayload(['cv' => UploadedFile::fake()->create('ozgecmis.pdf', 120, 'application/pdf')]))
            ->assertRedirect(route('jobs.thanks'))
            ->assertSessionHas('application_sent', true);

        $application = JobApplication::firstOrFail();

        $this->assertSame('Aday Öğrenci', $application->name);
        $this->assertSame('Rehber', $application->position);
        $this->assertSame(JobApplication::STATUS_NEW, $application->status);
        $this->assertNotNull($application->cv_path);

        // Özgeçmiş HERKESE AÇIK diske yazılmaz.
        Storage::disk('local')->assertExists($application->cv_path);
        $this->assertSame([], Storage::disk('public')->allFiles());

        Mail::assertSent(JobApplicationReceived::class, fn ($mail) => $mail->hasTo('bildirim@ornek.test'));

        $this->get('/is-basvurusu/tesekkurler')->assertOk()->assertSee('Başvurun bize ulaştı');
    }

    public function test_job_application_rejects_bad_files_and_minors(): void
    {
        Storage::fake('local');

        $this->post('/is-basvurusu', $this->jobPayload(['cv' => UploadedFile::fake()->create('virus.exe', 10, 'application/octet-stream')]))
            ->assertSessionHasErrors('cv');
        $this->post('/is-basvurusu', $this->jobPayload(['cv' => UploadedFile::fake()->create('buyuk.pdf', 6000, 'application/pdf')]))
            ->assertSessionHasErrors('cv');
        $this->post('/is-basvurusu', $this->jobPayload(['birth_date' => now()->subYears(12)->toDateString()]))
            ->assertSessionHasErrors('birth_date');
        $this->post('/is-basvurusu', $this->jobPayload(['position' => 'Uydurma']))
            ->assertSessionHasErrors('position');
        $this->post('/is-basvurusu', $this->jobPayload(['kvkk' => null]))->assertSessionHasErrors('kvkk');

        $this->assertDatabaseCount('job_applications', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_job_application_works_without_a_cv(): void
    {
        $this->post('/is-basvurusu', $this->jobPayload())->assertRedirect(route('jobs.thanks'));

        $this->assertNull(JobApplication::firstOrFail()->cv_path);
    }
}
