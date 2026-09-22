# Visit Tur — devir notu (Claude için)

Bu dosya, projeye yeni bir Claude oturumunda devam edilsin diye yazıldı. Proje, `~/Desktop/montaj`
(DOST MONTAJ, canlıda dostmontaj.com) projesinin altyapısı kopyalanıp **tur firmasına** dönüştürülerek
kuruldu. Önceki sohbetin tamamı burada özetlidir; montaj sohbetine gerek yoktur.

Kullanıcı Türkçe yazar, kısa ve gündelik mesajlar atar. İşi baştan sona devretmeyi sever ("bitince
bana bildir"). Cevapları Türkçe ver.

---

## Kesin kurallar (ihlal etme)

- **Veritabanı paylaşımlı.** Yerel MySQL (`127.0.0.1`, `root`) kullanıcının başka projelerini de barındırır
  (montaj'ın `trakya_mobilya_montaj`, `eyyopos`, `fitness`, `wedding`… gibi). Bu projenin veritabanı
  **`visit_tur`**. Hiçbir koşulda `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe`
  çalıştırma. Yalnız `php artisan migrate` (eklemeli) ve idempotent seeder'lar.
- **Testler yalnız bellek içi SQLite.** `tests/TestCase.php` bağlantı farklıysa testi durdurur.
  Testleri `php vendor/bin/phpunit` ile çalıştır.
- **Port 8000 kullanıcının montaj sunucusudur**, dokunma. Bu proje **8010**'da çalışır:
  `php artisan serve --port=8010` (önceki oturumdan açık kalmış olabilir; `lsof -iTCP:8010`).
- **`~/Desktop/montaj` ayrı ve canlı bir projedir**, bu projeden oraya yazma.
- **Bölge yalnız Doğu Karadeniz (Rize + Trabzon).** Kullanıcı açıkça "Trakya bölgesinde işimiz yok"
  dedi. Trakya / Tekirdağ / Kapadokya içeriği geri getirme.
- Commit / push yalnız kullanıcı isteyince.

## Proje nedir

Rize ve Trabzon çıkışlı **günübirlik tur** firması için web sitesi + operasyon paneli.

- **Site:** turlar, fiyatlar, tur takvimi, "{il/ilçe} çıkışlı günübirlik turlar" bölgesel SEO
  sayfaları, blog, galeri, rezervasyon talep formu (koltuk ayırmaz, ön taleptir).
- **Panel (`/admin`, Filament):** tur kayıtları (seferler), araç filosu, gruplar + yolcular
  (ad, soyad, TC, telefon, yaş, cinsiyet), **grupları bölmeden araçlara dağıtma**, yazdırılabilir
  yolcu listesi, rezervasyon talepleri, SEO & yapay zekâ modülü, Sistem Komutları.

### Çekirdek kural: GRUP BÖLÜNMEZ

Araç ataması **grupta** tutulur (`tour_groups.departure_vehicle_id`); yolcunun araç alanı yoktur.
Bir grup ya tamamıyla tek araca biner ya da "yerleşmeyi bekleyenler"de kalır. Araç taşmaz.
Dağıtım önceliği: (1) en çok yolcu, (2) en az araç, eşitlikte en az koltuk, (3) araçları liste
sırasıyla doldur. Elle "Taşı" = o araca sabitle; "Baştan dağıt" sabitlenenlere dokunmaz.
Grup büyürse / araç küçülür ya da silinirse gruplar araçtan çıkarılır (silinmez).
Kalkışa 48 saat kala (`auto_allocate_hours` ayarı) `tours:allocate-upcoming` saat başı bekleyenleri yerleştirir.

### Roller (`app/Enums/UserRole.php`, ilkeler `app/Policies`)

| Rol | Yetki |
| --- | --- |
| `super_admin` | Her şey |
| `operasyon` | Sefer, araç, dağıtım, tur/fiyat, talep atama |
| `kayit` | Grup/yolcu kaydı, talepler; yalnız kendi girdiği grubu silebilir |
| `icerik` | Site içeriği + SEO; **yolcu verisini göremez** |
| `rehber` | Yalnız rehberi olduğu seferleri görür; değiştiremez |

Yeni hesap varsayılanı `rehber` (en dar yetki).

## Çalıştırma

```bash
cd ~/Desktop/visit
php artisan serve --port=8010        # site: http://127.0.0.1:8010  panel: /admin
npm run build                        # SCSS/JS değişince (public/build depoya dahil edilecek)
php vendor/bin/phpunit               # 547 test, hepsi geçiyor (son durum)
```

- **Yerel yönetici:** `admin@example.com` / `password` (Süper Yönetici). Canlıda zayıf parolayla
  hesap oluşturulmaz (`AdminUserSeeder`).
- Taze kurulumdan sonra panel JS için `php artisan filament:upgrade` gerekir; yoksa panelde
  "filamentDropdown is not defined" hataları çıkar.
- SEO verisini yenileme sırası: `seo:sync-targets` → `seo:catalog-keywords` → `seo:location-keywords` → `seo:discovery`.
- Yerelde örnek içeriği baştan yüklemek gerekirse: YALNIZ `visit_tur` içindeki içerik tablolarını
  boşaltıp `php artisan db:seed --force` (users tablosuna dokunma). Seeder'lar `firstOrCreate` ile çalışır.

## Teknik yığın

Laravel 12.69 · PHP 8.2 · Filament 5.8 (Livewire) · MySQL · Vite 7 · Bootstrap 5 + SCSS (site) ·
Tailwind 4 yalnız panel teması · Font Awesome 7 (ayrı `icons.scss`, engellemeyen yükleme) ·
fontlar @fontsource ile yerel: **Plus Jakarta Sans** (gövde) + **Fraunces** (başlık).
Saat dilimi `Europe/Istanbul` (`APP_TIMEZONE`). Cache/session/queue sürücüleri `database`.

## Dosya haritası

| Ne | Nerede |
| --- | --- |
| Dağıtım algoritması (saf, DB bilmez) | `app/Services/Allocation/VehicleAllocator.php` |
| Dağıtımı kaydetme, elle taşıma, araç önerisi | `app/Services/Allocation/DepartureAllocator.php` |
| Dağılım panosu | `app/Filament/Resources/TourDepartures/Pages/DepartureAllocation.php` + `resources/views/filament/resources/tour-departures/` |
| Grup + yolcu formu, TC doğrulama | `app/Filament/Resources/TourGroups/`, `app/Rules/TcKimlikNo.php` |
| Modeller | `Tour`, `TourCategory`, `TourDeparture`, `DepartureVehicle`, `Vehicle`, `TourGroup`, `Passenger`, `ReservationRequest` |
| Operasyon göçü | `database/migrations/2026_09_22_000002_create_operation_tables.php` |
| Yolcu listesi (manifesto) | `app/Http/Controllers/ManifestController.php`, `resources/views/admin/manifest.blade.php` |
| Ön yüz denetleyicileri | `TourController`, `RegionController`, `ReservationController`, `HomeController` |
| Tema (renkler) | `resources/scss/_variables.scss`, `resources/scss/_theme.scss` |
| Örnek içerik | `database/seeders/data/tours.php`, `database/seeders/data/regions.php`, `SettingsSeeder`, `FaqSeeder` |
| Örnek görseller + lisans kaynakları | `database/seeders/images/` + `CREDITS.md` (Wikimedia, CC0/kamu malı) |
| Panel komut listesi (terminalsiz sunucu için) | `app/Support/Console/CommandCatalog.php` |
| AI istemlerindeki firma tanımı | `app/Support/BusinessContext.php` (aktif illerden türetilir) |
| SEO modülünün şartnamesi | `seo_sistemi_promptu.md` |
| Kullanıcıya yönelik belge | `README.md` |

## Mevcut içerik durumu

- **9 tur** (hepsi TRY, fiyatlar ÖRNEK, bölgedeki gerçek programlara göre yazıldı):
  Ayder Yaylası (1.400), Uzungöl (1.250), Sümela–Karaca Mağarası–Hamsiköy (1.350),
  Günübirlik Batum (1.650), Pokut ve Sal Yaylası (1.750, eski 1.900), Huser Gün Batımı (1.650),
  Zilkale ve Palovit Şelalesi (1.500), Trabzon Şehir Turu (1.100), Batum Tiflis 2 gece (9.800, eski 10.500).
- **4 kategori:** Yayla Turları, Göl ve Vadi Turları, Kültür Turları, Batum ve Gürcistan Turları.
- **Bölgeler:** Rize (12 ilçe) + Trabzon (18 ilçe). **Biniş noktaları bilerek boş** (kullanıcı girecek).
- Site adı **"Visit Tur"** yer tutucudur; logo `public/images/brand/logo.svg` yer tutucu SVG.
- **TÜRSAB no / ticari unvan boş** (Site Ayarları'nda alan var; doluysa footer'da görünür, yasal zorunluluk).
- `DemoOperationSeeder` yalnız production DEĞİLKEN örnek seferler + kurgusal yolcular ekler.
- Batum için araştırılan güncel bilgi sayfalara işlendi: T.C. vatandaşı çipli kimlikle geçer,
  çocuk kimliğinde fotoğraf şart, 2026'dan beri girişte seyahat sağlık sigortası isteniyor.

## Kullanıcının tasarım tercihleri

- **Canlı renkler.** İlk koyu petrol paletini "kötü" buldu. Şu an: gök mavisi `#0d7de0`, turuncu CTA
  `#ff6a1f`, turkuaz `#12b5cb`, güneş sarısı `#ffc530`, geçişli butonlar ve bantlar, fotoğraf
  katmanları hafif. Koyu/soluk tonlara dönme.
- Başlıkta **yeşil oval "Ara" butonu** (ikonlu, telefon ikonu sallanır) + turuncu "Rezervasyon".
- "Hata görmek istemiyorum": JS hatası, yatay taşma, kırık görsel bırakma. Mobilde kontrol et.

## Barındırma bağlamı (montaj'dan öğrenilenler, bu proje de aynı yere kurulacak)

- cPanel + LiteSpeed + CloudLinux alt-php82 + Cloudflare. **Terminal yok.**
- Dağıtım: cPanel **Git Version Control** → "Update from Remote" (GitHub). `vendor/` elle yüklenir.
- Kapalı PHP fonksiyonları: `proc_open`, `pcntl_*`, `exec`, `symlink`, `escapeshellarg`, `highlight_file`. PHP 8'de kapalı
  fonksiyon çağırmak `@` ile bastırılamayan Error fırlatır → `function_exists` ile koru.
  `tests/Feature/SharedHostingCompatibilityTest.php` bunu sınar.
- **Zamanlanmış görevler `InProcess::command()` ile eklenir, `Schedule::command()` ile DEĞİL**
  (`routes/console.php`, `app/Support/Console/InProcess.php`). `Schedule::command()` her görevi ayrı
  süreçte başlatır, bu da `proc_open` ister; hosting açmıyor. Montaj'da cron "DONE" yazıp hiçbir görevi
  çalıştırmıyordu. `SystemCommandsTest::test_scheduled_tasks_run_inside_the_scheduler_process` yakalar.
- "Önbellekleri oluştur" (`optimize`) taze bir uygulama başlatır; `CommandRunner::preserveApplicationState`
  panel isteğinin container'ını ve Livewire kancalarını geri verir (yoksa sonraki tıklama
  "Undefined array key children" ile düşer).
- **Kuyruk işçisi `App\Support\Queue\SharedHostingWorker`** (`AppServiceProvider::register`'da
  `extend('queue.worker')`). Hostingde pcntl eklentisi yüklü ama `pcntl_*` fonksiyonları kapalı; Laravel'in
  işçisi yalnız eklentiye bakıp `pcntl_async_signals()` çağırdığı için montaj'da cron'daki `queue:work` her
  dakika çöküyor, işler birikiyordu (panel: "Kuyruk işçisi çalışmıyor"). Bu işçi fonksiyonlara da bakar.
- **"Update from Remote" → "could not contact the remote repository" çoğu zaman AĞ DEĞİLDİR.** cPanel,
  sunucuda elle değiştirilmiş ya da elle yüklenmiş (git'teki yeni dosyayla aynı adlı) bir dosya birleştirmeyi
  engellediğinde de bu mesajı verir. Montaj'da sebep elle yüklenen iki logoydu; hosting firması "bizde sorun
  yok" dedi ve haklıydı. Git'teki dosyalar Dosya Yöneticisi ile elle yüklenmez/düzenlenmez; elle yalnız
  `.env` (ve montaj'da `public/build`). Teşhis için montaj oturumunda salt okunur bir PHP betiği yazıldı:
  `.git/index`'i okuyup çalışma ağacıyla karşılaştırır, kilit dosyalarına ve GitHub bağlantısına bakar
  (betik saklanmadı; gerekirse aynı mantıkla yeniden yazılır).
- MySQL için `DB_HOST=localhost` (soket). cPanel'in `AddHandler application/x-httpd-alt-php82`
  satırı tüm .php dosyalarını 404 yaptı; eklenmemeli.
- `storage:link` panelden olmazsa Sistem Komutları sayfası tek seferlik cron satırı verir.
- Montaj'da yaşanan kaza: `public/build` yüklendi ama kod çekilmedi → ikonlar kayboldu, form bozuldu.
  Bu yüzden bu projede **`public/build` ve `public/{js,css,fonts}/filament` `.gitignore`'dan çıkarıldı**
  (depoya dahil). `public/llms*.txt` depoya girmez.
- Cron: her dakika `schedule:run` ve kuyruk işçisi; satırları Sistem Komutları sayfası üretir.

## Açık işler (öncelik sırasıyla)

1. ~~Git deposu yok.~~ Yapıldı: `https://github.com/Migrain-code/visit.git`, `main` gönderildi.
2. **Canlıda ilk kurulum yolu yok:** panelde `db:seed` güvenlik için yasak (`CommandCatalog::FORBIDDEN`),
   bu yüzden sunucuda ilk yönetici ve örnek içerik oluşturulamaz. Seçenekler: yerel `visit_tur`'u
   phpMyAdmin'e aktarmak ya da yalnız `AdminUserSeeder` + içerik seeder'larını çalıştıran güvenli bir
   kurulum komutunu kataloğa eklemek. Kullanıcıya sor.
3. Gerçek fiyat/saatler, biniş noktaları, TÜRSAB no + unvan, logo, kendi fotoğrafları,
   KVKK ve Tur Sözleşmesi metinleri (şablon), Batum sigortasının fiyata dahil olup olmadığı.
4. Canlı `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, DB, `ADMIN_EMAIL/PASSWORD`,
   `MAIL_*` (talep bildirimi için), isteğe bağlı `RECAPTCHA_*`, Google Analytics kimliği panelden.
   `.env` değişince panelden sırasıyla "Tüm önbellekleri temizle" → "Önbellekleri oluştur" (yoksa eski
   ayar önbellekte kalır; montaj'da `APP_DEBUG` bu yüzden canlıda açık kaldı).
5. Alan adı belli olunca: `APP_URL`, `seo:discovery` (llms.txt alan adını içerir), Search Console.

## Bilinen tuzaklar

- **Filament form doldurma:** forma değer vererek `fill()` yapmak alan varsayılanlarını devre dışı
  bırakır → `CreateTourGroup::fillForm` ön doldurmaya `status`, `paid_amount`, boş yolcu satırını ekler.
- Testte repeater satır anahtarları için `Repeater::fake()` kullanılıyor (`TourOperationsTest::setUp`).
- `tour_groups.paid_amount` NOT NULL → boş bırakılırsa `TourGroup::saving` 0 yapar.
- Grup sayısı yolcu satırlarından türetilir (`Passenger` saved/deleted → `refreshPassengerCount`);
  test verisinde grup oluştururken gerçek yolcu satırı ekle.
- Wikimedia Commons görselleri: yalnız standart küçük resim genişlikleri (1280, 1920) iner;
  art arda istekte 429 döner, istekler arasında bekle. Yalnız CC0 / kamu malı kullan, `CREDITS.md`'yi güncelle.
- Ekran görüntüsü: `"/Applications/Google Chrome.app/Contents/MacOS/Google Chrome" --headless=new --screenshot=… --window-size=1440,1100 URL`.
  Panel sayfaları için oturum gerekir: geçici bir klasöre `puppeteer-core` kurup Chrome'u o yolla sür
  (login formu: `input[type=email]`, `input[type=password]`, `button[type=submit]`).
- Kök dizindeki slug'lar (tur, il, sayfa) çakışamaz; ayrılmış adresler `app/Rules/UniquePublicSlug.php` içinde.
