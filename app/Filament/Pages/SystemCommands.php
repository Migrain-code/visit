<?php

namespace App\Filament\Pages;

use App\Models\CommandRun;
use App\Services\Admin\CommandRunner;
use App\Support\Console\CommandCatalog;
use App\Support\DeploymentInfo;
use App\Support\Heartbeat;
use App\Support\Shell;
use App\Support\StorageLink;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
use UnitEnum;

/**
 * Terminal erişimi olmayan sunucu için komut paneli.
 *
 * YALNIZ "site ayarları ve sistem komutları" yetkisi olan görür. Yalnız CommandCatalog'daki komutlar, oradaki sabit
 * parametrelerle çalışır; ekranda serbest komut yazılacak bir alan yoktur.
 */
class SystemCommands extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCommandLine;

    protected static string|UnitEnum|null $navigationGroup = 'Ayarlar';

    protected static ?string $navigationLabel = 'Sistem Komutları';

    protected static ?string $title = 'Sistem Komutları';

    protected static ?int $navigationSort = 90;

    protected string $view = 'filament.pages.system-commands';

    public static function canAccess(): bool
    {
        return auth()->user()?->managesSettings() ?? false;
    }

    /** Zamanlayıcı veya kuyruk çalışmıyorsa menüde uyarı rozeti çıkar. */
    public static function getNavigationBadge(): ?string
    {
        return Heartbeat::healthy(Heartbeat::SCHEDULER) && self::queueHealthy() ? null : '!';
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function getViewData(): array
    {
        $runner = app(CommandRunner::class);
        $tableReady = $runner->tableReady();

        if ($tableReady) {
            $runner->expireStaleRuns();
        }

        $runs = $tableReady ? CommandRun::query()->with('user:id,name')->latest('id')->limit(25)->get() : collect();
        $activeKeys = $tableReady ? CommandRun::query()->active()->pluck('command_key')->all() : [];

        return [
            'tableReady' => $tableReady,
            'groups' => CommandCatalog::grouped(),
            'groupLabels' => CommandCatalog::GROUPS,
            'runs' => $runs,
            'activeKeys' => $activeKeys,
            'hasActive' => $activeKeys !== [],
            'health' => $this->health(),
            'cron' => $this->cronLines(),
            'storageLink' => $this->storageLinkStatus(),
            'deployment' => $this->deployment(),
        ];
    }

    public function runAction(): Action
    {
        return Action::make('run')
            ->label(fn (array $arguments) => $this->definition($arguments)['mode'] === 'background' ? 'Sıraya al' : 'Çalıştır')
            ->icon(fn (array $arguments) => $this->definition($arguments)['mode'] === 'background' ? 'heroicon-o-queue-list' : 'heroicon-o-play')
            ->color(fn (array $arguments) => $this->definition($arguments)['danger'] ? 'danger' : 'primary')
            ->size('sm')
            ->disabled(fn (array $arguments, CommandRunner $runner) => $runner->tableReady()
                && CommandRun::query()->where('command_key', $arguments['key'] ?? '')->active()->exists())
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => $this->definition($arguments)['label'])
            ->modalDescription(function (array $arguments) {
                $definition = $this->definition($arguments);
                $note = $definition['mode'] === 'background'
                    ? ' Arka planda çalışır; sonucu aşağıdaki geçmiş listesinde görürsünüz.'
                    : '';

                return $definition['description'].$note.' Komut: php artisan '.CommandCatalog::commandLine($definition);
            })
            ->modalSubmitActionLabel('Evet, çalıştır')
            ->action(function (array $arguments, CommandRunner $runner) {
                // Sayfa erişimine ek olarak eylemde de yetki denetlenir.
                abort_unless(auth()->user()?->managesSettings(), 403);

                try {
                    $run = $runner->start((string) ($arguments['key'] ?? ''), auth()->user());
                } catch (Throwable $e) {
                    Notification::make()->title('Çalıştırılamadı')->body($e->getMessage())->danger()->send();

                    return;
                }

                $label = $this->definition($arguments)['label'];

                match ($run->status) {
                    CommandRun::QUEUED => Notification::make()
                        ->title($label.' sıraya alındı')
                        ->body(self::queueHealthy()
                            ? 'Kuyruk işçisi bir dakika içinde çalıştıracak.'
                            : 'Kuyruk işçisi şu an çalışmıyor; cron kurulana kadar sırada bekleyecek.')
                        ->info()->send(),
                    CommandRun::SUCCEEDED => Notification::make()
                        ->title($label.' tamamlandı')
                        ->body(Str::limit((string) $run->output, 180) ?: 'Çıktı yok.')
                        ->success()->send(),
                    default => Notification::make()
                        ->title($label.' başarısız oldu')
                        ->body(Str::limit((string) $run->output, 300) ?: 'Ayrıntı için geçmiş listesine bakın.')
                        ->danger()->persistent()->send(),
                };
            });
    }

    /** @return array<string, mixed> */
    private function definition(array $arguments): array
    {
        return CommandCatalog::find($arguments['key'] ?? null)
            ?? ['label' => 'Bilinmeyen komut', 'description' => '', 'mode' => 'sync', 'danger' => true, 'command' => '', 'arguments' => []];
    }

    private static function queueHealthy(): bool
    {
        // "sync" bağlantısında işler istek içinde çalışır; işçiye gerek yoktur.
        return config('queue.default') === 'sync' || Heartbeat::healthy(Heartbeat::QUEUE);
    }

    /** @return array<string, mixed> */
    private function health(): array
    {
        $connection = (string) config('queue.default');

        return [
            'scheduler_last' => Heartbeat::last(Heartbeat::SCHEDULER),
            'scheduler_ok' => Heartbeat::healthy(Heartbeat::SCHEDULER),
            'queue_last' => Heartbeat::last(Heartbeat::QUEUE),
            'queue_ok' => self::queueHealthy(),
            'queue_connection' => $connection,
            'pending_jobs' => $this->countTable($connection === 'database' ? (string) config('queue.connections.database.table', 'jobs') : null),
            'failed_jobs' => $this->countTable((string) config('queue.failed.table', 'failed_jobs')),
            'php_version' => PHP_VERSION,
            'php_ok' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'stale_minutes' => Heartbeat::STALE_AFTER_MINUTES,
        ];
    }

    /** @return array{php: string, schedule: string, queue: string} */
    private function cronLines(): array
    {
        // Web isteğini çalıştıran PHP'nin klasöründeki "php" genellikle aynı sürümün
        // komut satırı sürümüdür (cPanel: /opt/cpanel/ea-php82/root/usr/bin/php).
        // Düz "php" yazmak hostingde çoğu zaman ESKİ bir sürümü çalıştırır.
        $php = PHP_BINDIR.DIRECTORY_SEPARATOR.'php';
        $base = Shell::quote(base_path());

        return [
            'php' => $php,
            'schedule' => "cd {$base} && {$php} artisan schedule:run >> /dev/null 2>&1",
            'queue' => "cd {$base} && {$php} artisan queue:work --stop-when-empty --max-time=55 --tries=3 >> /dev/null 2>&1",
        ];
    }

    /**
     * Sunucudaki kod ve derlenmiş dosyalar.
     *
     * Derlenmiş dosyalar koddan belirgin şekilde yeniyse kod güncellenmemiş demektir:
     * yeni JS/CSS eski sayfa şablonlarıyla çalışır, ikonlar kaybolur ve form bozulabilir.
     *
     * @return array{commit: ?string, code_at: ?Carbon, build_at: ?Carbon, mismatch: bool}
     */
    private function deployment(): array
    {
        $info = app(DeploymentInfo::class);
        $codeAt = $info->codeUpdatedAt();
        $buildAt = $info->buildUploadedAt();

        return [
            'commit' => $info->shortCommit(),
            'code_at' => $codeAt,
            'build_at' => $buildAt,
            'mismatch' => $codeAt && $buildAt && $buildAt->greaterThan($codeAt->copy()->addMinutes(30)),
        ];
    }

    /** @return array{exists: bool, php: bool, cron: string} */
    private function storageLinkStatus(): array
    {
        $link = app(StorageLink::class);

        return [
            'exists' => $link->exists(),
            'php' => $link->canCreateFromPhp(),
            'cron' => $link->cronCommand(),
        ];
    }

    private function countTable(?string $table): ?int
    {
        if ($table === null || $table === '') {
            return null;
        }

        try {
            return DB::table($table)->count();
        } catch (Throwable) {
            return null;
        }
    }
}
