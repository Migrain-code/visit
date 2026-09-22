<?php

namespace App\Console\Commands;

use App\Services\InternalLink\RuleBuilder;
use App\Support\AutomationLog;
use Illuminate\Console\Command;

/**
 * Yayındaki hizmet, marka ve bölge sayfalarından iç link kuralı üretir.
 *
 * links:apply'dan ÖNCE koşmalıdır: yeni açılan bir sayfanın kuralı yoksa o sayfaya
 * hiçbir yerden link verilmez ve skorun "iç link" bileşeni boş kalır.
 */
class BuildInternalLinkRules extends Command
{
    protected $signature = 'links:build-rules';

    protected $description = 'Yayındaki sayfalardan iç link kurallarını üretir/günceller';

    public function handle(RuleBuilder $builder): int
    {
        $result = $builder->build();
        $changed = $result['created'] + $result['deactivated'] > 0;

        AutomationLog::summary('links.build_rules', $result, reasons: $changed ? [] : ['unchanged'], changed: $changed);

        $this->info(sprintf('%d yeni kural, %d kural pasife alındı.', $result['created'], $result['deactivated']));

        return self::SUCCESS;
    }
}
