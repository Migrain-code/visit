<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

if (! function_exists('setting')) {
    /**
     * Site ayarını getirir. Admin panelinde girilmemişse config/site.php (.env) değerine düşer.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return Setting::get($key, $default);
    }
}

if (! function_exists('site_name')) {
    function site_name(): string
    {
        return (string) setting('site_name', config('app.name'));
    }
}

if (! function_exists('phone_digits')) {
    /**
     * Telefon numarasını tel: bağlantısı için sadeleştirir (+ ve rakamlar).
     */
    function phone_digits(?string $phone): string
    {
        $phone = trim((string) $phone);
        $digits = preg_replace('/[^0-9]/', '', $phone);

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($phone, '+')) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            return '+9'.$digits; // 05xx... -> +905xx...
        }

        if (strlen($digits) === 10) {
            return '+90'.$digits;
        }

        return '+'.$digits;
    }
}

if (! function_exists('site_phone')) {
    function site_phone(): string
    {
        return (string) setting('phone');
    }
}

if (! function_exists('phone_href')) {
    function phone_href(?string $phone = null): string
    {
        return 'tel:'.phone_digits($phone ?? site_phone());
    }
}

if (! function_exists('whatsapp_number')) {
    function whatsapp_number(): string
    {
        $number = (string) setting('whatsapp');

        if ($number === '') {
            $number = site_phone();
        }

        return ltrim(phone_digits($number), '+');
    }
}

if (! function_exists('whatsapp_message')) {
    /**
     * Hazır WhatsApp mesajını oluşturur. {tur} yer tutucusu desteklenir.
     */
    function whatsapp_message(?string $tour = null): string
    {
        $template = (string) setting(
            'whatsapp_message',
            'Merhaba, {tur} hakkında bilgi almak istiyorum.'
        );

        return trim(str_replace('{tur}', $tour ?: 'turlarınız', $template));
    }
}

if (! function_exists('whatsapp_url')) {
    function whatsapp_url(?string $tour = null, ?string $rawMessage = null): string
    {
        $message = $rawMessage ?? whatsapp_message($tour);

        return 'https://wa.me/'.whatsapp_number().'?text='.rawurlencode($message);
    }
}

if (! function_exists('instagram_handle')) {
    /** "@rteugeziyor" — ayarlardaki adresten ya da doğrudan girilen kullanıcı adından. */
    function instagram_handle(): ?string
    {
        $handle = trim((string) setting('instagram_handle'));

        if ($handle === '') {
            $url = trim((string) setting('instagram_url'));
            $handle = $url !== '' ? trim((string) parse_url($url, PHP_URL_PATH), '/') : '';
        }

        $handle = ltrim($handle, '@');

        return $handle !== '' ? '@'.$handle : null;
    }
}

if (! function_exists('instagram_url')) {
    function instagram_url(): ?string
    {
        $url = trim((string) setting('instagram_url'));

        if ($url !== '') {
            return $url;
        }

        $handle = instagram_handle();

        return $handle ? 'https://www.instagram.com/'.ltrim($handle, '@').'/' : null;
    }
}

if (! function_exists('media_url')) {
    /**
     * Admin panelinden yüklenen (storage) veya public/images altındaki görsellerin URL'sini döndürür.
     */
    function media_url(?string $path, ?string $fallback = null): ?string
    {
        if (blank($path)) {
            return $fallback;
        }

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        if (Str::startsWith($path, ['images/', '/images/'])) {
            return asset(ltrim($path, '/'));
        }

        return Storage::disk('public')->url($path);
    }
}

if (! function_exists('versioned_asset')) {
    /**
     * public/ altındaki sabit adlı bir dosyanın adresine değişiklik zamanını ekler.
     *
     * Statik dosyalar bir yıl tarayıcı önbelleğinde tutulur (public/.htaccess). Dosya
     * aynı adla değiştirilirse eski sürüm görünmeye devam ederdi; ?v= değeri dosya
     * değişince değiştiği için tarayıcı yenisini indirir.
     */
    function versioned_asset(string $path): string
    {
        $file = public_path(ltrim($path, '/'));
        $version = is_file($file) ? filemtime($file) : null;

        return asset(ltrim($path, '/')).($version ? '?v='.$version : '');
    }
}

if (! function_exists('money_label')) {
    /**
     * Fiyatı Türkiye biçiminde yazar: "12.500 ₺". Kuruş yalnız varsa gösterilir.
     */
    function money_label(float|int|string|null $amount, ?string $currency = 'TRY'): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        $amount = (float) $amount;
        $decimals = abs($amount - round($amount)) > 0.004 ? 2 : 0;
        $symbol = ['TRY' => '₺', 'EUR' => '€', 'USD' => '$', 'GBP' => '£'][strtoupper((string) $currency)] ?? (string) $currency;

        return number_format($amount, $decimals, ',', '.').' '.$symbol;
    }
}

if (! function_exists('seo_title')) {
    function seo_title(?string $title = null): string
    {
        $site = site_name();

        if (blank($title)) {
            return (string) setting('meta_title', $site.' | '.setting('site_tagline', 'Keşfet · Tanış · Yaşa'));
        }

        return Str::contains($title, $site) ? $title : $title.' | '.$site;
    }
}
