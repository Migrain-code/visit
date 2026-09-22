<?php

namespace App\Support\Console;

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use RuntimeException;

/**
 * Zamanlanmış bir Artisan komutunu zamanlayıcının KENDİ sürecinde çalıştırır.
 *
 * Schedule::command() her görevi ayrı bir PHP süreci olarak başlatır ve bunun için
 * proc_open ister. Paylaşımlı hostingde proc_open kapalıdır ve hosting firması
 * açmıyor: cron satırı doğru kurulu olsa bile görevlerin HİÇBİRİ çalışmaz. Burada
 * görev Artisan::call ile aynı süreçte koşar; proc_open gerekmez.
 *
 * Komut sıfırdan farklı bir kodla biterse istisna fırlatılır. Zamanlayıcı bunu
 * günlüğe yazar ve sıradaki görevlerle devam eder.
 */
final class InProcess
{
    /** @param  array<string, mixed>  $parameters */
    public static function command(string $command, array $parameters = []): CallbackEvent
    {
        return Schedule::call(function () use ($command, $parameters): void {
            $exitCode = Artisan::call($command, $parameters);

            if ($exitCode !== 0) {
                throw new RuntimeException(sprintf('Zamanlanmış görev "%s" %d koduyla bitti.', $command, $exitCode));
            }
        })->name(self::describe($command, $parameters));
    }

    /**
     * schedule:list'te görünen ad. withoutOverlapping kilidi de bu addan üretilir;
     * bu yüzden ad, withoutOverlapping'den ÖNCE verilmiş olmalıdır.
     */
    private static function describe(string $command, array $parameters): string
    {
        $parts = [$command];

        foreach ($parameters as $key => $value) {
            $value = implode(',', (array) $value);
            $parts[] = is_int($key) ? $value : $key.'='.$value;
        }

        return implode(' ', $parts);
    }
}
