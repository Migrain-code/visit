# RTEÜ Geziyor — devir notu (Claude için)

Bu dosya, projeye yeni bir Claude oturumunda devam edilsin diye yazıldı. Proje önce `~/Desktop/montaj`
(DOST MONTAJ) altyapısından "Visit Tur" olarak kuruldu, sonra **kökten sadeleştirilip** üniversite öğrenci
gezi topluluğu **RTEÜ Geziyor** için tek sayfalık site + mobil odaklı operasyon paneline dönüştürüldü.

Kullanıcı Türkçe yazar, kısa ve gündelik mesajlar atar. İşi baştan sona devretmeyi sever ("bitince
bana bildir"). Cevapları Türkçe ver.

---

## Kesin kurallar (ihlal etme)

- **Veritabanı paylaşımlı.** Yerel MySQL (`127.0.0.1`, `root`) kullanıcının başka projelerini de barındırır.
  Bu projenin veritabanı **`visit_tur`**. Hiçbir koşulda `migrate:fresh`, `migrate:refresh`, `migrate:reset`,
  `db:wipe` çalıştırma. Yalnız `php artisan migrate` (eklemeli) ve idempotent seeder'lar.
- **Testler yalnız bellek içi SQLite.** `tests/TestCase.php` bağlantı farklıysa testi durdurur.
  `php vendor/bin/phpunit` ile çalıştır.
- Yerel sunucu: **8010** (`php artisan serve --port=8010`); kullanıcı 8000'de de aynı projeyi açmış olabilir.
- **`~/Desktop/montaj` ayrı ve canlı bir projedir**, oraya yazma.
- Commit / push yalnız kullanıcı isteyince.

## Proje nedir (sadeleştirilmiş hâli)

**Site (tek sayfa):** üst bar (RTEÜ **GEZİYOR** · "Keşfet · Tanış · Yaşa"), büyük hero kartı, "Haftalık Turlar"
kart ızgarası (tıklanınca detay penceresi + "Katılmak İstiyorum" → iletişim formu), 4 maddelik öne çıkanlar şeridi,
Instagram bandı. Ayrı sayfalar: `/iletisim` (form → panelde "İletişim Talepleri") ve `/is-basvurusu` (form +
özgeçmiş → "İş Başvuruları"). Tur listesi, takvim, bölgeler, galeri, blog, SSS, hakkımızda **kaldırıldı** (kod ve
testleriyle). SEO/AI modülü, iç link motoru, 404/yönlendirme gözlemi de kaldırıldı; eski tabloları DB'de duruyor
(silinmedi), kod artık kullanmıyor.

**Panel (`/admin`, Filament 5):** herkes telefondan kullanır → mobil alt sekme çubuğu
(`resources/views/filament/partials/mobile-tabs.blade.php`) + PWA manifesti (`/admin/manifest.webmanifest`,
"ana ekrana ekle"). Menü: Operasyon (Tüm Turlar, Yolcu Ekle, Gruplar, Yolcular, Araç Liste Sihirbazı, Araçlar,
Araç Geçmişi, İletişim Talepleri, İş Başvuruları) · Personel (Personel, Kazançlarım) · Rapor (Kasa, Komisyon Raporu)
· Ayarlar (Site Ayarları, Sistem Komutları). Pano = özet kartları + tek grafik.

### Veri modeli — TUR = TARİHLİ TEK GEZİ

Katalog/sefer ayrımı kaldırıldı. `App\Models\TourDeparture` (tablo `tour_departures`) artık **turun kendisidir**:
`title, image, badge, short_description, description, starts_at, ends_on, price, meeting_point, sort_order, status,
is_public, guide_id`. `tour_id` sütunu kaldı ama boş/kullanılmıyor (`tours` tablosu artık okunmaz). Sınıf adı
geçmişten kalma; panelde/sitede adı "Tur". Ana sayfa sırası `sort_order` (Tüm Turlar tablosunda sürükle-bırak).

**Yolcu Ekle** (`TourGroupResource` create): tur seç → yolcu kartlarını gir (ad, soyad, TC, telefon, nereden
binecek, cinsiyet, yaş, not) → kaydet → bir **grup** olur. Ad boşsa `TourGroup::nextName()` "Grup 1, 2…" verir.
İletişim kişisi/telefonu ve biniş özeti **ilk yolcudan/yolculardan türer** (`TourGroup::refreshPassengerCount`,
`CreateTourGroup::mutateFormDataBeforeCreate`). `passengers.pickup_point` yolcu başına.

### Çekirdek kural: GRUP BÖLÜNMEZ

Araç ataması **grupta** (`tour_groups.departure_vehicle_id`). Dağıtım önceliği: (1) en çok yolcu, (2) **en az araç**
(22 yolcu 26'lık tek araca sığıyorsa oraya gider), eşitlikte en az koltuk, (3) araçları liste sırasıyla doldur.
Elle "Taşı" = sabitle; "Baştan dağıt" sabitlenenlere dokunmaz. Grup büyürse / araç küçülür ya da silinirse
gruplar araçtan çıkarılır (silinmez). Kalkışa 48 saat kala (`auto_allocate_hours`) `tours:allocate-upcoming` saat başı.

**Araç Liste Sihirbazı** (`app/Filament/Pages/VehicleWizard.php`): tur seç → araç satırları (filodan seç → ad/koltuk/
plaka/şoför dolar; ücret, rehber, not, görevli koltuğu) → "Araçları kaydet" (yerinde günceller; listeden çıkan araç
turdan silinir, grupları bekleyenlere döner) → "Grupları yerleştir" (keepExisting: true). Özet kartlarında boş koltuk.
`departure_vehicles.cost` (araç ücreti) ve `guide_id` (araç rehberi) buradan girilir. **Araç Geçmişi** =
`VehicleHistoryResource` (departure_vehicles üzerinden salt okunur).

### Yetkiler (`app/Enums/Permission.php`) — rol yok, kişi başına kutu

`users.is_super_admin` + `users.permissions` (json). Yetkiler: `tours.manage`, `vehicles.manage`,
`allocation.manage`, `groups.create` (yalnız kendi girdiği grubu düzenler/siler), `groups.manage`, `requests.manage`
(iletişim talepleri + iş başvuruları), `commissions.manage`, `reports.view`, `users.manage`, `settings.manage`.
Yardımcılar `User` modelinde (`managesTours()`, `managesOperations()`, `registersGroups()`, `seesPassengers()`…).
**Yetkisiz hesap = rehber:** yalnız rehberi olduğu turları (turun `guide_id`'si ya da bir aracının `guide_id`'si)
ve kendi kazancını görür (`scopeVisibleTo`, `isGuideOf`). Eski `role` sütunu DB'de duruyor, kullanılmıyor.
Yetki yöneticisi süper yöneticiyi düzenleyemez; kimse kendini silemez. Testte kullanıcı değiştirmek için
`tests/TestCase::actingAs` oturumu sıfırlar (Filament AuthenticateSession 302 vermesin diye).

### Komisyon ve kasa

- `tour_commissions` (tur × personel benzersiz, `amount`): tur özetindeki **"Komisyon ekle"** eylemi
  (`ViewTourDeparture::commissionAction`): "Tümüne uygula" + personel başına kutu; boş = silinir.
- `Kazançlarım` (`MyEarnings`): herkes yalnız kendininkini görür. `Komisyon Raporu` (`CommissionReport`): toplam,
  personele/tura göre, tarih/tur/personel filtreleri (`reports.view`).
- `tour_ledger_entries` (income/expense): tur özetinde **"Kasa hareketi"** eylemi + ilişki yöneticisi.
- **Kasa** (`CashReport`): Gelir = kayıtlı yolcu × `price` + ekstra gelir; Gider = araç ücretleri + komisyon + ekstra
  gider; Kalan. Toplamlar `TourDeparture::scopeWithFinanceStats` alt sorgularıyla; filtre/sıralama için
  `CashReport::NET_SQL` (SQLite takma adı ORDER BY/WHERE'de çözmez, `? + 0` metin bağlamayı sayıya çevirir).

## Çalıştırma

```bash
cd ~/Desktop/visit
php artisan serve --port=8010        # site: http://127.0.0.1:8010  panel: /admin
npm run build                        # SCSS/JS/panel teması değişince (public/build depoya dahil)
php vendor/bin/phpunit               # 353 test, hepsi geçiyor (son durum)
```

- **Yerel yönetici:** `admin@example.com` / `password` (süper yönetici). Canlıda zayıf parolayla hesap
  oluşturulmaz (`AdminUserSeeder`).
- Tohum: `AdminUserSeeder`, `ImageSeeder` (site/hero + tur görselleri), `SettingsSeeder` (RTEÜ Geziyor varsayılanları),
  `VehicleSeeder`, `TourSeeder` (6 örnek tur, önümüzdeki hafta sonlarına tarihli; tur varsa dokunmaz),
  `DemoOperationSeeder` (yalnız production değilken, ilk tura örnek gruplar).
- Yerel `visit_tur`'da eski Visit Tur seferleri hâlâ duruyor (Ayder, Uzungöl…): başlık ve fiyatları tur kayıtlarına
  kopyalandı. **1 numaralı tur (Ayder, 25.09.2026) örnek verilerle dolu:** 250 yolcu / 51 grup, 12 araç (plaka,
  şoför, ücret, ilk ikisinde rehber), 4 personel (elif@/kerem@/zeynep@/burak@ornek.test, parola `parola1234`),
  komisyonlar, kasa hareketleri, dağıtım yapılmış. Raporlar bu turla dolu görünür; kullanıcı isterse silinir.

## Teknik yığın

Laravel 12 · PHP 8.2 · Filament 5 (Livewire) · MySQL · Vite 7 · Bootstrap 5 (yalnız reboot/grid/forms/buttons/modal/
offcanvas) + SCSS (site) · Tailwind 4 yalnız panel teması (`resources/css/filament/admin/theme.css`, alt sekme
çubuğu stilleri burada) · Font Awesome 7 (`icons.scss`, engellemeyen yükleme) · font: Plus Jakarta Sans (@fontsource,
800 ağırlığı başlıklar için). Saat dilimi `Europe/Istanbul`. Cache/session/queue `database`.

## Dosya haritası

| Ne | Nerede |
| --- | --- |
| Tur (tarihli gezi) modeli, koltuk + kasa hesapları | `app/Models/TourDeparture.php` |
| Tüm Turlar kaynağı (form/tablo/özet, komisyon & kasa eylemleri) | `app/Filament/Resources/TourDepartures/` |
| Yolcu Ekle / gruplar | `app/Filament/Resources/TourGroups/` |
| Araç Liste Sihirbazı | `app/Filament/Pages/VehicleWizard.php` + `resources/views/filament/pages/vehicle-wizard.blade.php` |
| Araç Geçmişi | `app/Filament/Resources/VehicleHistory/` |
| Kasa / Komisyon Raporu / Kazançlarım | `app/Filament/Pages/{CashReport,CommissionReport,MyEarnings}.php` |
| Personel + yetkiler | `app/Filament/Resources/Users/UserResource.php`, `app/Enums/Permission.php`, `app/Policies/` |
| İletişim talepleri / iş başvuruları | `app/Filament/Resources/{ReservationRequests,JobApplications}/` |
| Dağıtım algoritması (saf) / kaydeden | `app/Services/Allocation/{VehicleAllocator,DepartureAllocator}.php` |
| Dağılım panosu | `.../TourDepartures/Pages/DepartureAllocation.php` + `resources/views/filament/resources/tour-departures/` |
| Yolcu listesi (manifesto) | `app/Http/Controllers/ManifestController.php`, `resources/views/admin/manifest.blade.php` |
| Site denetleyicileri | `HomeController`, `ContactController`, `JobApplicationController` |
| Site görünümleri | `resources/views/{home,contact,job-application,thanks}.blade.php`, `partials/{header,mobile-nav,footer,tour-card,contact-form}` |
| Tema (renkler: lacivert `#0d2544`, güneş sarısı `#ffc530`, gök mavisi `#0d7de0`) | `resources/scss/_variables.scss`, `_theme.scss` |
| Marka | `public/images/brand/{logo,logo-light,logo-mark}.svg`, `icon-512.png`, `public/apple-touch-icon.png` (yer tutucu; Chrome ile SVG'den üretildi) |
| Panel menü/PWA/alt bar | `app/Providers/Filament/AdminPanelProvider.php`, `resources/views/filament/partials/{pwa-head,mobile-tabs}.blade.php` |
| Panel komut listesi | `app/Support/Console/CommandCatalog.php` (SEO komutları kaldırıldı) |
| Site ayarları | `app/Filament/Pages/SiteSettings.php` (marka/iletişim/Instagram/logo, hero, tur bölümü, öne çıkanlar, operasyon, analitik) |
| Göç: sadeleştirme + yetki + komisyon + kasa + iş başvurusu | `database/migrations/2026_09_23_000001_simplify_tours_and_add_finance.php` |
| Örnek tur verisi | `database/seeders/data/tours.php` |
| Kullanıcıya yönelik belge | `README.md` |

## Kullanıcının tasarım tercihleri

- Referans: gönderdiği telefon mockup'ı (lacivert üst bar, sarı GEZİYOR, hero kartı, 2 sütun tur kartları,
  sarı fiyat düğmesi, 4'lü şerit, Instagram bandı). "Hata görmek istemiyorum": JS hatası, yatay taşma, kırık görsel
  bırakma; her değişiklikte telefon genişliğinde kontrol et (scratchpad'de puppeteer QA betiği kalıbı var:
  `/, /iletisim, /is-basvurusu` + panel sayfaları, 390px ve 1440px).
- Panel Filament varsayılan koyu/açık temada; telefonda alt sekme çubuğu şart.

## Barındırma bağlamı (montaj'dan öğrenilenler)

- cPanel + LiteSpeed + CloudLinux alt-php82 + Cloudflare. **Terminal yok.** Dağıtım: cPanel Git Version Control →
  "Update from Remote". `vendor/` elle yüklenir. `public/build` ve `public/{js,css,fonts}/filament` depoya dahil.
- Kapalı PHP fonksiyonları: `proc_open`, `pcntl_*`, `exec`, `symlink`, `escapeshellarg`, `highlight_file` →
  `function_exists` ile koru (`SharedHostingCompatibilityTest`).
- **Zamanlanmış görevler `InProcess::command()` ile** (`routes/console.php`); `Schedule::command()` proc_open
  ister, hostingde hiç çalışmaz. Kuyruk işçisi `App\Support\Queue\SharedHostingWorker`.
- "Önbellekleri oluştur" (`optimize`) taze uygulama başlatır; `CommandRunner::preserveApplicationState` panel
  isteğinin container'ını geri verir. `.env` değişince panelden "Tüm önbellekleri temizle" → "Önbellekleri oluştur".
- "Update from Remote → could not contact the remote repository" çoğu zaman sunucuda elle değiştirilmiş dosyadır.
- MySQL için `DB_HOST=localhost`. cPanel `AddHandler application/x-httpd-alt-php82` satırı eklenmemeli.
- İş başvurusu özgeçmişleri **özel diske** (`storage/app/private/job-applications`) yazılır; indirme
  `admin.job-application.cv` rotası (yetki denetimli).

## Açık işler

1. Git: depo `https://github.com/Migrain-code/visit.git` (`main`). Bu sadeleştirme henüz commit'lenmedi;
   kullanıcı isteyince commit + push (Co-Authored-By satırıyla).
2. Canlıda ilk kurulum yolu: panelde `db:seed` yasak. Seçenek: yerel `visit_tur`'u phpMyAdmin'e aktarmak ya da güvenli
   bir kurulum komutu. Kullanıcıya sor.
3. Gerçek içerik: logo, tur görselleri, fiyatlar, kalkış yeri, Instagram adresi, telefon/WhatsApp, KVKK metni.
4. Canlı `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, DB, `ADMIN_EMAIL/PASSWORD`, `MAIL_*`
   (talep/başvuru bildirimi), isteğe bağlı `RECAPTCHA_*`.
5. Eski tablolar (`tours`, `tour_categories`, `provinces`, `districts`, blog/galeri/SEO) ve `users.role` sütunu
   DB'de duruyor; istenirse ayrı bir göçle düşürülür.

## Bilinen tuzaklar

- **Filament form doldurma:** forma değer vererek `fill()` alan varsayılanlarını devre dışı bırakır →
  `CreateTourGroup::fillForm` ön doldurmaya `status`, `paid_amount`, boş yolcu satırını ekler.
- Repeater'lı testlerde `Repeater::fake()` (`TourOperationsTest`, `VehicleWizardTest`).
- İlişkili repeater (yolcular) verisi `mutateFormDataBeforeCreate`'in `$data`'sına gelmez; ham `$this->data` okunur.
- `tour_groups.contact_name/contact_phone` NOT NULL → `TourGroup::creating` geçici değer yazar, yolcu kaydedilince eşitlenir.
- Grup sayısı yolcu satırlarından türer; test verisinde grup oluştururken gerçek yolcu satırı ekle.
- RichEditor HTML'i normalize eder (`&#039;`, `<li><p>`): testte düz metin karşılaştır.
- Widget'lar `$isLazy = false` (ilk HTML'de gelsin; testler de buna güvenir).
- `TurkishText::upper()` kullan: `mb_strtoupper('Geziyor')` "GEZIYOR" verir.
- Ekran görüntüsü: `puppeteer-core` (scratchpad) + Chrome; panel için login formu `input[type=email]`,
  `input[type=password]`, `button[type=submit]`.
