<?php

namespace App\Services\Security;

use App\Support\AutomationLog;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Google reCAPTCHA doğrulaması (v2 kutucuk ve v3 puan).
 *
 * HATA POLİTİKASI — bilinçli seçim:
 *   Google'a ULAŞILAMAZSA form geçer (fail-open). Bu bir rezervasyon talep formudur; ağ
 *   sorunu yüzünden gerçek bir müşteriyi kapıda bırakmanın maliyeti, o aralıkta
 *   bir spam almaktan yüksektir. Ayrıca form zaten bal küpü alanı, dakikalık
 *   istek sınırı ve zorunlu fotoğrafla korunuyor.
 *
 *   Google AÇIKÇA REDDEDERSE form geçmez (fail-closed): geçersiz jeton, düşük
 *   puan veya eylem uyuşmazlığı gerçek bir bot işaretidir.
 */
class Recaptcha
{
    private const VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';

    public const FIELD = 'g-recaptcha-response';

    /** İki anahtar da doluysa devrededir; yarım yapılandırma formu kilitlemez. */
    public function enabled(): bool
    {
        return filled($this->siteKey()) && filled($this->secretKey());
    }

    public function siteKey(): ?string
    {
        return config('services.recaptcha.site_key');
    }

    public function version(): string
    {
        return config('services.recaptcha.version') === 'v2' ? 'v2' : 'v3';
    }

    public function action(): string
    {
        return (string) config('services.recaptcha.action', 'rezervasyon_formu');
    }

    public function minScore(): float
    {
        $score = (float) config('services.recaptcha.min_score', 0.5);

        // Aralık dışındaki bir değer ya her şeyi geçirir ya hiçbir şeyi; güvenli varsayılana düş.
        return ($score > 0.0 && $score <= 1.0) ? $score : 0.5;
    }

    /**
     * Jetonu doğrular.
     *
     * @return bool true: geç. false: reddet.
     */
    public function verify(?string $token, ?string $ip = null): bool
    {
        if (! $this->enabled()) {
            return true;
        }

        // Boş jeton = tarayıcı hiç doğrulama yapmamış. Gerçek ziyaretçide bu olmaz.
        if (blank($token)) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('services.recaptcha.timeout', 5))
                ->post(self::VERIFY_URL, array_filter([
                    'secret' => $this->secretKey(),
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (Throwable $e) {
            AutomationLog::error('recaptcha.verify', 'Google\'a ulaşılamadı: '.$e->getMessage());

            return true; // fail-open
        }

        if (! $response->successful()) {
            AutomationLog::error('recaptcha.verify', 'Beklenmeyen yanıt: HTTP '.$response->status());

            return true; // fail-open
        }

        return $this->passes($response->json() ?? []);
    }

    /** @param array<string, mixed> $data */
    private function passes(array $data): bool
    {
        if (($data['success'] ?? false) !== true) {
            return false;
        }

        if ($this->version() === 'v2') {
            return true;
        }

        // v3: jeton bu formdan mı geldi ve puanı eşiği geçiyor mu?
        if (isset($data['action']) && $data['action'] !== $this->action()) {
            return false;
        }

        return (float) ($data['score'] ?? 0) >= $this->minScore();
    }

    private function secretKey(): ?string
    {
        return config('services.recaptcha.secret_key');
    }
}
