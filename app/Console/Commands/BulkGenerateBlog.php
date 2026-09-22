<?php

namespace App\Console\Commands;

use App\Models\Blog;
use App\Models\BlogCategory;
use App\Services\Ai\AiClient;
use App\Services\Ai\ArticleGenerator;
use App\Services\Ai\TopicGenerator;
use App\Services\Seo\KeywordPool;
use App\Support\AutomationLog;
use App\Support\SeoConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Throwable;

/**
 * Belirli bir dönem için blog yazısı stoğu üretir (ör. 1 aylık).
 *
 * Her yazı TASLAK olarak kaydedilir ve gelecek bir güne planlanır; zamanı gelince
 * blog:publish-due yayınlar. Böylece içerik bir anda değil, düzenli aralıklarla çıkar.
 *
 * YENİDEN ÇALIŞTIRILABİLİR: zaten yazı planlanmış günler atlanır.
 */
class BulkGenerateBlog extends Command
{
    protected $signature = 'blog:bulk
                            {--days=30 : Kaç günlük içerik}
                            {--per-day=1 : Günde kaç yazı}
                            {--start=1 : Kaç gün sonrasından başlasın}
                            {--dry-run : Yalnız konuları göster, yazı üretme}';

    protected $description = 'Bir dönem boyunca yayınlanacak blog yazısı stoğu üretir';

    public function handle(AiClient $ai, KeywordPool $pool): int
    {
        if (! $ai->isConfigured()) {
            $this->error('AI yapılandırılmamış. .env dosyasına AI_API_KEY girin.');

            return self::FAILURE;
        }

        $days = max(1, (int) $this->option('days'));
        $perDay = max(1, (int) $this->option('per-day'));
        $dryRun = (bool) $this->option('dry-run');

        $categories = BlogCategory::query()->where('is_active', true)->where('auto_generate', true)
            ->orderBy('sort_order')->get();

        if ($categories->isEmpty()) {
            $this->error('Üretime açık blog kategorisi yok.');

            return self::FAILURE;
        }

        $slots = $this->freeSlots($days, $perDay);

        if ($slots === []) {
            $this->info('Belirtilen dönemin tüm günlerinde zaten planlanmış yazı var.');

            return self::SUCCESS;
        }

        $stats = $pool->stats();
        $this->info(sprintf(
            '%d boş gün · %d kategori · havuzda %d sahipsiz kelime',
            count($slots), $categories->count(), $stats['free'],
        ));

        if ($stats['free'] < count($slots)) {
            // Üretim hacmi konu envanterine bağlıdır (spec §7.15).
            $this->warn(sprintf(
                'Havuzda %d kelime var ama %d yazı isteniyor. Kelime bitince üretim durur ve haber verilir.',
                $stats['free'], count($slots),
            ));
        }

        $this->newLine();

        $produced = 0;
        $rejected = 0;
        $exhausted = false;

        foreach ($slots as $i => $slot) {
            $category = $categories[$i % $categories->count()];

            // Her tur için TAZE üretici: önceki turda eklenen yazılar da çakışma
            // karşılaştırmasına girsin.
            $topics = app()->make(TopicGenerator::class);

            try {
                $result = $topics->generate($category, 1);
            } catch (Throwable $e) {
                $this->line('  <fg=red>HATA</> '.$category->name.' — '.$e->getMessage());
                AutomationLog::error('blog.bulk', $e->getMessage(), ['category' => $category->name]);

                continue;
            }

            $rejected += count($result['rejected']);

            if ($result['accepted'] === []) {
                $this->line(sprintf(
                    '  <fg=yellow>ATLANDI</> %-20s %s',
                    $category->name,
                    $result['pool_size'] === 0 ? 'kelime havuzu boş' : 'aday filtrelere takıldı',
                ));

                if ($result['pool_size'] === 0) {
                    $exhausted = true;
                    break;
                }

                continue;
            }

            $topic = $result['accepted'][0];

            if ($dryRun) {
                $this->line(sprintf('  <fg=cyan>%s</> %s  [%s]', $slot->format('d.m'), $topic['title'], $topic['primary_keyword']));
                $produced++;

                continue;
            }

            try {
                $blog = app()->make(ArticleGenerator::class)->generate($category, $topic, $slot);
            } catch (Throwable $e) {
                $this->line('  <fg=red>HATA</> '.$topic['title'].' — '.$e->getMessage());
                AutomationLog::error('blog.bulk', $e->getMessage(), ['title' => $topic['title']]);

                continue;
            }

            $this->line(sprintf(
                '  <fg=green>%s</> %-52s %s',
                $slot->format('d.m H:i'),
                Str::limit($blog->title, 50),
                $category->name,
            ));

            $produced++;
        }

        AutomationLog::summary('blog.bulk', [
            'requested' => count($slots),
            'produced' => $produced,
            'rejected' => $rejected,
            'pool_exhausted' => $exhausted,
        ], changed: $produced > 0);

        $this->newLine();
        $this->info("{$produced} yazı üretildi ve planlandı. Reddedilen aday: {$rejected}.");

        if ($exhausted) {
            $this->warn('Kelime havuzu tükendi. Panel → SEO & AI → Anahtar Kelimeler ekranından yeni kelime ekleyin.');
        }

        if (! $dryRun && $produced > 0) {
            $this->line('Yazılar taslak olarak kaydedildi; zamanı gelince otomatik yayınlanacak.');
        }

        return self::SUCCESS;
    }

    /**
     * Henüz yazı planlanmamış günler için yayın saatleri.
     *
     * @return array<int, Carbon>
     */
    private function freeSlots(int $days, int $perDay): array
    {
        $start = SeoConfig::int('blog_publish_hour_start', (int) config('seo.blog.publish_hour_start', 9), 0, 23);
        $end = SeoConfig::int('blog_publish_hour_end', (int) config('seo.blog.publish_hour_end', 18), $start, 23);

        $taken = Blog::query()
            ->whereNotNull('publish_at')
            ->where('publish_at', '>=', Carbon::today())
            ->get()
            ->groupBy(fn (Blog $b) => $b->publish_at->toDateString())
            ->map->count();

        $slots = [];
        $offset = max(0, (int) $this->option('start'));

        for ($day = 0; $day < $days; $day++) {
            $date = Carbon::today()->addDays($day + $offset);
            $already = $taken->get($date->toDateString(), 0);

            for ($n = $already; $n < $perDay; $n++) {
                $hour = $start + (int) floor(($end - $start) * ($n / max(1, $perDay)));
                $slots[] = $date->copy()->setTime($hour, random_int(0, 59));
            }
        }

        return $slots;
    }
}
