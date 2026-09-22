<?php

namespace App\Console\Commands;

use App\Services\Seo\TargetSynchroniser;
use App\Support\AutomationLog;
use Illuminate\Console\Command;

class SyncSeoTargets extends Command
{
    protected $signature = 'seo:sync-targets';

    protected $description = 'Mevcut içerikten SEO hedef sayfalarını günceller';

    public function handle(TargetSynchroniser $sync): int
    {
        $result = $sync->sync();

        AutomationLog::summary('seo.targets', $result,
            reasons: ($result['created'] + $result['deactivated']) === 0 ? ['unchanged'] : [],
            changed: ($result['created'] + $result['deactivated']) > 0);

        $this->info("Hedefler: {$result['created']} yeni, {$result['updated']} güncel, {$result['deactivated']} pasife alındı.");

        return self::SUCCESS;
    }
}
