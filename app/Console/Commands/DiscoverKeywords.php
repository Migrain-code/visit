<?php

namespace App\Console\Commands;

use App\Models\SeoKeyword;
use App\Models\SeoSearchQuery;
use App\Support\AutomationLog;
use App\Support\TurkishText;
use Illuminate\Console\Command;

/**
 * Yeni kelime keşfi — DETERMİNİSTİK (spec §1: keşif AI değil, veri işidir).
 *
 * Kaynak: Search Console'da gösterim alıp kelime havuzunda olmayan sorgular.
 * Gerçek arama verisinden gelir, UYDURULMAZ (spec §10.1).
 */
class DiscoverKeywords extends Command
{
    protected $signature = 'seo:discover-keywords {--min-impressions=10} {--limit=50}';

    protected $description = 'Search Console sorgularından yeni anahtar kelime adayları ekler';

    public function handle(): int
    {
        $rows = SeoSearchQuery::query()
            ->queries()
            ->where('impressions', '>=', (int) $this->option('min-impressions'))
            ->orderByDesc('impressions')
            ->limit((int) $this->option('limit') * 4)
            ->get(['query', 'impressions', 'clicks', 'position']);

        if ($rows->isEmpty()) {
            AutomationLog::summary('seo.discover', ['added' => 0], reasons: ['no_candidates']);
            $this->warn('Search Console verisi yok. Önce: php artisan seo:sync-search-console');

            return self::SUCCESS;
        }

        $existing = SeoKeyword::query()->pluck('keyword_hash')->flip();
        $added = 0;

        foreach ($rows as $row) {
            $keyword = trim((string) $row->query);

            if ($keyword === '' || mb_strlen($keyword) < 4) {
                continue;
            }

            $hash = md5(TurkishText::lower($keyword));

            if ($existing->has($hash)) {
                continue;
            }

            SeoKeyword::create([
                'keyword' => $keyword,
                'search_volume' => (int) $row->impressions,
                'keyword_type' => 'BLOG_PRIMARY',
                'search_intent' => 'informational',
                // Gösterimi yüksek olan daha öncelikli (1 = en yüksek).
                'priority' => $row->impressions >= 100 ? 1 : ($row->impressions >= 30 ? 2 : 3),
                'note' => 'Search Console keşfi · '.now()->toDateString(),
            ]);

            $existing->put($hash, true);
            $added++;

            if ($added >= (int) $this->option('limit')) {
                break;
            }
        }

        AutomationLog::summary('seo.discover', [
            'scanned' => $rows->count(),
            'added' => $added,
        ], reasons: $added === 0 ? ['unchanged'] : [], changed: $added > 0);

        $this->info("{$rows->count()} sorgu tarandı, {$added} yeni kelime eklendi.");

        return self::SUCCESS;
    }
}
