<?php

namespace App\Support\Console;

/**
 * Panelden çalıştırılabilen komutların TAM listesi.
 *
 * GÜVENLİK: bu bir izin listesidir. Panel serbest komut ya da parametre KABUL ETMEZ;
 * yalnız buradaki anahtarlar ve buradaki sabit parametreler çalışır. Web'den komut
 * çalıştırmak, doğrudan sunucuda kod çalıştırmak demektir — liste bilerek dar tutuldu.
 *
 * Kip:
 *   sync        → istek içinde çalışır, çıktı hemen görünür. Kısa işler için.
 *   background  → kuyruğa gider, kuyruk işçisi çalıştırır. Hostingde web isteği
 *                 genelde 30-120 saniyede kesilir; yapay zeka ve Google çağrısı
 *                 yapan uzun işler bu yüzden arka planda koşar.
 */
final class CommandCatalog
{
    /**
     * Web'den ASLA çalıştırılmayacak komutlar. Katalog bunları içeremez (testle kilitli).
     *
     * - Veri silenler: migrate:fresh/refresh/reset/rollback, db:wipe
     * - Terminal isteyenler / sonsuz döngüler: tinker, queue:work, queue:listen, serve, schedule:work
     * - Kendini dışarıda bırakanlar: down (bakım modunda panel de kapanır; terminal yoksa geri dönüş yok)
     * - Oturumları ve şifreli verileri geçersiz kılanlar: key:generate
     */
    public const FORBIDDEN = [
        'migrate:fresh', 'migrate:refresh', 'migrate:reset', 'migrate:rollback',
        'db:wipe', 'db:seed', 'db', 'tinker', 'down', 'key:generate',
        'queue:work', 'queue:listen', 'queue:clear', 'serve', 'schedule:work',
        'schema:dump', 'env:decrypt', 'env:encrypt',
    ];

    public const GROUPS = [
        'setup' => 'Kurulum ve güncelleme',
        'operations' => 'Tur operasyonu',
        'cache' => 'Önbellek',
        'seo' => 'SEO ve içerik',
        'queue' => 'Kuyruk',
        'diagnostics' => 'Tanılama',
    ];

    /**
     * @return array<string, array{
     *     command: string, arguments: array<string, mixed>, label: string, description: string,
     *     group: string, mode: 'sync'|'background', danger: bool, timeout: int
     * }>
     */
    public static function all(): array
    {
        return [
            // ---------- Kurulum ve güncelleme ----------
            'migrate' => self::entry('migrate', ['--force' => true], 'Veritabanını güncelle',
                'Yeni kod gelen tablo ve sütunları ekler. Kodu güncelledikten sonra çalıştırın. Mevcut verilere dokunmaz.',
                'setup', danger: true),
            'storage-link' => self::entry('storage:link', [], 'Görsel bağlantısını kur',
                'Yüklenen görsellerin sitede görünmesi için public/storage bağlantısını oluşturur. Kurulumda bir kez yeterli. Hosting buna izin vermiyorsa sayfa size tek seferlik bir cron komutu verir.',
                'setup'),
            'allocate-upcoming' => self::entry('tours:allocate-upcoming', [], 'Yaklaşan seferleri yerleştir',
                'Kalkışı yaklaşan seferlerde araçsız kalan grupları boş koltuklara yerleştirir. Yerleşmiş gruplara dokunmaz; gruplar bölünmez. Normalde saat başı kendiliğinden çalışır.',
                'operations'),
            'filament-assets' => self::entry('filament:assets', [], 'Panel dosyalarını yayınla',
                'Yönetim panelinin stil ve betik dosyalarını public klasörüne kopyalar. Panel bozuk görünüyorsa çalıştırın.',
                'setup'),

            // ---------- Önbellek ----------
            'optimize' => self::entry('optimize', [], 'Önbellekleri oluştur',
                'Yapılandırma, rota ve görünüm önbelleklerini oluşturarak siteyi hızlandırır. Her güncellemeden sonra çalıştırın.',
                'cache'),
            'optimize-clear' => self::entry('optimize:clear', [], 'Tüm önbellekleri temizle',
                '.env veya ayar değişikliği sitede görünmüyorsa önce bunu, sonra "Önbellekleri oluştur"u çalıştırın.',
                'cache'),
            'config-clear' => self::entry('config:clear', [], 'Yapılandırma önbelleğini temizle',
                '.env dosyasında yaptığınız değişikliğin okunmasını sağlar.',
                'cache'),
            'cache-clear' => self::entry('cache:clear', [], 'Uygulama önbelleğini temizle',
                'Site haritası dahil tüm önbelleğe alınmış veriyi siler.',
                'cache'),
            'view-clear' => self::entry('view:clear', [], 'Görünüm önbelleğini temizle',
                'Tema dosyası değişikliği sitede görünmüyorsa çalıştırın.',
                'cache'),

            // ---------- SEO ve içerik ----------
            'sitemap' => self::entry('sitemap:generate', [], 'Site haritasını yenile',
                'sitemap.xml önbelleğini temizler; bir sonraki istekte güncel hâliyle üretilir.',
                'seo'),
            'discovery' => self::entry('seo:discovery', [], 'llms.txt dosyalarını üret',
                'Yapay zeka ajanları için llms.txt ve llms-full.txt dosyalarını veritabanından yeniden yazar. Alan adı değişince mutlaka çalıştırın.',
                'seo'),
            'sync-targets' => self::entry('seo:sync-targets', [], 'SEO hedeflerini eşitle',
                'Tur, kategori ve bölge sayfalarından SEO hedef listesini günceller.',
                'seo'),
            'location-keywords' => self::entry('seo:location-keywords', [], 'Bölge kelimelerini üret',
                'Aktif il ve ilçeler için bölge adlı anahtar kelimeleri üretir.',
                'seo'),
            'catalog-keywords' => self::entry('seo:catalog-keywords', [], 'Tur kelimelerini üret',
                'Yayındaki tur ve kategoriler için anahtar kelimeleri üretir ve sayfalarına atar.',
                'seo'),
            'link-rules' => self::entry('links:build-rules', [], 'İç link kurallarını üret',
                'Yayındaki sayfalardan iç link kurallarını oluşturur.',
                'seo'),
            'links-apply' => self::entry('links:apply', [], 'İç linkleri içeriğe işle',
                'İç link kurallarını yayındaki içeriklerin gövdesine yazar. Paneldeki otomatik uygulama ayarına uyar.',
                'seo', mode: 'background'),
            'score' => self::entry('seo:score', [], 'SEO skorlarını hesapla',
                'Yayındaki tüm sayfaları yeniden skorlar. Sayfaları tek tek açtığı için birkaç dakika sürebilir.',
                'seo', mode: 'background'),
            'publish-due' => self::entry('blog:publish-due', [], 'Zamanı gelen yazıları yayınla',
                'Yayın tarihi geçmiş planlı blog yazılarını hemen yayına alır.',
                'seo'),
            'suggest-redirects' => self::entry('seo:suggest-redirects', [], 'Yönlendirme önerilerini üret',
                '404 kayıtlarından 301 yönlendirme önerileri çıkarır. Önerileri uygulamaz.',
                'seo'),
            'blog-generate' => self::entry('blog:generate', [], 'Blog yazısı üret (yapay zeka)',
                'Yazı stoku azsa yeni konular bulup yazıları kuyruğa gönderir. Yapay zeka çağrısı yapar, ücretlidir.',
                'seo', mode: 'background', danger: true),
            'enrich' => self::entry('seo:enrich', ['--limit' => 3], 'Zayıf sayfaları zenginleştir (yapay zeka)',
                'Hedef skorun altındaki en fazla 3 sayfanın içeriğini yapay zekayla genişletir. Ücretlidir.',
                'seo', mode: 'background', danger: true),
            'refresh-meta' => self::entry('seo:refresh-meta', [], 'Düşük tıklamalı başlıkları yenile (yapay zeka)',
                'Search Console verisine göre az tıklanan sayfaların meta başlık ve açıklamalarını yeniden yazar. Ücretlidir.',
                'seo', mode: 'background', danger: true),
            'sync-search-console' => self::entry('seo:sync-search-console', [], 'Search Console verisini çek',
                'Son 28 günün arama sorgularını Google Search Console\'dan alır.',
                'seo', mode: 'background'),
            'sync-rankings' => self::entry('seo:sync-rankings', [], 'Sıralamaları güncelle',
                'Hedef kelimelerin Google sıralamalarını Search Console verisinden günceller.',
                'seo', mode: 'background'),
            'check-index' => self::entry('seo:check-index', [], 'Dizin durumunu kontrol et',
                'Sayfaların Google dizininde olup olmadığını Search Console\'a sorar.',
                'seo', mode: 'background'),
            'discover-keywords' => self::entry('seo:discover-keywords', [], 'Yeni kelime fırsatlarını bul',
                'Search Console sorgularından henüz hedeflenmeyen kelimeleri kelime havuzuna ekler.',
                'seo', mode: 'background'),

            // ---------- Kuyruk ----------
            'queue-retry' => self::entry('queue:retry', ['id' => ['all']], 'Başarısız işleri tekrar dene',
                'Başarısız olmuş tüm kuyruk işlerini yeniden sıraya koyar.',
                'queue'),
            'queue-flush' => self::entry('queue:flush', [], 'Başarısız işleri sil',
                'Başarısız iş kayıtlarını kalıcı olarak siler. Bekleyen işlere dokunmaz.',
                'queue', danger: true),
            'queue-restart' => self::entry('queue:restart', [], 'Kuyruk işçisini yeniden başlat',
                'Kod güncellemesinden sonra çalışan işçinin yeni kodu yüklemesini sağlar.',
                'queue'),

            // ---------- Tanılama ----------
            'about' => self::entry('about', [], 'Sistem bilgisi',
                'Laravel ve PHP sürümü, ortam, önbellek ve sürücü ayarlarını gösterir.',
                'diagnostics'),
            'migrate-status' => self::entry('migrate:status', [], 'Veritabanı sürüm durumu',
                'Hangi veritabanı güncellemelerinin uygulandığını listeler.',
                'diagnostics'),
            'schedule-list' => self::entry('schedule:list', [], 'Zamanlanmış görevler',
                'Otomatik çalışan görevleri ve bir sonraki çalışma zamanlarını listeler.',
                'diagnostics'),
            'queue-failed' => self::entry('queue:failed', [], 'Başarısız işler',
                'Başarısız olmuş kuyruk işlerini ve hata nedenlerini listeler.',
                'diagnostics'),
        ];
    }

    /** @return array<string, mixed>|null */
    public static function find(?string $key): ?array
    {
        return $key === null ? null : (self::all()[$key] ?? null);
    }

    /** @return array<string, array<string, array<string, mixed>>> grup anahtarı => [komut anahtarı => tanım] */
    public static function grouped(): array
    {
        $out = array_fill_keys(array_keys(self::GROUPS), []);

        foreach (self::all() as $key => $definition) {
            $out[$definition['group']][$key] = $definition;
        }

        return array_filter($out);
    }

    /** Terminalde yazılacak hâli: "migrate --force", "queue:retry all". Yalnız gösterim içindir. */
    public static function commandLine(array $definition): string
    {
        $parts = [$definition['command']];

        foreach ($definition['arguments'] as $name => $value) {
            if (! str_starts_with($name, '--')) {
                $parts[] = implode(' ', (array) $value);
            } elseif ($value === true) {
                $parts[] = $name;
            } else {
                $parts[] = $name.'='.$value;
            }
        }

        return implode(' ', $parts);
    }

    private static function entry(
        string $command,
        array $arguments,
        string $label,
        string $description,
        string $group,
        string $mode = 'sync',
        bool $danger = false,
    ): array {
        return [
            'command' => $command,
            'arguments' => $arguments,
            'label' => $label,
            'description' => $description,
            'group' => $group,
            'mode' => $mode,
            'danger' => $danger,
            // Arka plan işleri kuyruk işinin kendi süresiyle sınırlıdır.
            'timeout' => $mode === 'background' ? 900 : 120,
        ];
    }
}
