<?php

namespace App\Services\Admin;

use App\Jobs\RunConsoleCommand;
use App\Models\CommandRun;
use App\Models\User;
use App\Support\AutomationLog;
use App\Support\Console\CommandCatalog;
use App\Support\StorageLink;
use Closure;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Livewire\ComponentHookRegistry;
use ReflectionProperty;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;
use Throwable;

/**
 * Katalogdaki bir komutu panelden çalıştırır ve sonucunu kaydeder.
 *
 * Komut adı ve parametreleri YALNIZ katalogdan gelir; kullanıcıdan hiçbir metin
 * komuta geçmez. Aynı komut aynı anda iki kez çalışmaz.
 */
class CommandRunner
{
    /** Veritabanına yazılan çıktının üst sınırı. İlerleme çubukları çıktıyı şişirebilir. */
    private const MAX_OUTPUT = 200_000;

    /**
     * Kayıt tablosu var mı?
     *
     * Kod hostinge ilk yüklendiğinde command_runs tablosu henüz yoktur ve onu
     * oluşturacak "migrate" komutu da ancak bu panelden çalıştırılabilir. Tablo
     * yokken panel kilitlenmesin diye kısa komutlar kayıt tutmadan çalışır.
     */
    public function tableReady(): bool
    {
        try {
            return Schema::hasTable((new CommandRun)->getTable());
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Komutu başlatır: kısa işler hemen çalışır, uzun işler kuyruğa gider.
     *
     * @throws InvalidArgumentException katalogda olmayan anahtar
     * @throws RuntimeException aynı komut zaten çalışıyor
     */
    public function start(string $key, ?User $user): CommandRun
    {
        $definition = CommandCatalog::find($key);

        if ($definition === null || in_array($definition['command'], CommandCatalog::FORBIDDEN, true)) {
            throw new InvalidArgumentException('Bu komut panelden çalıştırılamaz.');
        }

        $attributes = [
            'command_key' => $key,
            'command_line' => CommandCatalog::commandLine($definition),
            'mode' => $definition['mode'],
            'status' => CommandRun::QUEUED,
            'user_id' => $user?->getKey(),
        ];

        if (! $this->tableReady()) {
            // Arka plan işi kaydı üzerinden yürür; kayıt tablosu olmadan kuyruğa atılamaz.
            if ($definition['mode'] === 'background') {
                throw new RuntimeException('Önce "Veritabanını güncelle" komutunu çalıştırın; komut geçmişi tablosu henüz oluşturulmadı.');
            }

            return $this->execute(new CommandRun($attributes));
        }

        $this->expireStaleRuns();

        if (CommandRun::query()->where('command_key', $key)->active()->exists()) {
            throw new RuntimeException('Bu komut zaten sırada ya da çalışıyor. Bitmesini bekleyin.');
        }

        $run = CommandRun::create($attributes);

        if ($definition['mode'] === 'background') {
            RunConsoleCommand::dispatch($run->getKey());

            return $run->refresh();
        }

        return $this->execute($run);
    }

    /** Kaydı alıp komutu GERÇEKTEN çalıştırır. Hem istek içinden hem kuyruk işinden çağrılır. */
    public function execute(CommandRun $run): CommandRun
    {
        $definition = CommandCatalog::find($run->command_key);

        if ($definition === null) {
            return $this->finish($run, 1, 'Komut artık izin listesinde değil; çalıştırılmadı.', microtime(true));
        }

        // Kuyruk işi yeniden denendiğinde bitmiş bir kaydı tekrar çalıştırma.
        if (! $run->isActive()) {
            return $run;
        }

        $this->persist($run, ['status' => CommandRun::RUNNING, 'started_at' => now()]);

        // Tarayıcı sekmesi kapanırsa işlem yarıda kalmasın (örn. veritabanı güncellemesi).
        // İki fonksiyon da paylaşımlı hostinglerde kapatılabiliyor. PHP 8'de kapalı bir
        // fonksiyonu çağırmak "@" ile bastırılamayan bir hata fırlatır; önce varlığı sorulur.
        if (function_exists('ignore_user_abort')) {
            ignore_user_abort(true);
        }

        if (function_exists('set_time_limit')) {
            set_time_limit($definition['timeout']);
        }

        $started = microtime(true);

        if ($precheck = $this->precheck($run->command_key)) {
            return $this->finish($run, $precheck[0], $precheck[1], $started);
        }

        $output = new BufferedOutput;
        $failure = null;
        $restore = $this->preserveApplicationState();

        try {
            $exitCode = Artisan::call($definition['command'], $definition['arguments'], $output);
        } catch (Throwable $e) {
            $exitCode = 1;
            $failure = $e;
        } finally {
            $restore();
        }

        $text = $output->fetch();

        if ($failure !== null) {
            $text = trim($text."\n\nHATA: ".$failure->getMessage());

            AutomationLog::error('system.command', $failure->getMessage(), ['command' => $run->command_line]);
        }

        return $this->finish($run, $exitCode, $text, $started);
    }

    /**
     * Komutun değiştirdiği uygulama durumunu isteğe geri verir.
     *
     * config:cache ve route:cache ("Önbellekleri oluştur" ikisini de çağırır) önbelleği
     * üretmek için bootstrap/app.php'den TAZE bir uygulama başlatır. Laravel bu sırada
     * global container'ı ve facade'ları o yeni uygulamaya çevirir; Livewire da bileşen
     * kancalarını tutan statik listeyi sıfırlar. Panel isteği bu hâliyle sürerse yanıtın
     * snapshot'ı eksik çıkar, bildirim kaybolur ve sayfadaki bir sonraki tıklama
     * "Undefined array key children" hatasıyla düşer.
     */
    private function preserveApplicationState(): Closure
    {
        $container = Container::getInstance();
        $facades = Facade::getFacadeApplication();
        $resolver = Model::getConnectionResolver();
        $dispatcher = Model::getEventDispatcher();

        $hooks = class_exists(ComponentHookRegistry::class)
            ? new ReflectionProperty(ComponentHookRegistry::class, 'components')
            : null;
        $components = $hooks?->getValue();

        return function () use ($container, $facades, $resolver, $dispatcher, $hooks, $components): void {
            Container::setInstance($container);
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($facades);

            if ($resolver !== null) {
                Model::setConnectionResolver($resolver);
            }

            if ($dispatcher !== null) {
                Model::setEventDispatcher($dispatcher);
            }

            $hooks?->setValue(null, $components);
        };
    }

    /**
     * Komutu çalıştırmadan önce sonucu belli olan durumları yakalar.
     *
     * storage:link, symlink() ve exec() kapalı bir hostingde "Call to undefined
     * function exec()" gibi anlaşılmaz bir hatayla düşer. Onun yerine ne yapılacağını
     * söyleyen bir mesaj verilir.
     *
     * @return array{0: int, 1: string}|null [çıkış kodu, mesaj] ya da null (normal çalıştır)
     */
    private function precheck(string $key): ?array
    {
        if ($key !== 'storage-link') {
            return null;
        }

        $link = app(StorageLink::class);

        if ($link->exists()) {
            return [0, 'Görsel bağlantısı zaten kurulu. Yapılacak bir şey yok.'];
        }

        if (! $link->canCreateFromPhp()) {
            return [1, implode("\n", [
                'Hosting, PHP\'den bağlantı oluşturmayı kapatmış (symlink ve exec fonksiyonları devre dışı).',
                'Bağlantıyı tek seferlik bir cron göreviyle kurun. Hosting panelinizde her dakika çalışan yeni bir cron görevi ekleyin:',
                '',
                $link->cronCommand(),
                '',
                'Bir-iki dakika sonra bu sayfayı yenileyin; "Kurulu" görününce cron görevini silin.',
            ])];
        }

        return null;
    }

    /**
     * Sunucu zaman sınırıyla ya da çökmeyle yarıda kalan kayıtları kapatır.
     *
     * Aksi hâlde "çalışıyor" durumunda asılı kalan bir kayıt, o komutun bir daha
     * hiç çalıştırılamamasına yol açar.
     */
    public function expireStaleRuns(): int
    {
        if (! $this->tableReady()) {
            return 0;
        }

        $expired = 0;

        foreach (CommandRun::query()->active()->get() as $run) {
            $definition = CommandCatalog::find($run->command_key);
            $timeout = ($definition['timeout'] ?? 900) + 60;

            // Sıradaki iş işçi gelene kadar bekleyebilir; ona daha uzun süre tanınır.
            $limit = $run->status === CommandRun::QUEUED
                ? $run->created_at->copy()->addDay()
                : ($run->started_at ?? $run->created_at)->copy()->addSeconds($timeout);

            if ($limit->isPast()) {
                $run->update([
                    'status' => CommandRun::FAILED,
                    'finished_at' => now(),
                    'output' => trim(($run->output ?? '')."\n\nZaman aşımı: işlem tamamlanmadan kesildi. Sunucu zaman sınırına takılmış olabilir."),
                ]);
                $expired++;
            }
        }

        return $expired;
    }

    private function finish(CommandRun $run, int $exitCode, string $text, float $started): CommandRun
    {
        // Renk kodları ve satır sonu boşlukları temizlenir; panelde düz metin gösterilir.
        $text = preg_replace('/\e\[[\d;]*[A-Za-z]/', '', $text) ?? $text;
        $text = trim(preg_replace("/[ \t]+\n/", "\n", $text) ?? $text);

        if (mb_strlen($text) > self::MAX_OUTPUT) {
            $text = mb_substr($text, 0, self::MAX_OUTPUT)."\n\n… çıktı kısaltıldı.";
        }

        $this->persist($run, [
            'status' => $exitCode === 0 ? CommandRun::SUCCEEDED : CommandRun::FAILED,
            'exit_code' => $exitCode,
            'output' => $text === '' ? null : $text,
            'finished_at' => now(),
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);

        return $run;
    }

    /**
     * Kaydı günceller; tablo yoksa yalnız bellekte doldurur.
     *
     * Tabloyu oluşturan "migrate" bittiğinde tablo artık vardır: o çalıştırma da
     * geçmişe ilk kayıt olarak yazılır.
     */
    private function persist(CommandRun $run, array $attributes): void
    {
        $run->fill($attributes);

        if ($run->exists || $this->tableReady()) {
            $run->save();
        }
    }
}
