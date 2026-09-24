# RTEÜ Geziyor

Rize çıkışlı öğrenci turları için tek sayfalık web sitesi ve telefondan kullanılan operasyon paneli.

## Site

- **Ana sayfa:** üst görsel, haftalık tur kartları (tıklayınca detay + "Katılmak İstiyorum"), öne çıkanlar, Instagram bandı.
- **İletişim (`/iletisim`):** form → panelde "İletişim Talepleri". Kart üzerinden gelindiğinde tur seçili gelir.
- **İş Başvurusu (`/is-basvurusu`):** form + özgeçmiş (PDF/Word) → panelde "İş Başvuruları".

Turların sırası, fiyatı, görseli ve metinleri panelden yönetilir; site başka içerik taşımaz.

## Panel (`/admin`)

Telefonda alt sekme çubuğu vardır ve "ana ekrana ekle" ile uygulama gibi açılır.

| Menü | Ne yapar |
| --- | --- |
| **Tüm Turlar** | Tarihli tur kayıtları. Sıralama düğmesiyle sitedeki sıra değişir. Tur özetinde "Komisyon ekle", "Kasa hareketi", "Yolcu listesi". |
| **Yolcu Ekle** | Turu seç, yolcuları alt alta gir (ad, soyad, TC, telefon, nereden binecek, cinsiyet, not), kaydet → "Grup 1", "Grup 2"… |
| **Gruplar / Yolcular** | Kayıtlı gruplar ve yolcu arama. |
| **Araç Liste Sihirbazı** | Turu seç → araçları ekle (plaka, şoför, ücret, rehber, not) → kaydet → "Grupları yerleştir". Boş koltuk özeti. |
| **Araçlar / Araç Geçmişi** | Filo ve hangi aracın hangi tura gittiği. |
| **İletişim Talepleri / İş Başvuruları** | Siteden gelenler; durum ve personel ataması. |
| **Personel** | Hesaplar ve yetki kutuları. Yetkisiz hesap yalnız rehberi olduğu turları ve kendi kazancını görür. |
| **Kazançlarım** | Personelin kendi komisyonları. |
| **Kasa** | Tur başına gelir (yolcu × fiyat + ekstra), gider (araç + komisyon + ekstra) ve kalan; tarih/tur/personel/kazanç filtreleri. |
| **Komisyon Raporu** | Toplam ödenen, tura ve personele göre. |
| **Site Ayarları / Sistem Komutları** | Marka, iletişim, Instagram, hero metinleri; sunucu komutları. |

**Kural:** bir grup asla iki araca bölünmez; araç taşmaz. Kalkışa 48 saat kala bekleyen gruplar otomatik yerleşir.

## Kurulum (yerel)

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate   # DB: visit_tur
php artisan migrate && php artisan db:seed
php artisan storage:link && php artisan filament:upgrade
npm run build
php artisan serve --port=8010
```

Yönetici: `admin@example.com` / `password` (yalnız yerelde). Testler: `php vendor/bin/phpunit`.

## Canlı (cPanel, terminalsiz)

Kod GitHub'dan "Update from Remote" ile çekilir; `vendor/` elle yüklenir; `public/build` depoya dahildir.
`.env`'de `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, DB, `ADMIN_EMAIL`/`ADMIN_PASSWORD`, `MAIL_*`.
Cron satırlarını panelde Sistem Komutları sayfası verir (`schedule:run` ve kuyruk işçisi, her dakika).
