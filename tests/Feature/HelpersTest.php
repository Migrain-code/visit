<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tour;
use App\Rules\UniquePublicSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class HelpersTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_phone_digits_normalises_turkish_numbers(): void
    {
        $this->assertSame('+905321112233', phone_digits('+90 532 111 22 33'));
        $this->assertSame('+905321112233', phone_digits('0532 111 22 33'));
        $this->assertSame('+905321112233', phone_digits('532 111 22 33'));
        $this->assertSame('+905321112233', phone_digits('(0532) 111-22-33'));
        $this->assertSame('', phone_digits(null));
    }

    public function test_whatsapp_url_uses_settings_and_placeholders(): void
    {
        Setting::set('whatsapp', '905321112233');
        Setting::set('whatsapp_message', 'Merhaba, {tur} için yer ayırtmak istiyorum. Bölge: {bolge}');
        Setting::flush();

        $url = whatsapp_url('Ardeşen, Rize', 'Ayder Yaylası Turu');

        $this->assertStringStartsWith('https://wa.me/905321112233?text=', $url);
        $this->assertStringContainsString(rawurlencode('Ayder Yaylası Turu için yer ayırtmak istiyorum. Bölge: Ardeşen, Rize'), $url);
    }

    public function test_whatsapp_message_defaults_and_region_suffix(): void
    {
        // Tohumlanan şablon: "Merhaba, {tur} hakkında bilgi almak istiyorum."
        $this->assertSame('Merhaba, turlarınız hakkında bilgi almak istiyorum.', whatsapp_message());
        $this->assertSame('Merhaba, Ayder Yaylası Turu hakkında bilgi almak istiyorum.', whatsapp_message(null, 'Ayder Yaylası Turu'));

        // Şablonda {bolge} yoksa bölge sona eklenir; böylece bölge sayfasından gelen mesaj kaybolmaz.
        $this->assertSame(
            'Merhaba, Ayder Yaylası Turu hakkında bilgi almak istiyorum. Katılacağım bölge: Ardeşen, Rize',
            whatsapp_message('Ardeşen, Rize', 'Ayder Yaylası Turu')
        );

        // Şablonda {bolge} varsa iki kez yazılmaz; bölge verilmediyse yer tutucu boş kalmaz.
        Setting::set('whatsapp_message', '{bolge} çıkışlı {tur} için bilgi almak istiyorum.');
        $this->assertSame('Ardeşen çıkışlı Ayder Yaylası Turu için bilgi almak istiyorum.', whatsapp_message('Ardeşen', 'Ayder Yaylası Turu'));
        $this->assertSame('... çıkışlı turlarınız için bilgi almak istiyorum.', whatsapp_message());

        // Ayar hiç yoksa koddaki varsayılan şablon kullanılır.
        Setting::where('key', 'whatsapp_message')->delete();
        Setting::flush();
        $this->assertSame('Merhaba, Ayder Yaylası Turu hakkında bilgi almak istiyorum.', whatsapp_message(null, 'Ayder Yaylası Turu'));
    }

    public function test_whatsapp_url_accepts_a_raw_message(): void
    {
        Setting::set('whatsapp', '905321112233');

        $this->assertSame(
            'https://wa.me/905321112233?text='.rawurlencode('Grup turu için fiyat almak istiyorum.'),
            whatsapp_url(null, null, 'Grup turu için fiyat almak istiyorum.')
        );
    }

    public function test_whatsapp_number_falls_back_to_the_phone(): void
    {
        Setting::set('whatsapp', '');
        Setting::set('phone', '0532 111 22 33');
        config(['site.whatsapp' => '']);

        $this->assertSame('905321112233', whatsapp_number());
    }

    public function test_money_label_uses_turkish_formatting(): void
    {
        $this->assertSame('8.900 ₺', money_label(8900, 'TRY'));
        $this->assertSame('8.900 ₺', money_label('8900.00', 'TRY'));
        $this->assertSame('24.900 ₺', money_label(24900));
        $this->assertSame('1.250,50 ₺', money_label(1250.5, 'TRY'));   // kuruş yalnız varsa yazılır
        $this->assertSame('185 €', money_label(185, 'EUR'));
        $this->assertSame('185 €', money_label(185, 'eur'));
        $this->assertSame('99 $', money_label(99, 'USD'));
        $this->assertSame('99 £', money_label(99, 'GBP'));
        $this->assertSame('100 CHF', money_label(100, 'CHF'));          // bilinmeyen para birimi kodu aynen yazılır
        $this->assertSame('0 ₺', money_label(0, 'TRY'));
        $this->assertSame('', money_label(null));
        $this->assertSame('', money_label(''));
    }

    public function test_tour_and_departure_price_labels(): void
    {
        $tour = Tour::where('slug', 'pokut-ve-sal-yaylasi-turu')->firstOrFail();

        $this->assertSame('1.750 ₺', $tour->price_label);
        $this->assertSame('1.900 ₺', $tour->old_price_label);
        $this->assertSame(8, $tour->discount_percent);
        $this->assertSame('Günübirlik', $tour->duration_label);

        $this->assertSame('2 Gece 3 Gün', Tour::where('slug', 'batum-tiflis-turu')->firstOrFail()->duration_label);

        // Yabancı para birimi: örnek veride yok, kendi turumuzu açıyoruz.
        $euro = Tour::create([
            'title' => 'Euro Tur', 'slug' => 'euro-tur', 'price' => 185, 'currency' => 'EUR',
            'duration_days' => 1, 'duration_nights' => 0, 'is_active' => true,
        ]);
        $this->assertSame('185 €', $euro->price_label);
        $this->assertNull($euro->old_price_label, 'indirim yoksa eski fiyat gösterilmez');

        // Sefere fiyat girilmediyse turun fiyatı, girildiyse seferin fiyatı geçerlidir.
        $departure = $euro->departures()->create(['starts_at' => now()->addDays(30)]);
        $this->assertSame('185 €', $departure->price_label);

        $departure->update(['price' => 210]);
        $this->assertSame('210 €', $departure->fresh()->price_label);
    }

    public function test_seo_title_appends_the_site_name_once(): void
    {
        $this->assertSame('Turlar | Visit Tur', seo_title('Turlar'));
        $this->assertSame('Hakkımızda | Visit Tur', seo_title('Hakkımızda | Visit Tur'));
        $this->assertSame(setting('meta_title'), seo_title(null));
    }

    public function test_settings_fall_back_to_env_config(): void
    {
        Setting::where('key', 'phone')->delete();
        Setting::flush();
        config(['site.phone' => '+90 500 000 00 00']);

        $this->assertSame('+90 500 000 00 00', site_phone());
    }

    public function test_public_slugs_cannot_collide(): void
    {
        $check = fn (string $slug, string $table, $ignore = null) => Validator::make(
            ['slug' => $slug],
            ['slug' => [new UniquePublicSlug($table, $ignore)]]
        )->passes();

        $this->assertFalse($check('rize', 'tours'), 'il slug\'ı turda kullanılamaz');
        $this->assertFalse($check('ayder-yaylasi-turu', 'pages'), 'tur slug\'ı sayfada kullanılamaz');
        $this->assertFalse($check('ayder-yaylasi-turu', 'provinces'), 'tur slug\'ı ilde kullanılamaz');
        $this->assertFalse($check('kvkk-aydinlatma-metni', 'tours'), 'sayfa slug\'ı turda kullanılamaz');
        $this->assertFalse($check('ayder-yaylasi-turu', 'tours'), 'aynı slug\'la ikinci tur açılamaz');
        $this->assertTrue($check('likya-yolu-turu', 'tours'));

        $ownId = Tour::where('slug', 'ayder-yaylasi-turu')->value('id');
        $this->assertTrue($check('ayder-yaylasi-turu', 'tours', $ownId), 'kayıt kendi slug\'ını koruyabilir');
        $this->assertFalse($check('ayder-yaylasi-turu', 'pages', $ownId), 'başka tablodaki aynı kimlik muafiyet sağlamaz');
    }

    public function test_fixed_routes_are_reserved_slugs(): void
    {
        $check = fn (string $slug) => Validator::make(['slug' => $slug], ['slug' => [new UniquePublicSlug('tours')]])->passes();

        // Kök dizindeki /{slug} rotası bu adresleri gölgelememeli.
        foreach (['turlar', 'tur-takvimi', 'rezervasyon', 'bolgeler', 'galeri', 'hakkimizda', 'sss', 'iletisim', 'blog', 'admin'] as $slug) {
            $this->assertFalse($check($slug), "{$slug} sabit rotadır, slug olarak kullanılamaz");
            $this->assertContains($slug, UniquePublicSlug::RESERVED);
        }

        $this->assertSame(['tours', 'provinces', 'pages'], UniquePublicSlug::TABLES);
    }

    public function test_every_single_segment_public_route_is_reserved(): void
    {
        // Yeni bir sabit sayfa eklenip listeye yazılması unutulursa, aynı slug'la açılan
        // bir tur o sayfayı erişilmez yapar.
        $segments = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods(), true))
            ->map(fn ($route) => trim($route->uri(), '/'))
            ->filter(fn (string $uri) => $uri !== '' && ! str_contains($uri, '/') && ! str_contains($uri, '{'))
            ->unique()
            ->values();

        $this->assertContains('turlar', $segments);
        $this->assertContains('rezervasyon', $segments);

        foreach ($segments as $segment) {
            $this->assertContains($segment, UniquePublicSlug::RESERVED, "/{$segment} rotası UniquePublicSlug::RESERVED listesinde yok.");
        }
    }
}
