<?php

namespace App\Console\Commands;

use App\Services\Google\SearchConsoleService;
use App\Support\AutomationLog;
use Illuminate\Console\Command;
use Throwable;

class SyncSearchConsole extends Command
{
    protected $signature = 'seo:sync-search-console {--days=28}';

    protected $description = 'Google Search Console performans verisini çeker';

    public function handle(SearchConsoleService $gsc): int
    {
        if (! $gsc->isConfigured()) {
            AutomationLog::summary('gsc.performance', ['configured' => false], reasons: ['not_configured']);
            $this->warn('Google Search Console yapılandırılmamış (servis hesabı JSON ve GSC mülkü gerekli).');

            return self::SUCCESS;
        }

        try {
            $result = $gsc->syncPerformance((int) $this->option('days'));
        } catch (Throwable $e) {
            AutomationLog::error('gsc.performance', $e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        AutomationLog::summary('gsc.performance', $result, changed: array_sum($result) > 0);
        $this->info("{$result['queries']} sorgu, {$result['pages']} sayfa satırı senkronlandı.");

        return self::SUCCESS;
    }
}
