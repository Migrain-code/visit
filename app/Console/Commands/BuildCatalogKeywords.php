<?php

namespace App\Console\Commands;

use App\Services\Seo\CatalogKeywordBuilder;
use App\Services\Seo\TargetSynchroniser;
use App\Support\AutomationLog;
use Illuminate\Console\Command;

class BuildCatalogKeywords extends Command
{
    protected $signature = 'seo:catalog-keywords';

    protected $description = 'Yayındaki tur ve kategoriler için anahtar kelimeleri üretir ve sayfalarına atar';

    public function handle(CatalogKeywordBuilder $builder, TargetSynchroniser $targets): int
    {
        // Kelimeler tur/kategori sayfalarına atanacağı için önce hedefler güncel olmalı.
        $targets->sync();

        $result = $builder->build();

        if ($result['items'] === []) {
            AutomationLog::summary('seo.catalog_keywords', ['items' => 0], reasons: ['no_candidates']);
            $this->warn('Yayında tur ya da kategori yok.');

            return self::SUCCESS;
        }

        $changed = $result['created'] + $result['assigned'] + $result['deactivated'] > 0;

        AutomationLog::summary('seo.catalog_keywords', [
            'items' => count($result['items']),
            'created' => $result['created'],
            'assigned' => $result['assigned'],
            'deactivated' => $result['deactivated'],
        ], reasons: $changed ? [] : ['unchanged'], changed: $changed);

        $this->info(sprintf(
            '%d tur/kategori işlendi: %d yeni kelime, %d tanesi sayfasına atandı, %d tanesi pasife alındı.',
            count($result['items']), $result['created'], $result['assigned'], $result['deactivated'],
        ));

        return self::SUCCESS;
    }
}
