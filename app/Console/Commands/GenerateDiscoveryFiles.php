<?php

namespace App\Console\Commands;

use App\Services\Discovery\LlmsTxtGenerator;
use App\Support\AutomationLog;
use Illuminate\Console\Command;
use Throwable;

class GenerateDiscoveryFiles extends Command
{
    protected $signature = 'seo:discovery';

    protected $description = 'AI ajanları için llms.txt ve llms-full.txt dosyalarını üretir';

    public function handle(LlmsTxtGenerator $generator): int
    {
        try {
            $result = $generator->generate();
        } catch (Throwable $e) {
            AutomationLog::error('discovery.llms', $e->getMessage());
            $this->error('Üretilemedi: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'llms.txt (%s) ve llms-full.txt (%s) üretildi.',
            number_format($result['index_bytes']).' bayt',
            number_format($result['full_bytes']).' bayt',
        ));

        return self::SUCCESS;
    }
}
