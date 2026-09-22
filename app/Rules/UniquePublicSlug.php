<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Tur, il ve sayfa slug'ları kök dizinde (/slug) yayınlandığı için
 * birbirleriyle ve sabit rotalarla çakışmamalıdır.
 */
class UniquePublicSlug implements ValidationRule
{
    public const RESERVED = [
        'admin', 'turlar', 'tur-takvimi', 'bolgeler', 'galeri', 'iletisim', 'rezervasyon', 'hakkimizda', 'sss',
        'sitemap.xml', 'robots.txt', 'storage', 'build', 'images', 'livewire', 'up', 'login', 'logout',
        'blog', 'llms.txt', 'llms-full.txt',
    ];

    public const TABLES = ['tours', 'provinces', 'pages'];

    public function __construct(
        protected string $ownTable,
        protected int|string|null $ignoreId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $slug = (string) $value;

        if (in_array($slug, self::RESERVED, true)) {
            $fail('Bu adres sistem tarafından kullanılıyor, lütfen farklı bir slug girin.');

            return;
        }

        foreach (self::TABLES as $table) {
            $query = DB::table($table)->where('slug', $slug);

            if ($table === $this->ownTable && $this->ignoreId !== null) {
                $query->where('id', '!=', $this->ignoreId);
            }

            if ($query->exists()) {
                $fail('Bu adres başka bir tur, il veya sayfa tarafından kullanılıyor.');

                return;
            }
        }
    }
}
