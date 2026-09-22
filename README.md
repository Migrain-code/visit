# Visit Tur — Tur Sitesi ve Operasyon Paneli

Doğu Karadeniz'de (Rize ve Trabzon çıkışlı) günübirlik turlar düzenleyen bir seyahat acentesi için web sitesi ve yönetim paneli.
Laravel 12, Filament 5 (yönetim paneli), Blade + Bootstrap 5 (ön yüz), MySQL.

İki parçadan oluşur:

1. **Web sitesi** — turlar (Ayder, Uzungöl, Sümela, Pokut, Huser, Zilkale, Batum…), fiyatlar,
   tur takvimi, "{il/ilçe} çıkışlı günübirlik turlar" bölge sayfaları, blog, galeri, rezervasyon
   talep formu.
2. **Operasyon paneli** (`/admin`) — tur kayıtları (seferler), araçlar, gruplar ve yolcular,
   **grupları bölmeden araçlara dağıtma**, yolcu listesi çıktısı, rezervasyon talepleri,
   SEO & yapay zekâ araçları.

> **Yayına almadan önce:** sitedeki turlar, fiyatlar, tarihler ve metinler ÖRNEKTİR.
> Panelden kendi bilgilerinizle değiştirin. Bkz. [Yayına almadan önce](#yayına-almadan-önce).

---

## Kurulum (yerel)

```bash
composer install
npm install
cp .env.example .env          # veritabanı bilgilerini ve ADMIN_PASSWORD'ü yazın
php artisan key:generate
php artisan migrate           # yalnız "migrate": mevcut veriye dokunmaz
php artisan db:seed           # örnek içerik + ilk yönetici (tekrar çalıştırmak güvenlidir)
php artisan storage:link
npm run build
php artisan serve --port=8010
```

- Site: <http://127.0.0.1:8010> · Panel: <http://127.0.0.1:8010/admin>
- İlk yönetici: `.env` içindeki `ADMIN_EMAIL` / `ADMIN_PASSWORD` (yerelde varsayılan
  `admin@example.com` / `password`). **Canlıda zayıf parolayla hesap oluşturulmaz.**
- `db:seed` yerelde örnek seferler ve kurgusal yolcular da ekler (dağıtım panosunu denemek için).
  Canlı ortamda (`APP_ENV=production`) bu örnek operasyon verisi **eklenmez**.

## Operasyon nasıl işler?

```
Araçlar (hazır liste)        Turlar (katalog)
        │                          │
        └──────► Tur Kaydı (sefer: tur + tarih) ◄── Rezervasyon talebi (siteden)
                       │                                    │
                 Araç ata (19, 24, 50 koltuk…)        "Gruba dönüştür"
                       │                                    │
                 Gruplar (2, 3, 5… kişi) ◄──────────────────┘
                       │   her yolcu: ad, soyad, TC, telefon, yaş, cinsiyet
                       ▼
                 Araç Dağılımı  ──►  Yolcu listesi (araç araç, yazdır / PDF)
```

1. **Operasyon → Araçlar:** hazır araç listenizi bir kez tanımlayın (ad + **yolcu** koltuğu
   sayısı; şoför hariç). Örn. 19, 24, 31, 46, 50 koltuk.
2. **Operasyon → Tur Kayıtları → Yeni:** turu ve kalkış tarihini seçin, araçları atayın.
   Aynı araç tipinden birden çok gerekiyorsa ya da rehber için koltuk ayıracaksanız kayıttan
   sonra "Araçlar" bölümünü kullanın. Koltuk sayısı filodan **kopyalanır**: filodaki araç
   sonradan değişse de geçmiş seferin listesi bozulmaz.
3. **Operasyon → Gruplar → Grup kaydet:** seferi seçin, ilgili kişiyi ve yolcuları girin.
   Grubun büyüklüğü yolcu satırı sayısıdır. "Opsiyon" durumunda kimlik bilgileri sonradan
   tamamlanabilir; opsiyon da koltuk tutar, iptal tutmaz.
4. **Tur Kaydı → Araç Dağılımı:**
   - **Bekleyenleri yerleştir** — yerleşmiş gruplara dokunmaz, araçsız grupları boş koltuklara koyar.
   - **Baştan dağıt** — elle sabitlenenler hariç herkesi yeniden, en dolu düzenle yerleştirir.
   - **Taşı** — bir grubu elle bir araca alır ve oraya **sabitler** (kilit simgesi).
   - **Yolcu listesi** — araç araç, imza sütunlu, yazdırılabilir liste.
5. Kalkışa **48 saat** kala araçsız kalan gruplar saat başı **kendiliğinden** yerleştirilir
   (Ayarlar → Site Ayarları → Operasyon sekmesinden süre değiştirilir; `0` kapatır).

### Dağıtım kuralları

**Grup asla bölünmez.** Bir grup ya tamamıyla tek bir araca biner ya da "yerleşmeyi
bekleyenler"de kalır. 19 koltuklu araçta 3 koltuk kaldıysa 4 kişilik grubun 3'ü oraya, 1'i
başka araca konmaz; grup bütün hâlinde başka bir araca gider.

Bu kural üç katmanda korunur:

- **Veri modeli:** araç ataması yolcuda değil **grupta** tutulur (`tour_groups.departure_vehicle_id`).
  Yolcunun araç alanı yoktur; bir grubun iki araca dağılması veritabanında ifade edilemez.
- **Algoritma** (`app/Services/Allocation/VehicleAllocator.php`), öncelik sırasıyla:
  1. en çok yolcuyu yerleştir (mümkünse herkesi);
  2. herkes sığıyorsa **en az araçla**, eşitlikte toplam koltuğu en az olan araçlarla sığdır —
     boş kalan araç panelde "seferden çıkarabilirsiniz" diye gösterilir;
  3. seçilen araçları **liste sırasıyla doldur**: ilk araç olabildiğince tam dolar, boşluk son
     araçta toplanır.
- **Koruyucu kurallar:** grup büyüyüp aracına sığmaz olursa, aracın koltuğu azaltılırsa ya da
  araç seferden çıkarılırsa ilgili gruplar **araçtan çıkarılır** (silinmez) ve yeniden
  dağıtılmayı bekler. Hiçbir koşulda taşan araç ya da bölünmüş grup oluşmaz.

Bir grup hiçbir araca bütün hâlinde sığmıyorsa panel nedenini ve filodan **ek araç önerisini**
gösterir. Algoritma, kaba kuvvetle (tüm olasılıkları deneyerek) karşılaştırılan yüzlerce rastgele
örnekle test edilir: `tests/Unit/VehicleAllocatorTest.php`.

### Yolcu bilgileri

- T.C. kimlik numarası **kontrol basamaklarıyla doğrulanır** (tek hane yanlış yazılırsa yakalanır).
- Aynı kişi aynı sefere iki kez kaydedilemez (başka grupta olsa da).
- Yabancı uyruklu yolcuda TC yerine pasaport numarası alınır.
- Liste ekranlarında kimlik **maskelenir** (`100******46`); tam numara yalnız grubun içinde ve
  yolcu listesinde görünür. Yolcu listesi arama motorlarına kapalıdır ve önbelleğe alınmaz.

## Roller ve yetkiler

| Rol | Ne yapar |
| --- | --- |
| **Süper Yönetici** | Her şey: kullanıcılar, ayarlar, sistem komutları dahil. |
| **Operasyon Sorumlusu** | Tur kaydı açar, araç atar, grupları dağıtır, turları ve fiyatları yönetir, talepleri personele atar. |
| **Kayıt Personeli** | Grup ve yolcu kaydı girer/günceller, rezervasyon taleplerini işler. Araç ve sefer tanımlayamaz; yalnız **kendi girdiği** grubu silebilir. |
| **İçerik Editörü** | Tur sayfaları, blog, galeri, bölgeler, SEO raporları. **Yolcu verisini göremez.** |
| **Rehber** | Yalnız **rehberi olduğu** seferleri, o seferlerin gruplarını ve yolcu listesini görür; değiştiremez. |

Rolü atanmayan yeni hesap en dar yetkiyle (Rehber) açılır. Yetkiler `app/Policies` altındadır;
her rol için sayfa erişim matrisi `tests/Feature/RolePermissionTest.php` içinde sınanır.
Personel eklemek: **Ayarlar → Personel**.

## Web sitesi

| Adres | İçerik |
| --- | --- |
| `/` | Ana sayfa: öne çıkan turlar, yaklaşan seferler, kalkış noktaları |
| `/turlar`, `/turlar/{kategori}` | Tur listesi (süre filtresi: `?sure=gunubirlik\|kisa\|uzun`) |
| `/{tur-slug}` | Tur sayfası: program, fiyat, dahil/hariç, açık tarihler, SSS |
| `/tur-takvimi` | Satışa açık tüm seferler, tarihe göre; kalan koltuk uyarısı |
| `/bolgeler`, `/{il}`, `/{il}/{ilce}` | "… çıkışlı turlar" bölgesel SEO sayfaları |
| `/rezervasyon` | Rezervasyon talep formu (`?tur=slug&sefer=id` ile ön seçim) |
| `/blog`, `/galeri`, `/hakkimizda`, `/sss`, `/iletisim` | İçerik sayfaları |
| `/sitemap.xml`, `/robots.txt`, `/llms.txt` | Arama motorları ve yapay zekâ ajanları için |

- **Rezervasyon formu koltuk ayırmaz.** Bir ön taleptir; personel müşteriyi arar ve talebi
  panelde **"Gruba dönüştür"** ile kayda çevirir (form talepteki bilgilerle ön dolu açılır).
- Takvimde yalnız **"Kayıt açık"**, **sitede görünür** ve **tarihi geçmemiş** seferler listelenir.
  Koltuk azaldığında "Son 7 koltuk", dolduğunda "Doldu" yazar.
- Tur programları, bölgede fiilen satılan günübirlik turların (Ayder, Uzungöl, Sümela, Pokut,
  Huser, Zilkale, Batum) yaygın güzergâhlarına göre yazıldı; **fiyatlar ve saatler örnektir**.
- Telefon / WhatsApp numarası kodda yoktur: **Ayarlar → Site Ayarları**'ndan girilir (boşsa
  `.env` içindeki `SITE_PHONE`, `SITE_WHATSAPP` kullanılır).
- Yüklenen görseller otomatik WebP'ye çevrilir; kartlar için küçük boyutlar kendiliğinden üretilir.
- reCAPTCHA isteğe bağlıdır: `.env` içine `RECAPTCHA_SITE_KEY` ve `RECAPTCHA_SECRET_KEY`
  yazılırsa form korunur; boşsa form olduğu gibi çalışır.

## Yayına almadan önce

- [ ] **Ayarlar → Site Ayarları:** firma adı, telefon, WhatsApp, e-posta, adres,
      **TÜRSAB belge numarası ve ticari unvan** (sitede gösterilmesi yasal zorunluluktur).
- [ ] **Turlar:** örnek program ve **fiyatları** kendi turlarınızla değiştirin ya da silin.
- [ ] **Bölgeler → İlçeler:** Rize'nin 12 ve Trabzon'un 18 ilçesi hazır; her ilçenin **biniş
      noktalarını** (otel önü, meydan, otogar…) girin (bilerek boş bırakıldı). Hizmet vermediğiniz
      ilçeyi yayından kaldırın.
- [ ] **Sayfalar:** "KVKK Aydınlatma Metni" ve "Tur Sözleşmesi ve İptal Koşulları" şablondur;
      kendi unvan ve koşullarınıza göre düzenleyin.
- [ ] **Operasyon → Araçlar:** kendi araçlarınızı tanımlayın.
- [ ] Logo: `public/images/brand/logo.svg` yer tutucudur; **Site Ayarları → Logo**'dan kendinizinkini yükleyin.
- [ ] Görseller örnek (Wikimedia Commons, CC0 / kamu malı; kaynaklar
      `database/seeders/images/CREDITS.md`). Kendi tur fotoğraflarınızı yükleyin — özellikle
      Ayder, Pokut ve Batum için gerçek fotoğraflarınız çok daha iyi görünür.

## Yayına alma (cPanel, terminalsiz)

`public/build` ve Filament'in `public/js|css|fonts/filament` dosyaları **bilerek depoya
dahildir**: sunucuda terminal olmadığı için `git pull` tek başına eksiksiz bir dağıtım olmalıdır.
(Derlenmiş dosyalar kodla ayrı ayrı yüklenirse uyumsuz kalabiliyor: ikonlar kaybolur, form bozulur.)

1. Yerelde: `npm run build` → değişiklikleri commit'leyip GitHub'a gönderin.
2. cPanel → **Git Version Control** → *Update from Remote*.
3. `vendor/` klasörü depoda yoktur: ilk kurulumda ve `composer.lock` her değiştiğinde yerelde
   `composer install --no-dev --optimize-autoloader` çalıştırıp `vendor/` klasörünü yükleyin.
4. `.env` dosyasını sunucuda oluşturun: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`,
   veritabanı (`DB_HOST=localhost`), `ADMIN_EMAIL`, güçlü bir `ADMIN_PASSWORD`.
5. Panel → **Ayarlar → Sistem Komutları**: "Veritabanını güncelle" → "Görsel bağlantısını kur"
   → "Önbellekleri oluştur". Sayfanın üstündeki commit kodu GitHub'daki son commit ile aynı olmalıdır.

   > **İlk kurulumda dikkat:** panelde örnek içerik / ilk yönetici oluşturan bir komut **yoktur**
   > (`db:seed` güvenlik için panelde yasaktır). İlk yönetici hesabı ve örnek içerik için ya yerel
   > veritabanını dışa aktarıp phpMyAdmin'den içe aktarın ya da güvenli bir kurulum komutu ekleyin.
   > Bu karar henüz verilmedi.
6. cPanel → **Cron Jobs** — Sistem Komutları sayfası size hazır satırları verir:
   - her dakika `schedule:run` (SEO görevleri, site haritası, **otomatik araç yerleştirme**),
   - her dakika kuyruk işçisi (`queue:work --stop-when-empty`).

Paylaşımlı hostinglerde `exec`, `symlink`, `escapeshellarg` gibi fonksiyonlar kapalı olabilir;
kod bunları kullanmaz (`tests/Feature/SharedHostingCompatibilityTest.php` bunu sınar).

## Geliştirme

```bash
npm run dev                         # Vite (canlı yenileme)
php artisan test                    # tüm testler — yalnız bellek içi SQLite üzerinde
php vendor/bin/phpunit tests/Unit/VehicleAllocatorTest.php
php artisan tours:allocate-upcoming # kalkışı yaklaşan seferleri elle yerleştir
```

| Ne | Nerede |
| --- | --- |
| Dağıtım algoritması (saf, veritabanı bilmez) | `app/Services/Allocation/VehicleAllocator.php` |
| Dağıtımı kaydeden servis, elle taşıma, araç önerisi | `app/Services/Allocation/DepartureAllocator.php` |
| Dağılım panosu | `app/Filament/Resources/TourDepartures/Pages/DepartureAllocation.php` |
| Grup + yolcu formu, TC doğrulama | `app/Filament/Resources/TourGroups/`, `app/Rules/TcKimlikNo.php` |
| Yolcu listesi çıktısı | `app/Http/Controllers/ManifestController.php`, `resources/views/admin/manifest.blade.php` |
| Ön yüz teması | `resources/scss/_variables.scss`, `resources/scss/_theme.scss` |
| Örnek içerik | `database/seeders/data/tours.php`, `database/seeders/data/regions.php` |

---

# SEO & AI Yönetim Sistemi

`seo_sistemi_promptu.md` dosyasındaki 9 modülün tamamı kurulmuştur. AI yalnız içerik üretimi,
meta üretimi ve SSS üretiminde kullanılır. **Skorlama, çakışma tespiti, yönlendirme önerisi ve iç
link önerisi deterministiktir** — ücretsiz, tekrarlanabilir ve test edilebilir.

## Modüller

| # | Modül | Nerede yönetilir | AI kullanır mı |
| --- | --- | --- | --- |
| 1 | Kelime ve hedef sahipliği | SEO & AI → Anahtar Kelimeler / Hedef Sayfalar | Hayır |
| 2 | İçerik SEO skoru | SEO & AI → SEO Gösterge Paneli | Hayır |
| 3 | AI içerik üretim hattı | Blog → Blog Yazıları → "AI ile konu üret" | Evet |
| 4 | Çakışma koruması (üretim anı) | Otomatik, üretimle birlikte çalışır | Hayır |
| 5 | Çakışma temizleyici (geçmiş) | SEO & AI → Çakışan İçerikler | Hayır |
| 6 | İç link motoru | SEO & AI → İç Link Önerileri / Kuralları | Hayır |
| 7 | Search Console katmanı | SEO & AI → Ayarlar → Search Console | Hayır |
| 8 | Yönlendirme ve 404 | Gözlem → 404 Kayıtları / Yönlendirmeler | Hayır |
| 9 | AI ajan keşfi | `/llms.txt`, `/robots.txt`, Link başlıkları | Hayır |

## Kurulum

### 1. OpenRouter anahtarı

```dotenv
AI_API_KEY=sk-or-v1-...
AI_BASE_URL=https://openrouter.ai/api/v1
AI_MODEL=openai/gpt-4o-mini
```

Anahtarı https://openrouter.ai/keys adresinden alın. Model listesi panelde
**SEO & AI → Ayarlar → AI sağlayıcı** sekmesinde OpenRouter'dan canlı çekilir; oradan seçebilirsiniz.

Anahtar girilmeden sistem çalışmaya devam eder: AI aksiyonları panelde **gizlenir**, hata vermez.

### 2. Zamanlanmış görevler

Sunucuda tek bir cron satırı yeterlidir:

```
* * * * * cd /proje/yolu && php artisan schedule:run >> /dev/null 2>&1
```

Yazı üretimi kuyrukta koştuğu için bir kuyruk işçisi de gerekir:

```
php artisan queue:work --tries=2 --timeout=600
```

### 3. Google Search Console (isteğe bağlı)

1. Google Cloud'da bir servis hesabı oluşturup JSON anahtarını indirin.
2. Dosyayı `storage/app/private/` altına koyun.
3. Panelde **SEO & AI → Ayarlar → Search Console** bölümüne dosya adını ve mülk adresini girin.
4. Search Console'da servis hesabının e-postasını mülke **kullanıcı** olarak ekleyin.

> **403 "You do not own this site" alırsanız** üç sebebi olabilir: servis hesabı mülke eklenmemiştir;
> mülk tipi uyuşmuyordur (`sc-domain:ornek.com` ile `https://ornek.com/` farklı mülklerdir ve
> ayardaki değer GSC'dekiyle **birebir** aynı olmalıdır); ya da incelenen adres mülkün dışındadır.
> URL Inspection için **"Tam"** yetki yeterlidir. Indexing API için **"Sahip"** gerekir ve sahiplik
> ayrı bir ekrandan verilir; kullanıcı ekleme penceresinde "Sahip" seçeneği çıkmaz.

## Zamanlama

Sıra önemlidir: önce veri çekilir, sonra ondan türetilen işler koşar.

| Saat | Görev | Komut |
| --- | --- | --- |
| 01:00 | Site haritası önbelleği | `sitemap:generate` |
| 01:10 | llms.txt üretimi | `seo:discovery` |
| 01:50 | Hedef sayfa senkronu | `seo:sync-targets` |
| 02:00 | SEO skorlama | `seo:score` |
| 03:00 | Google indeks kontrolü | `seo:check-index` |
| 04:00 | Blog konusu üretimi | `blog:generate` |
| 05:00 | Search Console performansı | `seo:sync-search-console` |
| 05:10 | Kelime sıralama geçmişi | `seo:sync-rankings` |
| 05:20 (pazartesi) | Yeni kelime keşfi | `seo:discover-keywords` |
| 05:30 (pazartesi) | Düşük CTR meta tazeleme | `seo:refresh-meta` |
| 05:40 | Yönlendirme önerisi | `seo:suggest-redirects` |
| 05:50 | İç linkleri işle | `links:apply` |
| her dakika | Zamanı gelen yazıları yayınla | `blog:publish-due` |

Yayın kararı dakika hassasiyetinde olduğu için o görev her dakika koşar; 5 dakikada bir koşturmak
kararı 5 dakika geciktirirdi.

## Blog üretim hattı nasıl çalışır?

```
günlük komut → kategori seç → AI konu üret → FİLTRE 1 → FİLTRE 2 → kuyruğa al
                                                                      ↓
                              yayın zamanı gelince yayınla ← taslak ← AI yazı üret
```

**Filtre 1 — başlık benzerliği.** Tam eşleşme yeterli değildir: tek ek veya tek kelime farkı
tekrar yazıyı kaçırır. Başlıklar Türkçeye özgü normalleştirmeden geçirilir (büyük İ/I elle eşlenir),
durak kelimeler atılır, her kelime ilk 6 harfe indirilir ve Jaccard benzerliği hesaplanır.
Eşik varsayılan **0.55**'tir.

*Bilinen sınır:* kökü 6 harften kısa kelimelerde ek katlanmaz ("rayı" ile "raylar" ayrı sayılır).
Uzun köklerde algoritma doğru çalışır. Kısa köklü tekrarlar **Çakışan İçerikler** ekranında elle görülür.

**Filtre 2 — kelime çakışması.** Adayın ana anahtar kelimesini zaten hedefleyen içerik varsa konu
reddedilir. Bu kontrol uyarı üretip geçmez; **engeller**.

Reddedilen her aday gerekçesiyle kaydedilir. Hiçbir konu üretilemezse sistem sessizce durmaz;
boştaki kelime sayısını, açık kategori sayısını ve nereye bakılacağını söyler.

## Çakışan içerikleri temizleme

İki ayrı eşik kullanılır ve bu **bilerek** böyledir:

- **Tarama eşiği (0.55):** "çakışma olabilir" → ekranda listelenir, buton çıkmaz
- **Birleştirme eşiği (0.90):** "pratikte aynı yazı" → tek tuşla birleştirilebilir

Aradaki çiftler elle bırakılır. %67 benzeyen iki yazı farklı konular olabilir; otomatik birleştirmek
içerik silmek demektir.

Birleştirme yapıldığında:

1. Kaybeden yazıdan kazanana **301** yönlendirme oluşturulur
2. Kaybeden **yayından kaldırılır** — asla silinmez
3. Her ikisi tek transaction içinde yapılır; yönlendirme yazılamazsa yazı da yayında kalır

Kazananı **veri seçer**: tıklama → gösterim → eski yayın tarihi → kısa slug.
Hedef yayında değilse birleştirme reddedilir. İşlem idempotenttir ve **geri alınabilir**.

## İç link motoru

Varsayılan olarak **kapalıdır**. Açmadan önce kuralları gözden geçirin.

- Link yalnız düz metne basılır; başlık, mevcut bağlantı, `code` ve `pre` içine asla girilmez
- Tavanlar her istekte kontrol edilir: makale başına, toplam ve anasayfa için ayrı
- Motor kelime uydurmaz; anchor metinleri yalnız gerçek hedef sayfalardan gelir
- AI gövdeye link gömmez; link basmanın tek sahibi bu motordur
- Reddedilen bir öneri (yazı + hedef + anchor) bir daha gösterilmez

## Otomasyon günlüğü

Otomasyonun izi `storage/logs/automation-YYYY-MM-DD.log` dosyasında, ana uygulama log'undan **ayrı**
tutulur. Her dakika koşan bir görev ana log'a yazsaydı gerçek hataları gömerdi.

Her tur bir satır yazar, değişiklik olmasa bile: "hiç koşmadı" ile "koştu ama atladı" ayrımı
teşhisin yarısıdır. Rutin sonuçlar `debug`, değişiklik ve hatalar `info` seviyesindedir; bir hata
varsa yanındaki rutin sebepler onu susturmaz.

Gürültüden kurtulmak için:

```dotenv
AUTOMATION_LOG_LEVEL=info
```

"Bugün blog neden üretilmedi?" sorusu tek komutla yanıtlanır:

```bash
grep "blog.generate" storage/logs/automation-*.log
```

## Aktif bölge kuralı

Sistem yalnız **erişilebilir** sayfalarla ilgilenir. Bir ilçe sayfasının erişilebilir sayılması için
**hem ilçenin hem de bağlı olduğu ilin** yayında olması gerekir; ili kapalı bir ilçe adresi 404 döner.

Bu kural her yerde geçerlidir:

- **Skorlama** yalnız erişilebilir sayfaları tarar. Kapatılan bir sayfanın eski skoru silinir, böylece
  gösterge panelinde var olmayan sayfalar "hedefin altında" görünmeye devam etmez.
- **Bölge anahtar kelimeleri** yalnız erişilebilir bölgeler için üretilir. Bölge kapanırsa kelimeler
  silinmez, pasife alınır; bölge tekrar açılınca kendiliğinden geri gelir.
- **İç link kuralları** yalnız erişilebilir sayfaları hedefler. Kapanan sayfanın kuralı pasife alınır.
- **İçerik zenginleştirme** kapalı sayfaya AI çağrısı harcamaz.

## Anahtar kelime sahipliği

| Kelime tipi | Örnek | Sahibi |
| --- | --- | --- |
| Bölge ticari | `rize günübirlik turlar`, `ardeşen çıkışlı turlar` | İl / ilçe sayfası |
| Tur ticari | `ayder yaylası turu fiyatları` | Tur sayfası |
| Bölge + tur | `ardeşen çıkışlı ayder yaylası turu` | Sahipsiz — blog hattının hedefi |
| Bilgi amaçlı | `ayder yaylasında ne yapılır` | Sahipsiz — blog hattının hedefi |

Ticari kelimeler bilerek tur ve bölge sayfalarına atanır. Sahipsiz bırakılsalardı blog üretim
hattının havuzuna düşer ve blog, para kazandıran sayfayla aynı kelimeyi hedefleyerek onu yerdi.

## İç link motoru ve Türkçe

Türkçe eklemeli bir dildir: metinde "Ayder Yaylası Turu" değil "Ayder Yaylası Tur**unun**" geçer.
Motor, anchor metninden sonra gelen **çekim eklerini** (hâl, iyelik, bağlantı) kabul eder ama
**yapım eklerini** kabul etmez. Yani "turunu" linklenir, "tur**cu**" linklenmez.

Motor idempotenttir: her gün koşsa bile mevcut linkleri bütçeye sayar ve üstüne link yığmaz.

## İçerik üretim takvimi

```bash
# Bir aylık stok üret (her güne bir yazı, taslak olarak)
php artisan blog:bulk --days=30

# Önce konuları gör, yazı üretme
php artisan blog:bulk --days=7 --dry-run
```

Yazılar **taslak** olarak kaydedilir ve `publish_at` alanına bir tarih verilir. `blog:publish-due`
her dakika koşarak zamanı geleni yayınlar.

Günlük `blog:generate` komutu **stoku tamamlar, üstüne yığmaz**: ileriye dönük üç günden fazla
planlanmış taslak varsa üretim yapmaz ve bunu günlüğe yazar. Stok azalınca kendiliğinden devam eder.

Üretilen her yazının uzunluğu **kod tarafında** denetlenir. Model istenen uzunluğun belirgin
altında bir metin döndürürse bir genişletme turu yapılır; mevcut bölümler korunur, üzerine yeni
bölüm eklenir.

## Yeni komutlar

| Komut | Ne yapar |
| --- | --- |
| `seo:location-keywords` | Aktif il/ilçeler için bölge adı içeren kelimeler üretir |
| `seo:enrich` | Hedef skorun altındaki yayındaki sayfaları AI ile genişletir |
| `blog:bulk --days=30` | Bir dönemlik blog stoğu üretir |
| `seo:score --all` | Yayında olmayanları da skorlar (varsayılan: yalnız yayındakiler) |
| `links:apply --type=district` | Yalnız belirli içerik tipine link işler |

## Model seçimi

Varsayılan `openai/gpt-4o-mini` ucuzdur ama kısa yazma eğilimindedir; sistem bunu genişletme turuyla
telafi eder. Daha uzun ve derin içerik için panelden daha güçlü bir model seçebilirsiniz
(**SEO & AI → Ayarlar → AI sağlayıcı**). Model listesi OpenRouter'dan canlı çekilir.

## Kırmızı çizgiler

Sistemde uygulanan, değiştirilmemesi gereken kurallar:

1. **Uydurma yasak.** Hiçbir metrik, müşteri, yorum veya rakam uydurulmaz. AI çıktısı kod tarafında
   sınırlanır: slug ASCII'ye indirilir, meta başlık 60, meta açıklama 155 karakterde kesilir,
   gövdeden bağlantılar ve `script` sökülür. Modele güvenilmez.
2. **API anahtarı** log'a, veritabanına, URL'ye veya hata mesajına yazılmaz.
3. **Loglama isteği bozmaz**, ama sessizce de yutulmaz; kanal yoksa varsayılana düşülür.
4. **Sorun metinleri sabittir.** Gösterge panelindeki sayımlar tam metinle eşleşir.
5. **Kimlik yoksa çökmez.** Google kimliği yoksa GSC aksiyonları, AI anahtarı yoksa AI aksiyonları gizlenir.
6. **Yönlendirme yalnız 404'te** çalışır; geçerli adreslere maliyeti sıfırdır.
7. **Keşif dosyaları cron'da** üretilir, istek anında değil.
8. **İçerik silinmez.** Yayından kaldırılır ve yönlendirilir.
9. **Geri alınamaz işlem yoktur.** Birleştirme geri alınabilir, pasife alınan hedefler silinmez.

## Personelin sitede görünmesi

**Ayarlar → Personel** ekranında bir kişiye telefon girip **"Web sitesinde göster"** anahtarını açın.
O kişi iletişim sayfasındaki ve ana sayfadaki ekip listesinde yer alır; ziyaretçiler doğrudan onun
numarasına yönlendirilir (tıkla-ara ve WhatsApp).

Kurallar:

- Telefonu olmayan kişi listelenmez — tıklanacak bir şey olmadan kart göstermek ziyaretçiyi çıkmaza sokar.
- WhatsApp alanı boşsa telefon numarası kullanılır.
- Fotoğraf yoksa baş harfleri gösterilir.
- **E-posta ve rol asla siteye basılmaz**; panel hesabı bilgileri herkese açık sayfaya sızmaz.

## Analitik paneli

**SEO & AI → Analitik** ekranı tek sayfada şunları verir:

- **Sayı kutuları:** rezervasyon talebi (son 30 gün, önceki döneme göre değişimle), yayındaki ve planlı
  blog sayısı, AI bot ziyareti, ortalama SEO skoru, çözülmemiş 404.
- **Rezervasyon talepleri grafiği:** son 30 günün günlük seyri. Sitenin ana dönüşüm ölçüsüdür.
- **Yapay zeka botları:** bot başına toplu kart (aşağıda).
- **İçerik tipine göre SEO skoru:** yatay çubuk, rakam çubuğun yanında yazılı.
- **Yaklaşan blog yazıları:** planlanmış taslakların takvimi.
- **Google araması:** Search Console bağlıysa tıklama, gösterim, ortalama sıra ve en çok tıklanan
  aramalar. Bağlı değilse bunu açıkça söyler ve anahtar yükleme ekranına bağlantı verir.
- **Bulunamayan adresler:** en çok istenen 404'ler.

Veri yoksa her bölüm ne yapılması gerektiğini söyleyen bir boş durum gösterir; boş eksen çizmez.

### Grafik renkleri

`app/Support/ChartPalette.php` içindeki palet, renk körlüğü ayrımı ve yüzey kontrastı için
doğrulanmıştır. Renkler kimliğe göre **sabit sırayla** atanır, döngüye sokulmaz. Rakamlar her zaman
çubuğun yanında yazılıdır; bilgi asla yalnız renkle taşınmaz.

Palet değiştirilecekse doğrulama yeniden çalıştırılmalıdır; gelişigüzel renk seçmeyin.

## AI bot ziyaretleri

Ham kayıt (bot + adres) okunmaz: tek bir bot onlarca satıra dağılır. Bu yüzden ziyaretler
**bot başına tek kartta** toplanır:

- toplam ziyaret ve genel içindeki payı
- kaç farklı adres okunduğu
- en çok okunan 3 adres
- hatalı istek sayısı (404 vb.)
- son ziyaret zamanı

Kartlar hem **Analitik** ekranında hem de **Gözlem → AI Bot Ziyaretleri** sayfasının üstünde
görünür; ham kayıtlar aynı sayfadaki tabloda kalır.

## Google Search Console anahtarını yükleme

Artık dosyayı elle klasöre koymanıza gerek yok. **SEO & AI → Ayarlar → Search Console** sekmesinde:

1. Google Cloud → **IAM ve Yönetici → Hizmet Hesapları → Anahtarlar → Anahtar ekle → JSON**
2. İnen dosyayı yükleme alanına sürükleyin, **Kaydet**'e basın.

Dosya `storage/app/private` altına yazılır; bu klasör web sunucusundan **erişilemez**. Yükleme
kabul edilmeden önce doğrulanır: geçerli JSON mu, türü `service_account` mı, özel anahtar var mı.
Geçersiz dosya diskten silinir ve eski ayar korunur — bozuk bir yolu kaydetmek her Search Console
çağrısının sessizce düşmesine yol açardı.

Kaydettikten sonra servis hesabının e-posta adresi ekranda görünür. Onu Search Console'da
**Ayarlar → Kullanıcılar ve izinler** bölümünden mülkünüze ekleyin.


## Veritabanı hakkında uyarı

Bu proje canlı içerik ve **yolcu kişisel verisi** barındırır. **`migrate:fresh`, `migrate:refresh`,
`migrate:reset` ve `db:wipe` çalıştırmayın**; bu komutlar bağlı veritabanındaki tüm tabloları siler
(panelde de yasaklıdır). Yeni migrasyonlar için yalnız `php artisan migrate` kullanın. Seeder'lar
idempotenttir (`firstOrCreate`): tekrar çalıştırmak var olan kayıtların üzerine yazmaz.

Testler yalnız bellek içi SQLite üzerinde koşar. `tests/TestCase.php` içindeki koruma, bağlantı
farklıysa testleri veritabanına dokunmadan durdurur.
