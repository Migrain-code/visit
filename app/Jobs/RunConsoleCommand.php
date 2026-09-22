<?php

namespace App\Jobs;

use App\Models\CommandRun;
use App\Services\Admin\CommandRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Panelden başlatılan uzun komutu kuyrukta çalıştırır.
 *
 * Yalnız kayıt kimliğini taşır; komut adı her seferinde katalogdan okunur.
 */
class RunConsoleCommand implements ShouldQueue
{
    use Queueable;

    /** Komutlar kendi içinde idempotent değil (yapay zeka ücreti); otomatik tekrar yok. */
    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public int $runId) {}

    public function handle(CommandRunner $runner): void
    {
        $run = CommandRun::find($this->runId);

        if ($run) {
            $runner->execute($run);
        }
    }

    public function failed(?Throwable $e): void
    {
        CommandRun::query()->whereKey($this->runId)->where('status', '!=', CommandRun::SUCCEEDED)->update([
            'status' => CommandRun::FAILED,
            'finished_at' => now(),
            'output' => 'Kuyruk işi başarısız oldu: '.($e?->getMessage() ?? 'bilinmeyen hata'),
        ]);
    }
}
