<?php

namespace App\Rules;

use App\Services\Security\Recaptcha as Verifier;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Form gönderimini Google reCAPTCHA ile doğrular.
 *
 * Anahtarlar girilmemişse kural sessizce geçer; böylece geliştirme ortamında ve
 * anahtarlar girilene kadar form çalışmaya devam eder.
 */
class Recaptcha implements ValidationRule
{
    public function __construct(private ?Verifier $verifier = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $verifier = $this->verifier ?? app(Verifier::class);

        if (! $verifier->enabled()) {
            return;
        }

        if (! $verifier->verify(is_string($value) ? $value : null, request()->ip())) {
            $fail('Güvenlik doğrulaması tamamlanamadı. Sayfayı yenileyip tekrar deneyin.');
        }
    }
}
