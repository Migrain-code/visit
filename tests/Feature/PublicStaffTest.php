<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicStaffTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function staff(array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => 'Ahmet Yılmaz',
            'email' => 'ahmet@ornek.test',
            'password' => 'parola1234',
            'role' => UserRole::Rehber,
            'title' => 'Tur Rehberi',
            'phone' => '0532 111 22 33',
            'is_active' => true,
            'show_on_site' => true,
        ], $attributes));
    }

    public function test_published_staff_appears_with_click_to_call_and_whatsapp(): void
    {
        $this->staff();

        $html = $this->get('/iletisim')->assertOk()->getContent();

        $this->assertStringContainsString('Ahmet Yılmaz', $html);
        $this->assertStringContainsString('Tur Rehberi', $html);
        // Misafir doğrudan bu kişinin numarasına yönlendirilir.
        $this->assertStringContainsString('tel:+905321112233', $html);
        $this->assertStringContainsString('https://wa.me/905321112233', $html);
        // Hazır WhatsApp mesajı tur acentesine göre yazılmıştır.
        $this->assertStringContainsString('https://wa.me/905321112233?text='.rawurlencode('Merhaba, turlarınız hakkında bilgi almak istiyorum.'), $html);
    }

    public function test_staff_also_appears_on_the_home_page(): void
    {
        $this->staff();

        $this->get('/')->assertOk()->assertSee('Ahmet Yılmaz')->assertSee('Doğrudan İlgili Kişiye Ulaşın');
    }

    public function test_hidden_staff_is_not_published(): void
    {
        $this->staff(['show_on_site' => false]);

        $this->get('/iletisim')->assertOk()->assertDontSee('Ahmet Yılmaz');
        $this->get('/')->assertOk()->assertDontSee('Ahmet Yılmaz');
    }

    public function test_inactive_staff_is_not_published(): void
    {
        $this->staff(['is_active' => false]);

        $this->get('/iletisim')->assertOk()->assertDontSee('Ahmet Yılmaz');
    }

    public function test_staff_without_a_phone_is_not_published(): void
    {
        // Tıklanacak numara yoksa kart göstermek ziyaretçiyi çıkmaza sokar.
        $this->staff(['phone' => null]);

        $this->get('/iletisim')->assertOk()->assertDontSee('Ahmet Yılmaz');
    }

    public function test_whatsapp_falls_back_to_the_phone_number(): void
    {
        $withBoth = $this->staff(['whatsapp' => '0555 999 88 77']);
        $this->assertSame('905559998877', $withBoth->whatsapp_number);

        $phoneOnly = $this->staff(['email' => 'b@ornek.test', 'whatsapp' => null]);
        $this->assertSame('905321112233', $phoneOnly->whatsapp_number);
    }

    public function test_staff_order_is_respected(): void
    {
        $this->staff(['name' => 'İkinci Kişi', 'email' => 'b@ornek.test', 'sort_order' => 2]);
        $this->staff(['name' => 'Birinci Kişi', 'email' => 'a@ornek.test', 'sort_order' => 1]);

        $html = $this->get('/iletisim')->assertOk()->getContent();

        $this->assertLessThan(
            strpos($html, 'İkinci Kişi'),
            strpos($html, 'Birinci Kişi'),
            'sıra alanı listelemeyi belirlemeli',
        );
    }

    public function test_initials_are_used_when_there_is_no_photo(): void
    {
        $person = $this->staff(['name' => 'Ahmet Yılmaz', 'photo' => null]);

        $this->assertSame('AY', $person->initials);
        $this->get('/iletisim')->assertOk()->assertSee('AY');
    }

    public function test_section_is_absent_when_no_staff_is_published(): void
    {
        $this->get('/iletisim')->assertOk()->assertDontSee('Doğrudan Ulaşın');
        $this->get('/')->assertOk()->assertDontSee('Doğrudan İlgili Kişiye Ulaşın');
    }

    public function test_every_role_can_be_published(): void
    {
        // Yayın, role değil "web sitesinde göster" işaretine bağlıdır.
        foreach (UserRole::cases() as $i => $role) {
            $this->staff(['name' => 'Personel '.$role->value, 'email' => $role->value.'@ornek.test', 'role' => $role, 'sort_order' => $i]);
        }

        $response = $this->get('/iletisim')->assertOk();

        foreach (UserRole::cases() as $role) {
            $response->assertSee('Personel '.$role->value);
        }
    }

    public function test_staff_email_and_role_are_never_exposed(): void
    {
        $this->staff(['email' => 'gizli-eposta@ornek.test', 'role' => UserRole::Kayit]);

        foreach (['/iletisim', '/'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            // Panel hesabı bilgileri herkese açık sayfaya sızmamalı.
            $this->assertStringContainsString('Ahmet Yılmaz', $html);
            $this->assertStringNotContainsString('gizli-eposta@ornek.test', $html);
            $this->assertStringNotContainsString(UserRole::Kayit->value, $html);
            $this->assertStringNotContainsString(UserRole::Kayit->label(), $html);
        }
    }
}
