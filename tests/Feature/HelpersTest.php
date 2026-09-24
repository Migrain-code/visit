<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\TourDeparture;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        Setting::set('whatsapp_message', 'Merhaba, {tur} için yer ayırtmak istiyorum.');
        Setting::flush();

        $url = whatsapp_url('Batum');

        $this->assertStringStartsWith('https://wa.me/905321112233?text=', $url);
        $this->assertStringContainsString(rawurlencode('Batum için yer ayırtmak istiyorum.'), $url);
    }

    public function test_whatsapp_message_defaults(): void
    {
        // Tohumlanan şablon: "Merhaba, {tur} hakkında bilgi almak istiyorum."
        $this->assertSame('Merhaba, turlarınız hakkında bilgi almak istiyorum.', whatsapp_message());
        $this->assertSame('Merhaba, Batum hakkında bilgi almak istiyorum.', whatsapp_message('Batum'));

        // Ayar hiç yoksa koddaki varsayılan şablon kullanılır.
        Setting::where('key', 'whatsapp_message')->delete();
        Setting::flush();
        $this->assertSame('Merhaba, Batum hakkında bilgi almak istiyorum.', whatsapp_message('Batum'));
    }

    public function test_whatsapp_url_accepts_a_raw_message(): void
    {
        Setting::set('whatsapp', '905321112233');

        $this->assertSame(
            'https://wa.me/905321112233?text='.rawurlencode('Grup turu için fiyat almak istiyorum.'),
            whatsapp_url(null, 'Grup turu için fiyat almak istiyorum.')
        );
    }

    public function test_whatsapp_number_falls_back_to_the_phone(): void
    {
        Setting::set('whatsapp', '');
        Setting::set('phone', '0532 111 22 33');
        config(['site.whatsapp' => '']);

        $this->assertSame('905321112233', whatsapp_number());
    }

    public function test_instagram_handle_and_url_are_derived_from_each_other(): void
    {
        Setting::set('instagram_handle', '@rteugeziyor');
        Setting::set('instagram_url', '');
        Setting::flush();
        $this->assertSame('@rteugeziyor', instagram_handle());
        $this->assertSame('https://www.instagram.com/rteugeziyor/', instagram_url());

        Setting::set('instagram_handle', '');
        Setting::set('instagram_url', 'https://www.instagram.com/baska.hesap/');
        Setting::flush();
        $this->assertSame('@baska.hesap', instagram_handle());
        $this->assertSame('https://www.instagram.com/baska.hesap/', instagram_url());

        Setting::set('instagram_url', '');
        Setting::flush();
        $this->assertNull(instagram_handle());
        $this->assertNull(instagram_url());
    }

    public function test_money_label_uses_turkish_formatting(): void
    {
        $this->assertSame('8.900 ₺', money_label(8900, 'TRY'));
        $this->assertSame('8.900 ₺', money_label('8900.00', 'TRY'));
        $this->assertSame('24.900 ₺', money_label(24900));
        $this->assertSame('1.250,50 ₺', money_label(1250.5, 'TRY'));   // kuruş yalnız varsa yazılır
        $this->assertSame('185 €', money_label(185, 'EUR'));
        $this->assertSame('99 $', money_label(99, 'USD'));
        $this->assertSame('0 ₺', money_label(0, 'TRY'));
        $this->assertSame('', money_label(null));
        $this->assertSame('', money_label(''));
    }

    public function test_tour_labels(): void
    {
        $tour = TourDeparture::create(['title' => 'Batum', 'starts_at' => '2027-04-11 07:00:00', 'price' => 1250]);

        $this->assertSame('1.250 ₺', $tour->price_label);
        $this->assertSame('Batum · 11.04.2027', $tour->label);
        $this->assertSame('11 Nisan Paz', $tour->short_date_label);
        $this->assertSame('11 Nisan 2027', $tour->date_range_label);
        $this->assertSame('BAT-110427', $tour->code);

        $tour->update(['ends_on' => '2027-04-13']);
        $this->assertSame('11 - 13 Nisan 2027', $tour->fresh()->date_range_label);

        $free = TourDeparture::create(['title' => 'Tanışma Yürüyüşü', 'starts_at' => '2027-04-18 10:00:00']);
        $this->assertNull($free->price_label, 'fiyat girilmediyse etiket yok');
    }

    public function test_seo_title_appends_the_site_name_once(): void
    {
        $this->assertSame('İletişim | RTEÜ Geziyor', seo_title('İletişim'));
        $this->assertSame('Hakkımızda | RTEÜ Geziyor', seo_title('Hakkımızda | RTEÜ Geziyor'));
        $this->assertSame(setting('meta_title'), seo_title(null));
    }

    public function test_settings_fall_back_to_env_config(): void
    {
        Setting::where('key', 'phone')->delete();
        Setting::flush();
        config(['site.phone' => '+90 500 000 00 00']);

        $this->assertSame('+90 500 000 00 00', site_phone());
    }
}
