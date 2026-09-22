<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Zamanlayıcı ve kuyruk işçisinin gerçekten çalıştığının kanıtı.
 *
 * Terminal olmayan bir sunucuda cron'un çalışıp çalışmadığını görmenin başka yolu
 * yok. Her biri kendi zincirinin SONUNDA bir zaman damgası yazar:
 *   scheduler → cron → schedule:run → system:heartbeat komutu (alt süreç)
 *   queue     → system:heartbeat kuyruğa iş atar → işçi o işi çalıştırır
 * Damga tazeyse zincirin tamamı çalışıyor demektir.
 *
 * Damga ÖNBELLEĞE değil dosyaya yazılır: paneldeki "Tüm önbellekleri temizle"
 * önbelleği siler ve cron çalışırken bir dakika boyunca yanlış alarm verirdi.
 * Dosya "local" diskte (storage/app/private), web'den erişilemez.
 */
final class Heartbeat
{
    public const SCHEDULER = 'scheduler';

    public const QUEUE = 'queue';

    /** Dakikada bir atılır; bu kadar dakikadır gelmiyorsa "çalışmıyor" sayılır. */
    public const STALE_AFTER_MINUTES = 5;

    public static function beat(string $name): void
    {
        try {
            Storage::disk('local')->put(self::path($name), now()->toIso8601String());
        } catch (Throwable) {
            // Dosya yazılamazsa nabız kaybolur; görevin kendisi bundan etkilenmemeli.
        }
    }

    public static function last(string $name): ?Carbon
    {
        try {
            $disk = Storage::disk('local');
            $value = $disk->exists(self::path($name)) ? trim((string) $disk->get(self::path($name))) : '';

            return $value !== '' ? Carbon::parse($value) : null;
        } catch (Throwable) {
            return null;
        }
    }

    public static function healthy(string $name): bool
    {
        $last = self::last($name);

        return $last !== null && $last->greaterThan(now()->subMinutes(self::STALE_AFTER_MINUTES));
    }

    private static function path(string $name): string
    {
        return 'heartbeats/'.$name.'.txt';
    }
}
