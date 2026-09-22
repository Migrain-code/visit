<?php

namespace App\Console\Commands;

use App\Services\Seo\LocationKeywordBuilder;
use App\Services\Seo\TargetSynchroniser;
use App\Support\AutomationLog;
use Illuminate\Console\Command;

class BuildLocationKeywords extends Command
{
    protected $signature = 'seo:location-keywords';

    protected $description = 'Aktif il ve ilçeler için bölge adı içeren anahtar kelimeleri üretir';

    public function handle(LocationKeywordBuilder $builder, TargetSynchroniser $targets): int
    {
        // Kelimeler hedeflere atanacağı için önce hedefler güncel olmalı.
        $targets->sync();

        $result = $builder->build();

        if ($result['regions'] === []) {
            AutomationLog::summary('seo.location_keywords', ['regions' => 0], reasons: ['no_candidates']);
            $this->warn('Erişilebilir bölge yok. Bir ilçenin sayılması için hem kendisi hem de bağlı olduğu il aktif olmalıdır.');

            return self::SUCCESS;
        }

        $changed = $result['created'] + $result['assigned'] + $result['deactivated'] > 0;

        AutomationLog::summary('seo.location_keywords', [
            'regions' => count($result['regions']),
            'created' => $result['created'],
            'assigned' => $result['assigned'],
            'deactivated' => $result['deactivated'],
        ], reasons: $changed ? [] : ['unchanged'], changed: $changed);

        $this->info(sprintf(
            '%d bölge işlendi: %d yeni kelime, %d tanesi bölge sayfasına atandı, %d tanesi pasife alındı.',
            count($result['regions']), $result['created'], $result['assigned'], $result['deactivated'],
        ));
        $this->line('  Bölgeler: '.implode(' · ', $result['regions']));

        return self::SUCCESS;
    }
}
