<?php

namespace App\Console\Commands;

use App\Models\SeoAnalysis;
use App\Models\SeoSearchQuery;
use App\Services\Ai\AiClient;
use App\Services\Ai\ContentSanitizer;
use App\Services\Seo\ContentRegistry;
use App\Support\AutomationLog;
use App\Support\PathNormalizer;
use App\Support\SeoConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Gösterimi yüksek, tıklaması düşük sayfaların meta başlık/açıklamasını AI ile tazeler.
 *
 * AI'nin meşru kullanım alanlarından biri (spec §1): meta üretimi.
 * Çıktı yine de kod tarafında sınırlanır (spec §10.1).
 */
class RefreshLowCtrMeta extends Command
{
    protected $signature = 'seo:refresh-meta {--limit=} {--dry-run}';

    protected $description = 'Düşük tıklama oranlı sayfaların metalarını AI ile tazeler';

    public function handle(AiClient $ai, ContentRegistry $registry, ContentSanitizer $sanitizer): int
    {
        if (! $ai->isConfigured()) {
            AutomationLog::summary('seo.meta', ['configured' => false], reasons: ['not_configured']);

            return self::SUCCESS;
        }

        if (! SeoConfig::bool('ai_refresh_meta', false)) {
            AutomationLog::summary('seo.meta', ['enabled' => false], reasons: ['disabled']);
            $this->warn('Düşük CTR meta tazeleme kapalı (SEO & AI Ayarları → AI otomasyonu).');

            return self::SUCCESS;
        }

        $limit = (int) ($this->option('limit') ?: SeoConfig::int('ai_refresh_meta_limit', 3, 1, 25));

        $rows = SeoSearchQuery::query()->pages()->lowCtr()->orderByDesc('impressions')->limit($limit * 3)->get();

        if ($rows->isEmpty()) {
            AutomationLog::summary('seo.meta', ['updated' => 0], reasons: ['no_candidates']);
            $this->warn('Düşük CTR verisi yok. Önce: php artisan seo:sync-search-console');

            return self::SUCCESS;
        }

        $updated = 0;
        $errors = [];

        foreach ($rows as $row) {
            if ($updated >= $limit) {
                break;
            }

            $analysis = SeoAnalysis::query()->get()->first(
                fn (SeoAnalysis $a) => $a->url && PathNormalizer::normalize($a->url) === PathNormalizer::normalize((string) $row->path)
            );

            if (! $analysis) {
                continue;
            }

            $model = $registry->resolve($analysis->content_type, $analysis->content_id);

            if (! $model) {
                continue;
            }

            $content = $registry->forModel($model);

            try {
                $payload = $ai->json(
                    'seo.meta',
                    'Sen Türkçe SEO uzmanısın. Yalnızca geçerli JSON döndür. Şema: {"meta_title":"","meta_description":""}',
                    implode("\n", [
                        'Bu sayfanın Google\'daki tıklama oranı düşük. Meta başlık ve açıklamayı yeniden yaz.',
                        '',
                        'Sayfa: '.$content->title,
                        'Adres: '.$content->url,
                        'Mevcut meta başlık: '.($content->metaTitle ?: '(yok)'),
                        'Mevcut meta açıklama: '.($content->metaDescription ?: '(yok)'),
                        'Gösterim: '.$row->impressions.' · Tıklama: '.$row->clicks.' · Ortalama sıra: '.round((float) $row->position, 1),
                        '',
                        'İçerik özeti: '.Str::limit(strip_tags((string) $content->bodyHtml), 900),
                        '',
                        'KURALLAR:',
                        '- meta_title en fazla 60 karakter, meta_description en fazla 155 karakter.',
                        '- Rakam, ödül, garanti gibi doğrulanamaz iddia UYDURMA.',
                        '- Tıklamaya teşvik et ama abartma. Bölge adını (kalkış ili/ilçesi ya da gezilen yer) uygunsa kullan.',
                    ]),
                    ['content_type' => $analysis->content_type, 'content_id' => $analysis->content_id],
                );
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();

                continue;
            }

            $newTitle = $sanitizer->metaTitle($payload['meta_title'] ?? null, $content->title);
            $newDescription = $sanitizer->metaDescription($payload['meta_description'] ?? null, $content->metaDescription);

            $this->line('  '.$content->url);
            $this->line('    eski: '.$content->metaTitle);
            $this->line('    yeni: '.$newTitle);

            if (! $this->option('dry-run') && $newTitle !== '' && $newDescription !== '') {
                $model->forceFill(['meta_title' => $newTitle, 'meta_description' => $newDescription])->save();
                $updated++;
            }
        }

        AutomationLog::summary('seo.meta', ['updated' => $updated, 'scanned' => $rows->count()],
            reasons: $updated === 0 ? ['no_candidates'] : [],
            errors: $errors,
            changed: $updated > 0);

        $this->info("{$updated} sayfanın metası tazelendi.");

        return self::SUCCESS;
    }
}
