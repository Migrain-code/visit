<?php

namespace App\Jobs;

use App\Support\Heartbeat;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Kuyruk işçisinin çalıştığını kanıtlar: iş ancak bir işçi onu alırsa çalışır.
 *
 * ShouldBeUnique: işçi çalışmıyorsa her dakika yeni bir iş birikmesin; sırada
 * en fazla bir nabız işi bekler.
 */
class QueueHeartbeat implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 3600;

    public function handle(): void
    {
        Heartbeat::beat(Heartbeat::QUEUE);
    }
}
