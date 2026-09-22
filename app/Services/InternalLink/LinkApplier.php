<?php

namespace App\Services\InternalLink;

use App\Models\InternalLinkRule;
use App\Support\PathNormalizer;
use App\Support\SeoConfig;
use Illuminate\Support\Collection;

/**
 * İç link uygulayıcı (spec §3.6).
 *
 * KURALLAR:
 *  - Link YALNIZ düz metne basılır. Başlık (h1-h6), mevcut <a>, code/pre/script
 *    içine ASLA girilmez.
 *  - Tavanlar HER İSTEKTE kontrol edilir: makale başına, toplam ve anasayfa tavanı.
 *  - Motor kelime UYDURMAZ — yalnız tanımlı kuralların anchor metinlerini kullanır.
 *
 * Kill switch: varsayılan KAPALI. enabled() false ise HTML'e dokunulmaz.
 */
class LinkApplier
{
    /** Link basılmayacak bölgeler. */
    private const FORBIDDEN_TAGS = ['a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'code', 'pre', 'script', 'style', 'textarea', 'button'];

    /**
     * Anchor'dan sonra gelmesine izin verilen Türkçe ÇEKİM EKLERİ.
     *
     * Neden gerekli: Türkçe eklemeli bir dildir. Metinde "gardırop montajı" değil
     * "gardırop montajını", "gardırop montajında" geçer. Tam eşleşme arayan bir motor
     * doğal metinde neredeyse hiç link basamaz.
     *
     * Neden SERBEST HARF DEĞİL de sabit liste: "montaj" + herhangi bir harf dizisi kabul
     * edilseydi "montajcı" (ayrı bir meslek adı) da eşleşirdi. Liste yalnız hâl, iyelik
     * ve bağlantı eklerini içerir; yapım ekleri (-cı, -lık, -sız) DIŞARIDADIR.
     *
     * Uzun ekler önce denensin diye uzunluğa göre sıralıdır.
     */
    private const SUFFIXES = [
        'ndaki', 'ndeki', 'sında', 'sinde', 'sını', 'sini', 'sına', 'sine',
        'ndan', 'nden', 'daki', 'deki', 'taki', 'teki', 'nın', 'nin', 'nun', 'nün',
        'dan', 'den', 'tan', 'ten', 'yla', 'yle', 'nda', 'nde',
        'da', 'de', 'ta', 'te', 'la', 'le', 'nı', 'ni', 'nu', 'nü',
        'na', 'ne', 'ya', 'ye', 'sı', 'si', 'su', 'sü',
        'ı', 'i', 'u', 'ü', 'a', 'e',
    ];

    public function enabled(): bool
    {
        return SeoConfig::bool('internal_links_enabled', (bool) config('seo.internal_links.enabled', false));
    }

    public function maxPerArticle(): int
    {
        return SeoConfig::int('internal_links_max_per_article', (int) config('seo.internal_links.max_per_article', 4), 0, 50);
    }

    public function maxTotal(): int
    {
        return SeoConfig::int('internal_links_max_total', (int) config('seo.internal_links.max_total', 8), 0, 100);
    }

    public function maxHome(): int
    {
        return SeoConfig::int('internal_links_max_home', (int) config('seo.internal_links.max_home', 1), 0, 20);
    }

    /**
     * @param  string  $scope  blog | tour | category | district | province | page
     * @param  string|null  $selfUrl  Sayfanın kendi adresi — kendine link verilmez.
     */
    public function apply(?string $html, string $scope = 'blog', ?string $selfUrl = null): string
    {
        $html = (string) $html;

        if ($html === '' || ! $this->enabled()) {
            return $html;
        }

        $rules = $this->rules($scope, $selfUrl);

        if ($rules->isEmpty()) {
            return $html;
        }

        /*
         * MEVCUT linkler bütçeye dahil edilir.
         *
         * Bu komut cron'da her gün koşar. Zaten basılmış linkler sayılmazsa her tur
         * tavan kadar YENİ link ekler ve sayı sonsuza kadar birikir. Sayarak komut
         * idempotent hâle gelir: ikinci koşu hiçbir şey değiştirmez.
         */
        $existing = $this->countExistingLinks($html);

        $applied = $existing['total'];
        $homeApplied = $existing['home'];
        $perTarget = $existing['per_target'];
        $maxTotal = $this->maxTotal();
        $maxArticle = $this->maxPerArticle();
        $maxHome = $this->maxHome();

        foreach ($rules as $rule) {
            if ($applied >= $maxArticle || $applied >= $maxTotal) {
                break;
            }

            $isHome = PathNormalizer::normalize($rule->target_url) === '/';

            if ($isHome && $homeApplied >= $maxHome) {
                continue;
            }

            $targetKey = $rule->target_hash;
            $used = $perTarget[$targetKey] ?? 0;

            if ($used >= $rule->max_per_article) {
                continue;
            }

            $remaining = min(
                $rule->max_per_article - $used,
                $maxArticle - $applied,
                $maxTotal - $applied,
            );

            if ($isHome) {
                $remaining = min($remaining, $maxHome - $homeApplied);
            }

            if ($remaining <= 0) {
                continue;
            }

            [$html, $placed] = $this->place($html, $rule, $remaining);

            if ($placed > 0) {
                $applied += $placed;
                $perTarget[$targetKey] = $used + $placed;
                $isHome && $homeApplied += $placed;
            }
        }

        return $html;
    }

    /**
     * Gövdede hâlihazırda bulunan SİTE İÇİ bağlantıları sayar.
     *
     * @return array{total: int, home: int, per_target: array<string, int>}
     */
    private function countExistingLinks(string $html): array
    {
        preg_match_all('/<a\b[^>]*href\s*=\s*["\']([^"\']+)["\']/i', $html, $matches);

        $host = parse_url((string) config('app.url'), PHP_URL_HOST);
        $total = 0;
        $home = 0;
        $perTarget = [];

        foreach ($matches[1] ?? [] as $href) {
            $href = trim($href);

            // Site dışı bağlantılar bütçeyi harcamaz.
            if (preg_match('#^https?://#i', $href)) {
                if (! $host || ! str_contains($href, (string) $host)) {
                    continue;
                }
            } elseif (! str_starts_with($href, '/')) {
                continue;
            }

            $path = PathNormalizer::normalize($href);
            $hash = PathNormalizer::hash($path);

            $total++;
            $perTarget[$hash] = ($perTarget[$hash] ?? 0) + 1;

            if ($path === '/') {
                $home++;
            }
        }

        return ['total' => $total, 'home' => $home, 'per_target' => $perTarget];
    }

    /**
     * Anchor metnini YALNIZ düz metin parçalarında arar ve ilk $limit tanesini linkler.
     *
     * Segmentler HER KURAL İÇİN YENİDEN hesaplanır: önceki kuralın bastığı <a> artık
     * dokunulmaz bölge olur, ama aynı paragrafın geri kalanı düz metin olarak kalır.
     * (Paragrafın tamamını dokunulmaz saymak, bir paragrafa tek link basılmasına yol açardı.)
     *
     * @return array{0: string, 1: int} yeni HTML ve yerleştirilen link sayısı
     */
    private function place(string $html, InternalLinkRule $rule, int $limit): array
    {
        $anchor = trim($rule->anchor_text);

        if ($anchor === '' || $limit <= 0) {
            return [$html, 0];
        }

        $placed = 0;

        $pattern = '/(?<![\p{L}\p{N}])('
            .preg_quote($anchor, '/')
            .'(?:'.implode('|', self::SUFFIXES).')?'
            .')(?![\p{L}\p{N}])/iu';

        $out = '';

        foreach ($this->segments($html) as $segment) {
            if ($placed >= $limit || ! $segment['safe'] || $segment['text'] === '') {
                $out .= $segment['text'];

                continue;
            }

            $out .= preg_replace_callback(
                $pattern,
                function (array $m) use ($rule, &$placed, $limit) {
                    if ($placed >= $limit) {
                        return $m[0];
                    }

                    $placed++;

                    // KÖK-GÖRELİ adres yazılır ("/gardirop-montaji"), url() ile tam adres DEĞİL.
                    // Bu HTML veritabanına kalıcı yazılıyor; tam adres o anki alan adını
                    // (yerelde 127.0.0.1) içeriğe gömer ve site taşınınca linkler kırılır.
                    return '<a href="'.e(PathNormalizer::normalize($rule->target_url)).'" class="internal-link">'.$m[1].'</a>';
                },
                $segment['text'],
            );
        }

        return [$out, $placed];
    }

    /**
     * HTML'i düz metin (safe) ve dokunulmaz (unsafe) parçalara ayırır.
     *
     * @return array<int, array{text: string, safe: bool}>
     */
    private function segments(string $html): array
    {
        $parts = preg_split('/(<[^>]+>)/', $html, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

        $segments = [];
        $depth = 0;

        foreach ($parts as $part) {
            if (str_starts_with($part, '<')) {
                // Etiketin kendisine asla dokunulmaz.
                $segments[] = ['text' => $part, 'safe' => false];

                if (preg_match('#^</\s*([a-z0-9]+)#i', $part, $m)) {
                    if (in_array(strtolower($m[1]), self::FORBIDDEN_TAGS, true)) {
                        $depth = max(0, $depth - 1);
                    }
                } elseif (preg_match('#^<\s*([a-z0-9]+)#i', $part, $m) && ! str_ends_with(rtrim($part), '/>')) {
                    if (in_array(strtolower($m[1]), self::FORBIDDEN_TAGS, true)) {
                        $depth++;
                    }
                }

                continue;
            }

            $segments[] = ['text' => $part, 'safe' => $depth === 0];
        }

        return $segments;
    }

    /** @return Collection<int, InternalLinkRule> */
    private function rules(string $scope, ?string $selfUrl): Collection
    {
        $selfHash = $selfUrl ? PathNormalizer::hash($selfUrl) : null;

        return InternalLinkRule::query()
            ->active()
            ->forScope($scope)
            ->orderByDesc('priority')
            // Uzun anchor önce denenir: "gardırop montajı" > "montaj"
            ->orderByRaw('LENGTH(anchor_text) DESC')
            ->get()
            ->reject(fn (InternalLinkRule $rule) => $selfHash && $rule->target_hash === $selfHash)
            ->values();
    }
}
