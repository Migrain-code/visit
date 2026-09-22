<?php

namespace App\Console\Commands;

use App\Services\Google\SearchConsoleService;
use App\Support\AutomationLog;
use Illuminate\Console\Command;

class CheckIndexStatus extends Command
{
    protected $signature = 'seo:check-index {--limit=30}';

    protected $description = 'Sayfaların Google indeks durumunu kontrol eder (URL Inspection API)';

    public function handle(SearchConsoleService $gsc): int
    {
        if (! $gsc->isConfigured()) {
            AutomationLog::summary('gsc.index', ['configured' => false], reasons: ['not_configured']);

            return self::SUCCESS;
        }

        $result = $gsc->syncIndexStatus((int) $this->option('limit'));

        AutomationLog::summary('gsc.index', $result,
            reasons: $result['checked'] === 0 ? ['no_candidates'] : [],
            errors: $result['errors'] > 0 ? ['inspection_failed'] : [],
            changed: $result['checked'] > 0);

        $this->info("{$result['checked']} sayfa kontrol edildi, {$result['indexed']} tanesi indeksli.");

        return self::SUCCESS;
    }
}
