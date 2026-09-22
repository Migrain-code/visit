<?php

namespace App\Services\Seo;

use App\Models\Blog;
use App\Models\District;
use App\Models\NotFoundLog;
use App\Models\Page;
use App\Models\Province;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Support\PathNormalizer;
use Illuminate\Support\Collection;

/**
 * 404 için yönlendirme önerisi — DETERMİNİSTİK, AI kullanmaz (spec §3.8, §10.8).
 *
 * Hedef UYDURULMAZ: yalnız gerçekten yayında olan sayfalar aday olur.
 */
class RedirectSuggester
{
    /** @var Collection<int, string>|null */
    private ?Collection $targets = null;

    /** Yayındaki tüm genel adresler. Tek seferde yüklenir (spec §7.3 performans notu). */
    public function targets(): Collection
    {
        if ($this->targets !== null) {
            return $this->targets;
        }

        $paths = collect(['/', '/turlar', '/tur-takvimi', '/bolgeler', '/galeri', '/hakkimizda', '/sss', '/iletisim', '/rezervasyon']);

        $paths = $paths
            ->merge(Tour::query()->where('is_active', true)->pluck('slug')->map(fn ($s) => '/'.$s))
            ->merge(TourCategory::query()->where('is_active', true)->pluck('slug')->map(fn ($s) => '/turlar/'.$s))
            ->merge(Page::query()->where('is_active', true)->pluck('slug')->map(fn ($s) => '/'.$s))
            ->merge(Province::query()->where('is_active', true)->pluck('slug')->map(fn ($s) => '/'.$s));

        $districts = District::query()
            ->where('is_active', true)
            ->with('province:id,slug,is_active')
            ->get(['id', 'province_id', 'slug']);

        foreach ($districts as $district) {
            if ($district->province?->is_active) {
                $paths->push('/'.$district->province->slug.'/'.$district->slug);
            }
        }

        if (class_exists(Blog::class)) {
            $paths = $paths->merge(
                Blog::query()->where('status', 1)->pluck('slug')->map(fn ($s) => '/blog/'.$s)
            );
        }

        return $this->targets = $paths->map(fn ($p) => PathNormalizer::normalize($p))->unique()->values();
    }

    /**
     * En olası hedefi bulur. Eşiğin altındaysa null döner — zorlamaz.
     *
     * @return array{path: string, score: float}|null
     */
    public function suggest(string $path): ?array
    {
        $normalized = PathNormalizer::normalize($path);
        $minimum = (float) config('seo.redirects.min_similarity', 0.72);

        $best = null;

        foreach ($this->targets() as $target) {
            if ($target === $normalized) {
                continue;
            }

            $score = PathNormalizer::similarity($normalized, $target);

            if ($best === null || $score > $best['score']) {
                $best = ['path' => $target, 'score' => $score];
            }
        }

        return ($best && $best['score'] >= $minimum) ? $best : null;
    }

    /** Çözülmemiş 404'lere öneri yazar. @return array{scanned: int, suggested: int} */
    public function fillSuggestions(int $limit = 200): array
    {
        $logs = NotFoundLog::query()->unresolved()->orderByDesc('hits')->limit($limit)->get();
        $suggested = 0;

        foreach ($logs as $log) {
            $suggestion = $this->suggest($log->path);

            $log->forceFill([
                'suggested_path' => $suggestion['path'] ?? null,
                'suggestion_score' => $suggestion['score'] ?? null,
            ])->saveQuietly();

            if ($suggestion) {
                $suggested++;
            }
        }

        return ['scanned' => $logs->count(), 'suggested' => $suggested];
    }
}
