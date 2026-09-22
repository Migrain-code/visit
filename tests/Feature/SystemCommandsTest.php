<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\SystemCommands;
use App\Jobs\QueueHeartbeat;
use App\Jobs\RunConsoleCommand;
use App\Models\CommandRun;
use App\Models\User;
use App\Services\Admin\CommandRunner;
use App\Support\Console\CommandCatalog;
use App\Support\Console\InProcess;
use App\Support\Heartbeat;
use App\Support\StorageLink;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Container\Container;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Terminalsiz sunucu için komut paneli.
 *
 * DİKKAT: bu testler katalogdaki komutları TOPLU ÇALIŞTIRMAZ. "optimize" ve
 * "config:cache" gerçek bootstrap/cache klasörüne test ortamının ayarlarını
 * (bellek içi SQLite) yazar ve geliştirme sunucusunu bozar. Yalnız yan etkisiz
 * komutlar (site haritası önbelleğini silmek gibi) gerçekten çalıştırılır.
 */
class SystemCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Nabız dosyaları gerçek storage klasörüne yazılmasın.
        Storage::fake('local');
    }

    private function user(UserRole $role): User
    {
        return User::create([
            'name' => $role->label(),
            'email' => $role->value.'@komut.test',
            'password' => 'parola1234',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function admin(): User
    {
        return $this->user(UserRole::SuperAdmin);
    }

    // ---------- İzin listesi ----------

    public function test_catalog_never_contains_destructive_or_interactive_commands(): void
    {
        foreach (CommandCatalog::all() as $key => $definition) {
            $this->assertNotContains($definition['command'], CommandCatalog::FORBIDDEN, $key.' yasaklı bir komut.');
            // Veri silen tüm migrate türevleri ve db:* komutları dışarıda kalmalı.
            $this->assertDoesNotMatchRegularExpression(
                '/^(migrate:(fresh|refresh|reset|rollback)|db:|down$|tinker$)/',
                $definition['command'],
                $key.' veri silebilecek ya da paneli kilitleyebilecek bir komut.'
            );
        }
    }

    public function test_every_catalog_command_actually_exists(): void
    {
        $registered = array_keys(Artisan::all());

        foreach (CommandCatalog::all() as $key => $definition) {
            $this->assertContains($definition['command'], $registered, $key.' için komut bulunamadı.');
        }
    }

    public function test_migrate_always_carries_force_so_it_cannot_hang_on_a_prompt(): void
    {
        // Canlıda migrate onay sorar; web isteğinde soruyu yanıtlayacak kimse yok.
        $this->assertTrue(CommandCatalog::find('migrate')['arguments']['--force'] ?? false);
    }

    public function test_unknown_or_forbidden_keys_are_rejected(): void
    {
        $runner = app(CommandRunner::class);
        $admin = $this->admin();

        foreach (['migrate:fresh', 'db:wipe', 'tinker', 'olmayan-komut', ''] as $key) {
            try {
                $runner->start($key, $admin);
                $this->fail($key.' reddedilmeliydi.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }

        $this->assertDatabaseCount('command_runs', 0);
    }

    // ---------- Erişim ----------

    public function test_super_admin_can_open_the_page(): void
    {
        $this->actingAs($this->admin())->get(SystemCommands::getUrl())->assertOk()->assertSee('Sistem Komutları');
    }

    public static function otherRoles(): array
    {
        return [
            'operasyon sorumlusu' => [UserRole::Operasyon],
            'kayıt personeli' => [UserRole::Kayit],
            'içerik editörü' => [UserRole::Icerik],
            'rehber' => [UserRole::Rehber],
        ];
    }

    // Her rol ayrı test: tek istekte kullanıcı değiştirmek oturumu karıştırıp yanıltıcı 302 döndürüyor.
    #[DataProvider('otherRoles')]
    public function test_other_roles_cannot_open_the_page(UserRole $role): void
    {
        $this->actingAs($this->user($role))->get(SystemCommands::getUrl())->assertForbidden();
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(SystemCommands::getUrl())->assertRedirect('/admin/login');
    }

    // ---------- Çalıştırma ----------

    public function test_sync_command_runs_and_is_recorded(): void
    {
        $admin = $this->admin();

        $run = app(CommandRunner::class)->start('sitemap', $admin);

        $this->assertSame(CommandRun::SUCCEEDED, $run->status);
        $this->assertSame(0, $run->exit_code);
        $this->assertSame('sitemap:generate', $run->command_line);
        $this->assertSame($admin->id, $run->user_id);
        $this->assertStringContainsString('Site haritası', (string) $run->output);
        $this->assertNotNull($run->duration_ms);
    }

    public function test_background_command_goes_to_the_queue_and_runs_there(): void
    {
        Queue::fake();

        $run = app(CommandRunner::class)->start('score', $this->admin());

        $this->assertSame(CommandRun::QUEUED, $run->status);
        Queue::assertPushed(RunConsoleCommand::class, fn ($job) => $job->runId === $run->id);

        // İşçi işi aldığında komut çalışır. Artisan taklit edilir: skorlama sayfaları render eder, testte gereksiz.
        Artisan::shouldReceive('call')->once()->with('seo:score', [], \Mockery::any())->andReturn(0);
        (new RunConsoleCommand($run->id))->handle(app(CommandRunner::class));

        $this->assertSame(CommandRun::SUCCEEDED, $run->refresh()->status);
    }

    public function test_failing_command_is_recorded_as_failed_with_the_error(): void
    {
        Artisan::shouldReceive('call')->once()->andThrow(new RuntimeException('bağlantı koptu'));

        $run = app(CommandRunner::class)->start('sitemap', $this->admin());

        $this->assertSame(CommandRun::FAILED, $run->status);
        $this->assertSame(1, $run->exit_code);
        $this->assertStringContainsString('bağlantı koptu', (string) $run->output);
    }

    public function test_non_zero_exit_code_counts_as_failure(): void
    {
        Artisan::shouldReceive('call')->once()->andReturn(2);

        $run = app(CommandRunner::class)->start('sitemap', $this->admin());

        $this->assertSame(CommandRun::FAILED, $run->status);
        $this->assertSame(2, $run->exit_code);
    }

    public function test_same_command_cannot_run_twice_at_once(): void
    {
        Queue::fake();
        $runner = app(CommandRunner::class);

        $admin = $this->admin();
        $runner->start('score', $admin);

        $this->expectException(RuntimeException::class);
        $runner->start('score', $admin);
    }

    public function test_a_run_stuck_after_a_server_timeout_is_released(): void
    {
        // Sunucu isteği zaman sınırında öldürürse kayıt "çalışıyor"da asılı kalır.
        $stuck = CommandRun::create([
            'command_key' => 'sitemap', 'command_line' => 'sitemap:generate', 'mode' => 'sync',
            'status' => CommandRun::RUNNING, 'started_at' => now()->subHour(),
        ]);

        $run = app(CommandRunner::class)->start('sitemap', $this->admin());

        $this->assertSame(CommandRun::FAILED, $stuck->refresh()->status);
        $this->assertStringContainsString('Zaman aşımı', (string) $stuck->output);
        $this->assertSame(CommandRun::SUCCEEDED, $run->status, 'Takılan kayıt yeni çalıştırmayı engellememeli.');
    }

    public function test_ansi_colours_are_stripped_and_huge_output_is_capped(): void
    {
        Artisan::shouldReceive('call')->once()->andReturnUsing(function ($command, $arguments, $output) {
            $output->write("\e[32mYeşil\e[0m ".str_repeat('x', 250_000));

            return 0;
        });

        $run = app(CommandRunner::class)->start('sitemap', $this->admin());

        $this->assertStringNotContainsString("\e[", (string) $run->output);
        $this->assertStringStartsWith('Yeşil', (string) $run->output);
        $this->assertLessThan(201_000, mb_strlen((string) $run->output));
        $this->assertStringContainsString('çıktı kısaltıldı', (string) $run->output);
    }

    // ---------- Panel eylemi ----------

    public function test_super_admin_runs_a_command_from_the_page(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(SystemCommands::class)
            ->callAction('run', arguments: ['key' => 'sitemap'])
            ->assertNotified();

        $this->assertDatabaseHas('command_runs', ['command_key' => 'sitemap', 'status' => CommandRun::SUCCEEDED]);
    }

    public function test_page_rejects_a_key_outside_the_catalog(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(SystemCommands::class)
            ->callAction('run', arguments: ['key' => 'db:wipe'])
            ->assertNotified('Çalıştırılamadı');

        $this->assertDatabaseCount('command_runs', 0);
    }

    /**
     * config:cache ve route:cache ("Önbellekleri oluştur" ikisini de çağırır) önbelleği
     * üretmek için bootstrap/app.php'den TAZE bir uygulama başlatır. Canlıda bu, panel
     * isteğinin ortasında container'ı ve Livewire'ın bileşen kancalarını değiştiriyordu:
     * bildirim kayboluyor, sayfadaki bir sonraki tıklama "Undefined array key children"
     * ile düşüyordu. Gerçek komut bootstrap/cache'e yazacağı için aynı başlatma taklit edilir.
     */
    public function test_building_caches_does_not_break_the_panel_page(): void
    {
        $this->actingAs($this->admin());

        Artisan::shouldReceive('call')->once()->with('optimize', [], \Mockery::any())->andReturnUsing(function () {
            $fresh = require base_path('bootstrap/app.php');
            $fresh->make(ConsoleKernel::class)->bootstrap();

            return 0;
        });

        $page = Livewire::test(SystemCommands::class)
            ->callAction('run', arguments: ['key' => 'optimize'])
            ->assertNotified('Önbellekleri oluştur tamamlandı');

        $this->assertSame($this->app, Container::getInstance());
        $this->assertSame($this->app, Facade::getFacadeApplication());

        // Hata, önbellekler oluştuktan SONRAKİ ilk istekte çıkıyordu.
        $page->call('$refresh')->assertOk();

        $this->assertDatabaseHas('command_runs', ['command_key' => 'optimize', 'status' => CommandRun::SUCCEEDED]);
    }

    // ---------- Nabız ----------

    public function test_heartbeat_command_proves_the_scheduler_and_feeds_the_queue(): void
    {
        Queue::fake();

        $this->assertFalse(Heartbeat::healthy(Heartbeat::SCHEDULER));

        $this->artisan('system:heartbeat')->assertSuccessful();

        $this->assertTrue(Heartbeat::healthy(Heartbeat::SCHEDULER));
        Queue::assertPushed(QueueHeartbeat::class);
    }

    public function test_queue_heartbeat_is_written_only_when_a_worker_runs_the_job(): void
    {
        config(['queue.default' => 'database']);
        $this->assertFalse(Heartbeat::healthy(Heartbeat::QUEUE));

        (new QueueHeartbeat)->handle();

        $this->assertTrue(Heartbeat::healthy(Heartbeat::QUEUE));
    }

    public function test_clearing_the_cache_does_not_erase_the_heartbeat(): void
    {
        Heartbeat::beat(Heartbeat::SCHEDULER);

        // Panelden "Tüm önbellekleri temizle" çalıştırılınca yanlış alarm çıkmamalı.
        Cache::flush();

        $this->assertTrue(Heartbeat::healthy(Heartbeat::SCHEDULER));
    }

    public function test_heartbeat_goes_stale(): void
    {
        $this->travelTo(now()->subMinutes(Heartbeat::STALE_AFTER_MINUTES + 1));
        Heartbeat::beat(Heartbeat::SCHEDULER);
        $this->travelBack();

        $this->assertFalse(Heartbeat::healthy(Heartbeat::SCHEDULER));
    }

    public function test_heartbeat_is_scheduled_every_minute(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->description, 'system:heartbeat'));

        $this->assertNotNull($event, 'system:heartbeat zamanlanmamış.');
        $this->assertSame('* * * * *', $event->expression);
    }

    /**
     * Hosting proc_open'ı kapatıyor. Schedule::command() görevi ayrı süreçte başlattığı
     * için cron doğru kurulu olsa da hiçbir görev çalışmıyordu.
     */
    public function test_scheduled_tasks_run_inside_the_scheduler_process(): void
    {
        foreach (app(Schedule::class)->events() as $event) {
            $this->assertInstanceOf(
                CallbackEvent::class,
                $event,
                ($event->description ?? $event->command).' ayrı süreçte başlatılıyor; proc_open kapalı hostingde çalışmaz. InProcess::command() kullanın.'
            );
        }

        Queue::fake();
        $this->travelTo(now()->setTime(12, 34)); // yalnız her dakika koşan görevlerin zamanı

        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertTrue(Heartbeat::healthy(Heartbeat::SCHEDULER), 'Nabız görevi zamanlayıcının içinde çalışmadı.');
        Queue::assertPushed(QueueHeartbeat::class);
    }

    public function test_a_scheduled_command_that_fails_is_reported(): void
    {
        Artisan::command('test:fails', fn () => 3);

        $this->expectExceptionMessage('"test:fails" 3 koduyla bitti');

        InProcess::command('test:fails')->run($this->app);
    }

    public function test_page_shows_cron_lines_while_the_scheduler_is_silent(): void
    {
        config(['queue.default' => 'database']);

        $this->actingAs($this->admin())->get(SystemCommands::getUrl())
            ->assertOk()
            ->assertSee('Çalışmıyor')
            ->assertSee('artisan schedule:run', false)
            ->assertSee('artisan queue:work --stop-when-empty', false)
            ->assertSee(PHP_BINDIR, false);
    }

    public function test_page_hides_cron_instructions_once_both_are_healthy(): void
    {
        config(['queue.default' => 'database']);
        Heartbeat::beat(Heartbeat::SCHEDULER);
        Heartbeat::beat(Heartbeat::QUEUE);

        $this->actingAs($this->admin())->get(SystemCommands::getUrl())
            ->assertOk()
            ->assertDontSee('Cron kurulumu')
            ->assertDontSee('Çalışmıyor');
    }

    // ---------- İlk yükleme: tablo henüz yok ----------

    /** Kod hostinge yeni yüklendi, migrate henüz çalışmadı: command_runs tablosu yok. */
    private function simulateFreshDeploy(): void
    {
        Schema::drop('command_runs');
        DB::table('migrations')
            ->where('migration', '2026_09_22_000001_create_command_runs_table')
            ->delete();
    }

    public function test_page_still_opens_before_the_run_log_table_exists(): void
    {
        $this->simulateFreshDeploy();

        $this->actingAs($this->admin())->get(SystemCommands::getUrl())
            ->assertOk()
            ->assertSee('Önce "Veritabanını güncelle" komutunu çalıştırın.', false);
    }

    public function test_migrate_from_the_panel_creates_the_missing_table_and_logs_itself(): void
    {
        $this->simulateFreshDeploy();
        $this->actingAs($this->admin());

        Livewire::test(SystemCommands::class)
            ->callAction('run', arguments: ['key' => 'migrate'])
            ->assertNotified();

        $this->assertTrue(Schema::hasTable('command_runs'), 'migrate tabloyu oluşturmalıydı.');
        // Tabloyu oluşturan çalıştırma, geçmişin ilk kaydı olarak yazılır.
        $this->assertDatabaseHas('command_runs', ['command_key' => 'migrate', 'status' => CommandRun::SUCCEEDED]);
    }

    public function test_background_commands_explain_what_to_do_before_the_table_exists(): void
    {
        $this->simulateFreshDeploy();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Veritabanını güncelle');

        app(CommandRunner::class)->start('score', $this->admin());
    }

    // ---------- Görsel bağlantısı (storage:link) ----------

    /** Hosting kısıtlarını taklit eden sahte bağlantı durumu. */
    private function fakeStorageLink(bool $exists, bool $php): void
    {
        $this->app->instance(StorageLink::class, new class($exists, $php) extends StorageLink
        {
            public function __construct(private bool $fakeExists, private bool $fakePhp) {}

            public function exists(): bool
            {
                return $this->fakeExists;
            }

            public function canCreateFromPhp(): bool
            {
                return $this->fakePhp;
            }
        });
    }

    public function test_storage_link_explains_the_cron_fix_when_the_host_blocks_symlinks(): void
    {
        // Canlıdaki durum: symlink() ve exec() kapalı. Laravel'in kendi komutu
        // "Call to undefined function exec()" ile düşüyordu; artık çalıştırılmıyor bile.
        $this->fakeStorageLink(exists: false, php: false);
        Artisan::shouldReceive('call')->never();

        $run = app(CommandRunner::class)->start('storage-link', $this->admin());

        $this->assertSame(CommandRun::FAILED, $run->status);
        $this->assertStringContainsString('cron', (string) $run->output);
        $this->assertStringContainsString('ln -s', (string) $run->output);
        $this->assertStringNotContainsString('undefined function', (string) $run->output);
    }

    public function test_storage_link_reports_success_when_the_link_already_exists(): void
    {
        $this->fakeStorageLink(exists: true, php: false);
        Artisan::shouldReceive('call')->never();

        $run = app(CommandRunner::class)->start('storage-link', $this->admin());

        $this->assertSame(CommandRun::SUCCEEDED, $run->status);
        $this->assertStringContainsString('zaten kurulu', (string) $run->output);
    }

    public function test_storage_link_runs_normally_where_php_may_create_links(): void
    {
        $this->fakeStorageLink(exists: false, php: true);
        Artisan::shouldReceive('call')->once()->with('storage:link', [], \Mockery::any())->andReturn(0);

        $run = app(CommandRunner::class)->start('storage-link', $this->admin());

        $this->assertSame(CommandRun::SUCCEEDED, $run->status);
    }

    public function test_storage_link_cron_command_is_idempotent_and_uses_real_paths(): void
    {
        $command = app(StorageLink::class)->cronCommand();

        $this->assertSame(
            "[ -e '".public_path('storage')."' ] || ln -s '".storage_path('app/public')."' '".public_path('storage')."'",
            $command,
        );
    }

    public function test_page_shows_the_link_cron_only_when_it_is_needed(): void
    {
        $this->fakeStorageLink(exists: false, php: false);

        $this->actingAs($this->admin())->get(SystemCommands::getUrl())
            ->assertOk()
            ->assertSee('Kurulu değil', false)
            ->assertSee('ln -s', false);
    }

    public function test_page_shows_the_link_as_installed(): void
    {
        $this->fakeStorageLink(exists: true, php: false);

        $this->actingAs($this->admin())->get(SystemCommands::getUrl())
            ->assertOk()
            ->assertDontSee('Kurulu değil', false)
            ->assertDontSee('ln -s', false);
    }

    public function test_private_disk_is_not_served_over_http(): void
    {
        // Özel diskte Google kimlik dosyası ve teklif fotoğrafları duruyor.
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->assertFalse(Route::has('storage.local'));
        $this->assertFalse(Route::has('storage.local.upload'));
    }
}
