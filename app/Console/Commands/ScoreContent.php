<?php

namespace App\Console\Commands;

use App\Models\SeoAnalysis;
use App\Services\Seo\ContentRegistry;
use App\Services\Seo\ContentScorer;
use App\Services\Seo\PageRenderer;
use App\Support\AutomationLog;
use App\Support\SeoConfig;
use Illuminate\Console\Command;

class ScoreContent extends Command
{
    protected $signature = 'seo:score
                            {--type= : Yalnız bu içerik tipini skorla}
                            {--all : Yayında olmayanları da skorla}';

    protected $description = 'Yayındaki içeriği deterministik SEO kurallarıyla skorlar (AI kullanmaz)';

    public function handle(ContentRegistry $registry, ContentScorer $scorer, PageRenderer $renderer): int
    {
        $all = $registry->all();
        $items = $this->option('all') ? $all : $registry->reachable();

        // Yayından kaldırılan sayfaların eski skorları TEMİZLENİR; aksi hâlde
        // gösterge panelinde var olmayan sayfalar "hedefin altında" görünmeye devam eder.
        $skipped = 0;

        if (! $this->option('all')) {
            $reachableKeys = $items->map(fn ($c) => $c->contentType.':'.$c->contentId)->flip();

            foreach ($all as $candidate) {
                if (! $reachableKeys->has($candidate->contentType.':'.$candidate->contentId)) {
                    SeoAnalysis::query()
                        ->where('content_type', $candidate->contentType)
                        ->where('content_id', $candidate->contentId)
                        ->delete();
                    $skipped++;
                }
            }
        }

        if ($type = $this->option('type')) {
            $items = $items->where('contentType', $type);
        }

        if ($items->isEmpty()) {
            AutomationLog::summary('seo.score', ['scored' => 0], reasons: ['no_candidates']);
            $this->warn('Skorlanacak içerik bulunamadı.');

            return self::SUCCESS;
        }

        $total = 0;
        $below = 0;
        $target = SeoConfig::int('score_target', 80, 1, 100);

        $renderer->prepare();
        $rendered = 0;

        $bar = $this->output->createProgressBar($items->count());
        $bar->start();

        foreach ($items as $item) {
            // Yapısal kontroller (H1/H2) için gerçek sayfayı render et; yayında
            // olmayan sayfa 404 döner ve null gelir — skorlayıcı gövdeye düşer.
            if ($item->published) {
                $item->renderedHtml = $renderer->mainHtml($item->url);
                $item->renderedHtml !== null && $rendered++;
            }

            $analysis = $scorer->persist($item);
            $total += $analysis->score;
            $analysis->score < $target && $below++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $average = (int) round($total / $items->count());

        AutomationLog::summary('seo.score', [
            'scored' => $items->count(),
            'skipped_unpublished' => $skipped,
            'rendered' => $rendered,
            'average' => $average,
            'below_target' => $below,
            'target' => $target,
        ], changed: true);

        $this->info("{$items->count()} yayındaki içerik skorlandı ({$rendered} tanesi sayfa render edilerek). Ortalama: {$average}/100. Hedefin altında: {$below}");

        if ($skipped > 0) {
            $this->line("  {$skipped} yayında olmayan sayfa atlandı ve eski skorları temizlendi.");
        }

        return self::SUCCESS;
    }
}
