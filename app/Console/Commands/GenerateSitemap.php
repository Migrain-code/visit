<?php

namespace App\Console\Commands;

use App\Support\AutomationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Site haritası önbelleğini tazeler';

    public function handle(): int
    {
        Cache::forget('sitemap.xml');

        AutomationLog::summary('sitemap.generate', ['cache' => 'cleared'], changed: true);
        $this->info('Site haritası önbelleği temizlendi; bir sonraki istekte yeniden üretilecek.');

        return self::SUCCESS;
    }
}
