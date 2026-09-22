<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Otomasyon günlüğü (spec §6).
 *
 * Üç seviye disiplini:
 *  1. Her tur BİR SATIR yazılır — değişiklik olmasa bile. "Hiç koşmadı" ile
 *     "koştu ama atladı" ayrımı teşhisin yarısıdır.
 *  2. Rutin sonuçlar debug, anormal olanlar info. Hata varsa satır info'ya yükselir.
 *  3. Seviye AUTOMATION_LOG_LEVEL ile ayarlanır.
 *
 * Log yazamamak otomasyonu DURDURMAZ, ama sessizce de yutulmaz: kanal yoksa
 * varsayılan kanala düşülür (spec §7.12).
 */
class AutomationLog
{
    /** Beklenen, "hiçbir şey olmadı" anlamına gelen sebepler. */
    public const ROUTINE_REASONS = [
        'already_desired',   // zaten istenen durumda
        'not_configured',    // yapılandırılmamış (anahtar/mülk yok)
        'nothing_due',       // zamanı gelen iş yok
        'no_candidates',     // aday yok
        'disabled',          // kapalı
        'unchanged',         // değer değişmedi
    ];

    /**
     * Bir görev turunu tek satırda özetler.
     *
     * @param  string  $task  nokta ile ayrılmış görev adı, ör. "blog.topics"
     * @param  array<string, mixed>  $context  sayılar ve kısa bilgiler
     * @param  array<int, string>  $reasons  neden değişiklik olmadığı
     * @param  array<int, string>  $errors  hata mesajları
     */
    public static function summary(
        string $task,
        array $context = [],
        array $reasons = [],
        array $errors = [],
        bool $changed = false,
    ): void {
        $level = self::level($reasons, $errors, $changed);

        $payload = $context;

        if ($reasons !== []) {
            $payload['reasons'] = array_values(array_unique($reasons));
        }

        if ($errors !== []) {
            $payload['errors'] = array_values($errors);
        }

        self::write($level, $task, $payload);
    }

    public static function error(string $task, string $message, array $context = []): void
    {
        self::write('error', $task, $context + ['error' => $message]);
    }

    public static function info(string $task, array $context = []): void
    {
        self::write('info', $task, $context);
    }

    /** Rutin = değişiklik yok, hata yok ve TÜM sebepler beklenen türden. */
    private static function level(array $reasons, array $errors, bool $changed): string
    {
        if ($errors !== []) {
            return 'warning';
        }

        if ($changed) {
            return 'info';
        }

        foreach ($reasons as $reason) {
            if (! in_array($reason, self::ROUTINE_REASONS, true)) {
                return 'info';
            }
        }

        return 'debug';
    }

    private static function write(string $level, string $task, array $context): void
    {
        try {
            Log::channel('automation')->log($level, $task, $context);
        } catch (Throwable $e) {
            // Yut ama YUTMA: varsayılan kanala düş (spec §7.12).
            try {
                Log::log($level, "[automation] {$task}", $context + ['log_channel_error' => $e->getMessage()]);
            } catch (Throwable) {
                // Log tamamen kullanılamıyorsa isteği bozma.
            }
        }
    }
}
