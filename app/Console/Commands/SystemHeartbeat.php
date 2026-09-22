<?php

namespace App\Console\Commands;

use App\Jobs\QueueHeartbeat;
use App\Support\Heartbeat;
use Illuminate\Console\Command;

/**
 * Zamanlayıcı her dakika çalıştırır.
 *
 * Diğer bütün görevlerle AYNI yoldan (InProcess::command) zamanlanır: damga
 * yazılıyorsa gerçek görevler de çalışabiliyor demektir.
 */
class SystemHeartbeat extends Command
{
    protected $signature = 'system:heartbeat';

    protected $description = 'Zamanlayıcı ve kuyruk işçisinin çalıştığını panele bildirir';

    public function handle(): int
    {
        Heartbeat::beat(Heartbeat::SCHEDULER);
        QueueHeartbeat::dispatch();

        return self::SUCCESS;
    }
}
