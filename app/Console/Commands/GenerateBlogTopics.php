<?php

namespace App\Console\Commands;

use App\Jobs\GenerateBlogArticle;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Services\Ai\AiClient;
use App\Services\Ai\TopicGenerator;
use App\Services\Seo\KeywordPool;
use App\Support\AutomationLog;
use App\Support\SeoConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Throwable;

class GenerateBlogTopics extends Command
{
    protected $signature = 'blog:generate
                            {--count= : Üretilecek yazı sayısı}
                            {--category= : Yalnız bu kategori kimliği}
                            {--dry-run : Yazı üretmeden yalnız konuları göster}';

    protected $description = 'Günlük blog konusu üretir, çakışma filtrelerinden geçirir ve yazıları kuyruğa alır';

    public function handle(AiClient $ai, TopicGenerator $topics, KeywordPool $pool): int
    {
        if (! $ai->isConfigured()) {
            $this->warn('AI yapılandırılmamış. .env dosyasına AI_API_KEY girin (OpenRouter anahtarı).');
            AutomationLog::summary('blog.generate', ['configured' => false], reasons: ['not_configured']);

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! SeoConfig::bool('blog_daily_enabled', (bool) config('seo.blog.daily_enabled', false))) {
            $this->warn('Günlük blog üretimi kapalı (SEO & AI Ayarları → Blog otomasyonu).');
            AutomationLog::summary('blog.generate', ['enabled' => false], reasons: ['disabled']);

            return self::SUCCESS;
        }

        $wanted = (int) ($this->option('count') ?: SeoConfig::int('blog_daily_count', (int) config('seo.blog.daily_count', 1), 1, 20));

        /*
         * STOK KONTROLÜ: ileriye dönük planlanmış taslak varsa üretim yapılmaz.
         *
         * Bu komut her gün koşar. blog:bulk ile bir aylık stok üretildiyse, stok
         * kontrolü olmadan her gün o günlere BİR YAZI DAHA eklenir ve içerik
         * takvimi kendiliğinden şişer. Komut stoku TAMAMLAR, üstüne yığmaz.
         */
        if (! $dryRun && ($skip = $this->stockedAhead($wanted)) !== null) {
            $this->info($skip);
            AutomationLog::summary('blog.generate', ['queued' => 0, 'reason' => 'stocked'], reasons: ['already_desired']);

            return self::SUCCESS;
        }

        $categories = BlogCategory::query()
            ->where('is_active', true)
            ->when(! $dryRun, fn ($q) => $q->where('auto_generate', true))
            ->when($this->option('category'), fn ($q, $id) => $q->whereKey($id))
            ->orderBy('sort_order')
            ->get();

        $stats = $pool->stats();

        if ($categories->isEmpty()) {
            return $this->explainNothing($categories->count(), $stats, 'Üretime açık kategori yok.');
        }

        $queued = 0;
        $allRejections = [];

        foreach ($categories as $category) {
            $perCategory = (int) ceil($wanted / max(1, $categories->count()));

            try {
                $result = $topics->generate($category, $perCategory);
            } catch (Throwable $e) {
                $this->error($category->name.': '.$e->getMessage());
                AutomationLog::error('blog.generate', $e->getMessage(), ['category' => $category->name]);

                continue;
            }

            foreach ($result['rejected'] as $rejection) {
                $allRejections[] = $rejection;
                $this->line(sprintf('  <fg=yellow>RET</> %s — %s', $rejection['title'] ?? '?', $rejection['reason'] ?? ''));
            }

            foreach ($result['accepted'] as $topic) {
                $this->line(sprintf('  <fg=green>KABUL</> %s  [%s]', $topic['title'], $topic['primary_keyword'] ?: 'kelime yok'));

                if ($dryRun) {
                    continue;
                }

                GenerateBlogArticle::dispatch(
                    $category->getKey(),
                    $topic,
                    $this->nextPublishSlot($queued)->toDateTimeString(),
                );

                $queued++;
            }

            if ($queued >= $wanted) {
                break;
            }
        }

        if ($queued === 0 && ! $dryRun) {
            return $this->explainNothing($categories->count(), $stats, 'Hiçbir aday filtreleri geçemedi.', $allRejections);
        }

        AutomationLog::summary('blog.generate', [
            'queued' => $queued,
            'categories' => $categories->count(),
            'pool_free' => $stats['free'],
            'rejected' => count($allRejections),
        ], changed: $queued > 0);

        $this->newLine();
        $this->info($dryRun
            ? 'Kuru çalışma: hiçbir yazı üretilmedi.'
            : "{$queued} yazı kuyruğa alındı. Kuyruk işçisi çalışıyor olmalı: php artisan queue:work");

        return self::SUCCESS;
    }

    /**
     * Sessizce başarısız OLMA: neden konu üretilmediğini ve nereye bakılacağını söyle (spec §3.4).
     */
    private function explainNothing(int $categoryCount, array $stats, string $headline, array $rejections = []): int
    {
        $this->newLine();
        $this->warn($headline);
        $this->line('  Üretime açık kategori sayısı : '.$categoryCount);
        $this->line('  Toplam kelime                : '.$stats['total']);
        $this->line('  Sahiplenilmiş kelime         : '.$stats['assigned']);
        $this->line('  Mevcut içerikte geçen kelime : '.$stats['covered']);
        $this->line('  <fg=cyan>Boştaki kelime (havuz)       : '.$stats['free'].'</>');
        $this->newLine();

        if ($stats['free'] === 0) {
            // Kök sebep genelde budur: üretim hızı ≠ içerik kapasitesi (spec §7.15).
            $this->line('  Havuz boş. Filtre tekrarı engeller ama YENİ konu üretmez.');
            $this->line('  Yapılacak: Panel → SEO & AI → Anahtar Kelimeler ekranından yeni kelime ekleyin');
            $this->line('  veya haftalık kelime keşfini çalıştırın: php artisan seo:discover-keywords');
        } else {
            $this->line('  Havuzda kelime var ama adaylar filtrelere takıldı.');
            $this->line('  Yapılacak: Panel → SEO & AI → AI İşlemleri ekranından ret gerekçelerine bakın,');
            $this->line('  ya da storage/logs/automation.log dosyasında "blog.topics" satırlarını inceleyin.');
        }

        AutomationLog::summary('blog.generate', [
            'queued' => 0,
            'categories' => $categoryCount,
            'pool' => $stats,
            'rejections' => $rejections,
        ], reasons: $stats['free'] === 0 ? ['no_candidates'] : ['filtered_out']);

        return self::SUCCESS;
    }

    /**
     * İleride yayınlanmak üzere bekleyen taslak sayısı yeterliyse sebebi döndürür.
     *
     * Yeterli = bugünden itibaren günlük hedef kadar dolu gün var.
     */
    private function stockedAhead(int $perDay): ?string
    {
        $bufferDays = 3; // en az 3 günlük stok tutulur

        $scheduled = Blog::query()
            ->where('status', Blog::STATUS_DRAFT)
            ->whereNull('merged_into_id')
            ->whereNotNull('publish_at')
            ->where('publish_at', '>=', Carbon::today())
            ->count();

        if ($scheduled >= $perDay * $bufferDays) {
            return sprintf(
                'Stok yeterli: ileriye dönük %d planlanmış taslak var (%d günlük). Yeni üretim yapılmadı.',
                $scheduled, (int) floor($scheduled / max(1, $perDay)),
            );
        }

        return null;
    }

    /** Yayın saatini ayarlardaki aralığa dağıtır. */
    private function nextPublishSlot(int $offset): Carbon
    {
        $start = SeoConfig::int('blog_publish_hour_start', (int) config('seo.blog.publish_hour_start', 9), 0, 23);
        $end = SeoConfig::int('blog_publish_hour_end', (int) config('seo.blog.publish_hour_end', 18), $start, 23);

        $hour = $start + ($offset % max(1, $end - $start + 1));

        // Zaten yazı planlanmış günleri atla; aynı güne ikinci yazı koyma.
        $taken = Blog::query()
            ->whereNotNull('publish_at')
            ->where('publish_at', '>=', Carbon::today())
            ->pluck('publish_at')
            ->map(fn ($d) => $d->toDateString())
            ->flip();

        for ($day = 0; $day <= 60; $day++) {
            $slot = Carbon::today()->addDays($day)->setHour($hour)->setMinute(random_int(0, 59));

            if ($slot->isPast() || $taken->has($slot->toDateString())) {
                continue;
            }

            return $slot;
        }

        return Carbon::tomorrow()->setHour($hour);
    }
}
