<?php

namespace Tests\Feature;

use App\Models\ReservationRequest;
use App\Models\TourDeparture;
use App\Services\Security\Recaptcha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Google reCAPTCHA.
 *
 * En önemli iki kural burada sınanıyor:
 *   1. Anahtar girilmemişse form AYNEN çalışır (yarım kurulum formu kilitlemez).
 *   2. Google'a ulaşılamazsa gönderim geçer, Google açıkça reddederse geçmez.
 */
class RecaptchaTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function enable(string $version = 'v3', float $minScore = 0.5): void
    {
        config([
            'services.recaptcha.site_key' => 'test-site-key',
            'services.recaptcha.secret_key' => 'test-secret-key',
            'services.recaptcha.version' => $version,
            'services.recaptcha.min_score' => $minScore,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Misafir',
            'phone' => '0532 111 22 33',
            'tour_departure_id' => TourDeparture::query()->bookable()->value('id'),
            'people_count' => 2,
            'kvkk' => '1',
        ], $overrides);
    }

    public function test_it_is_disabled_until_both_keys_are_present(): void
    {
        config(['services.recaptcha.site_key' => null, 'services.recaptcha.secret_key' => null]);
        $this->assertFalse(app(Recaptcha::class)->enabled());

        // Tek anahtar yeterli DEĞİL: yarım yapılandırma formu kilitlememeli.
        config(['services.recaptcha.site_key' => 'yalnizca-site']);
        $this->assertFalse(app(Recaptcha::class)->enabled());

        config(['services.recaptcha.secret_key' => 'gizli']);
        $this->assertTrue(app(Recaptcha::class)->enabled());
    }

    public function test_form_works_normally_when_keys_are_missing(): void
    {
        config(['services.recaptcha.site_key' => null, 'services.recaptcha.secret_key' => null]);
        Http::fake(); // Google'a hiç gidilmemeli

        $this->post('/iletisim', $this->payload())->assertRedirect(route('contact.thanks'));

        $this->assertDatabaseCount('reservation_requests', 1);
        Http::assertNothingSent();
    }

    public function test_valid_v3_token_passes(): void
    {
        $this->enable();

        Http::fake([
            'www.google.com/*' => Http::response(['success' => true, 'score' => 0.9, 'action' => 'rezervasyon_formu']),
        ]);

        $this->post('/iletisim', $this->payload(['g-recaptcha-response' => 'gecerli-jeton']))
            ->assertRedirect(route('contact.thanks'));

        $this->assertDatabaseCount('reservation_requests', 1);
    }

    public function test_low_v3_score_is_rejected(): void
    {
        $this->enable(minScore: 0.5);

        Http::fake([
            'www.google.com/*' => Http::response(['success' => true, 'score' => 0.1, 'action' => 'rezervasyon_formu']),
        ]);

        $this->post('/iletisim', $this->payload(['g-recaptcha-response' => 'bot-jetonu']))
            ->assertSessionHasErrors(Recaptcha::FIELD);

        $this->assertDatabaseCount('reservation_requests', 0);
    }

    public function test_the_v3_action_is_named_after_the_reservation_form(): void
    {
        $this->enable();

        $this->assertSame('rezervasyon_formu', app(Recaptcha::class)->action());
        $this->get('/iletisim')->assertOk()->assertSee('"rezervasyon_formu"', false);
    }

    public function test_token_from_another_action_is_rejected(): void
    {
        $this->enable();

        // Başka bir sayfadan alınmış jeton bu forma taşınamaz.
        Http::fake([
            'www.google.com/*' => Http::response(['success' => true, 'score' => 0.9, 'action' => 'baska_sayfa']),
        ]);

        $this->post('/iletisim', $this->payload(['g-recaptcha-response' => 'tasinmis-jeton']))
            ->assertSessionHasErrors(Recaptcha::FIELD);
    }

    public function test_missing_token_is_rejected_when_enabled(): void
    {
        $this->enable();
        Http::fake();

        $this->post('/iletisim', $this->payload())->assertSessionHasErrors(Recaptcha::FIELD);

        $this->assertDatabaseCount('reservation_requests', 0);
        // Boş jeton için Google'a gitmeye gerek yok.
        Http::assertNothingSent();
    }

    public function test_v2_checkbox_ignores_score(): void
    {
        $this->enable('v2');

        // v2 yanıtında puan alanı hiç yoktur; bu bir hata değildir.
        Http::fake([
            'www.google.com/*' => Http::response(['success' => true]),
        ]);

        $this->post('/iletisim', $this->payload(['g-recaptcha-response' => 'kutucuk-jetonu']))
            ->assertRedirect(route('contact.thanks'));

        $this->assertDatabaseCount('reservation_requests', 1);
    }

    public function test_google_rejection_blocks_the_submission(): void
    {
        $this->enable('v2');

        Http::fake([
            'www.google.com/*' => Http::response(['success' => false, 'error-codes' => ['invalid-input-response']]),
        ]);

        $this->post('/iletisim', $this->payload(['g-recaptcha-response' => 'sahte']))
            ->assertSessionHasErrors(Recaptcha::FIELD);

        $this->assertDatabaseCount('reservation_requests', 0);
    }

    public function test_unreachable_google_lets_a_real_customer_through(): void
    {
        $this->enable();

        // Ağ hatası gerçek misafiri kapıda bırakmamalı (bilinçli fail-open).
        Http::fake(fn () => throw new ConnectionException('bağlanılamadı'));

        $this->post('/iletisim', $this->payload(['g-recaptcha-response' => 'jeton']))
            ->assertRedirect(route('contact.thanks'));

        $this->assertDatabaseCount('reservation_requests', 1);
    }

    public function test_google_server_error_lets_a_real_customer_through(): void
    {
        $this->enable();

        Http::fake(['www.google.com/*' => Http::response('', 503)]);

        $this->post('/iletisim', $this->payload(['g-recaptcha-response' => 'jeton']))
            ->assertRedirect(route('contact.thanks'));

        $this->assertDatabaseCount('reservation_requests', 1);
    }

    public function test_out_of_range_score_falls_back_to_the_safe_default(): void
    {
        $this->enable(minScore: 0);
        $this->assertSame(0.5, app(Recaptcha::class)->minScore());

        $this->enable(minScore: 5);
        $this->assertSame(0.5, app(Recaptcha::class)->minScore());
    }

    public function test_v3_renders_a_hidden_field_and_the_required_notice(): void
    {
        $this->enable();

        $response = $this->get('/iletisim');

        $response->assertOk();
        $response->assertSee('name="'.Recaptcha::FIELD.'"', false);
        $response->assertSee('window.__recaptcha', false);
        $response->assertSee('"test-site-key"', false);
        // Rozet gizlendiği için Google bu bilgilendirmeyi zorunlu tutuyor.
        $response->assertSee('Gizlilik Politikası', false);
        // Betik açılışta YÜKLENMEZ (PageSpeed): forma dokununca JS ile eklenir.
        $response->assertDontSee('<script src="https://www.google.com/recaptcha', false);
    }

    public function test_v2_renders_the_checkbox_widget(): void
    {
        $this->enable('v2');

        $response = $this->get('/iletisim');

        $response->assertOk();
        $response->assertSee('g-recaptcha', false);
        $response->assertSee('data-sitekey="test-site-key"', false);
        $response->assertDontSee('<script src="https://www.google.com/recaptcha', false);
        // v2'de gizli alan OLMAMALI: Google kendi alanını kendisi ekler, çift olur.
        $response->assertDontSee('input type="hidden" name="'.Recaptcha::FIELD.'"', false);
    }

    public function test_nothing_is_rendered_without_keys(): void
    {
        config(['services.recaptcha.site_key' => null, 'services.recaptcha.secret_key' => null]);

        $this->get('/iletisim')->assertOk()
            ->assertDontSee('recaptcha/api.js', false)
            ->assertDontSee('window.__recaptcha', false)
            // Form yine de işaretlidir; yükleyici yapılandırma yoksa hiçbir şey yapmaz.
            ->assertSee('data-recaptcha', false);
    }

    public function test_job_application_form_is_protected_too(): void
    {
        $this->enable();

        // İş başvurusu formu da korunur; yapılandırma betiği sayfaya BİR kez yazılır.
        $html = $this->get('/is-basvurusu')->assertOk()->getContent();

        $this->assertStringContainsString('name="'.Recaptcha::FIELD.'"', $html);
        $this->assertSame(1, substr_count($html, 'window.__recaptcha = {'));
        $this->assertStringNotContainsString('<script src="https://www.google.com/recaptcha', $html);
    }

    public function test_pages_without_a_form_do_not_carry_recaptcha(): void
    {
        $this->enable();

        foreach (['/', '/iletisim/tesekkurler'] as $url) {
            $this->get($url)->assertOk()
                ->assertDontSee('window.__recaptcha', false)
                ->assertDontSee('google.com/recaptcha', false);
        }
    }

    public function test_honeypot_and_validation_run_even_with_a_valid_token(): void
    {
        $this->enable();

        Http::fake([
            'www.google.com/*' => Http::response(['success' => true, 'score' => 0.9, 'action' => 'rezervasyon_formu']),
        ]);

        // Geçerli jeton diğer kuralları atlatmaz.
        $this->post('/iletisim', $this->payload(['g-recaptcha-response' => 'gecerli-jeton', 'website' => 'https://spam.example']))
            ->assertSessionHasErrors('website');
        $this->post('/iletisim', $this->payload(['g-recaptcha-response' => 'gecerli-jeton', 'kvkk' => null]))
            ->assertSessionHasErrors('kvkk');

        $this->assertDatabaseCount('reservation_requests', 0);
    }

    public function test_the_token_is_never_stored_with_the_request(): void
    {
        $this->enable();

        Http::fake([
            'www.google.com/*' => Http::response(['success' => true, 'score' => 0.9, 'action' => 'rezervasyon_formu']),
        ]);

        $this->post('/iletisim', $this->payload(['g-recaptcha-response' => 'gecerli-jeton']))
            ->assertRedirect(route('contact.thanks'));

        $reservation = ReservationRequest::firstOrFail();

        $this->assertSame('new', $reservation->status);
        $this->assertArrayNotHasKey(Recaptcha::FIELD, $reservation->getAttributes());
        $this->assertStringNotContainsString('gecerli-jeton', json_encode($reservation->getAttributes()));
    }
}
