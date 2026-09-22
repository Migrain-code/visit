<?php

namespace App\Console\Commands;

use App\Services\Google\SearchConsoleService;
use App\Support\AutomationLog;
use Illuminate\Console\Command;
use Throwable;

class SyncRankings extends Command
{
    protected $signature = 'seo:sync-rankings';

    protected $description = 'Takip edilen kelimelerin günlük sıralamasını kaydeder';

    public function handle(SearchConsoleService $gsc): int
    {
        if (! $gsc->isConfigured()) {
            AutomationLog::summary('gsc.rankings', ['configured' => false], reasons: ['not_configured']);

            return self::SUCCESS;
        }

        try {
            $result = $gsc->syncRankings();
        } catch (Throwable $e) {
            AutomationLog::error('gsc.rankings', $e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        AutomationLog::summary('gsc.rankings', $result,
            reasons: $result['tracked'] === 0 ? ['no_candidates'] : [],
            changed: $result['tracked'] > 0);

        // Yanlış sayfa sıralanıyorsa bu GÖRÜNÜR olmalı (spec §7.4).
        if ($result['mismatched'] > 0) {
            $this->warn("{$result['mismatched']} kelimede beklenenden farklı sayfa sıralanıyor. Panel → Anahtar Kelimeler ekranına bakın.");
        }

        $this->info("{$result['tracked']} kelime izlendi, {$result['matched']} tanesi doğru sayfayla eşleşti.");

        return self::SUCCESS;
    }
}
