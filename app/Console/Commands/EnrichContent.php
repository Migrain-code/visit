<?php

namespace App\Console\Commands;

use App\Models\District;
use App\Models\Province;
use App\Models\SeoAnalysis;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Services\Ai\AiClient;
use App\Services\Ai\RegionContentEnricher;
use App\Services\Seo\ContentRegistry;
use App\Services\Seo\ContentScorer;
use App\Services\Seo\PageRenderer;
use App\Support\AutomationLog;
use App\Support\SeoConfig;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Hedef skorun altındaki YAYINDAKİ sayfaların içeriğini AI ile genişletir.
 *
 * Kapalı sayfalara dokunmaz: 404 dönen bir sayfayı zenginleştirmek boşa AI çağrısıdır.
 */
class EnrichContent extends Command
{
    protected $signature = 'seo:enrich
                            {--type=* : district|province|tour|category (boş bırakılırsa hepsi)}
                            {--limit=50 : En fazla kaç sayfa}
                            {--force : Hedef skorun üstündekileri de işle}';

    protected $description = 'Yayındaki sayfaların içeriğini AI ile genişletir ve yeniden skorlar';

    public function handle(
        AiClient $ai,
        RegionContentEnricher $enricher,
        ContentRegistry $registry,
        ContentScorer $scorer,
        PageRenderer $renderer,
    ): int {
        if (! $ai->isConfigured()) {
            AutomationLog::summary('seo.enrich', ['configured' => false], reasons: ['not_configured']);
            $this->warn('AI yapılandırılmamış. .env dosyasına AI_API_KEY girin.');

            return self::SUCCESS;
        }

        $target = SeoConfig::int('score_target', 80, 1, 100);
        $candidates = $this->candidates($registry, $target, (array) $this->option('type'), (bool) $this->option('force'))
            ->take((int) $this->option('limit'));

        if ($candidates->isEmpty()) {
            AutomationLog::summary('seo.enrich', ['processed' => 0], reasons: ['no_candidates']);
            $this->info('Zenginleştirilecek sayfa yok — yayındaki tüm içerik hedef skorun üstünde.');

            return self::SUCCESS;
        }

        $this->info($candidates->count().' sayfa işlenecek. Her sayfa bir AI çağrısı demektir.');
        $this->newLine();

        $done = 0;
        $errors = [];
        $renderer->prepare();

        foreach ($candidates as $model) {
            $label = $this->label($model);

            try {
                $result = $enricher->enrich($model);
            } catch (Throwable $e) {
                $errors[] = $label.': '.$e->getMessage();
                $this->line("  <fg=red>HATA</> {$label} — ".$e->getMessage());

                continue;
            }

            // Hemen yeniden skorla ki sonucu görelim.
            $content = $registry->forModel($model->refresh());
            $content->renderedHtml = $renderer->mainHtml($content->url);
            $analysis = $scorer->persist($content);

            $this->line(sprintf(
                '  <fg=green>TAMAM</> %-28s %d → %d kelime · %d H2 · skor %d/100',
                $label, $result['words_before'], $result['words_after'], $result['h2'], $analysis->score,
            ));

            $done++;
        }

        AutomationLog::summary('seo.enrich', [
            'processed' => $done,
            'failed' => count($errors),
        ], errors: $errors, changed: $done > 0);

        $this->newLine();
        $this->info("{$done} sayfa zenginleştirildi.".(count($errors) ? ' '.count($errors).' hata.' : ''));

        return self::SUCCESS;
    }

    /** @return Collection<int, Model> */
    private function candidates(ContentRegistry $registry, int $target, array $types, bool $force): Collection
    {
        $types = $types ?: ['district', 'province', 'tour', 'category'];

        return $registry->reachable()
            ->filter(fn ($c) => in_array($c->contentType, $types, true))
            ->filter(function ($c) use ($target, $force) {
                if ($force) {
                    return true;
                }

                $score = SeoAnalysis::query()
                    ->where('content_type', $c->contentType)
                    ->where('content_id', $c->contentId)
                    ->value('score');

                return $score === null || $score < $target;
            })
            ->map(fn ($c) => match ($c->contentType) {
                'district' => District::with('province')->find($c->contentId),
                'province' => Province::find($c->contentId),
                'tour' => Tour::find($c->contentId),
                'category' => TourCategory::find($c->contentId),
                default => null,
            })
            ->filter()
            ->values();
    }

    private function label(Model $model): string
    {
        return match (true) {
            $model instanceof District => $model->name.' / '.$model->province?->name,
            $model instanceof Province => $model->name.' (il)',
            $model instanceof Tour => $model->title,
            $model instanceof TourCategory => $model->name.' (kategori)',
            default => class_basename($model),
        };
    }
}
