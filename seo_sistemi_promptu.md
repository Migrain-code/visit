# SEO & AI Yönetimi Sistemi — Kurulum Prompt'u

> Bu dosya bir **prompt/brief**'tir. Başka bir projede bu sistemi kurdurmak için bir AI
> agent'a ver. Spec, canlıda çalışan bir Laravel/Filament implementasyonundan damıtıldı;
> teknoloji-bağımsız yazıldı, agent kendi stack'ine uyarlar.
>
> **Bu dosya `SEO-AI-MODULU-NODEJS-PORT-PROMPT.md`'nin yerini alır.** O dosya 11 özelliği
> anlatıyordu ve Node.js'e özeldi; bu dosya sistemin TAMAMINI kapsar — blog üretim hattı,
> çakışma koruması, iç link motoru ve kelime sahiplik modeli orada yoktu.
>
> **§7 (Tuzaklar) bu dosyanın en değerli kısmı.** Oradaki her madde canlıda gerçekten
> yaşanmış bir hatadır; ölçülmüş sayılarla birlikte verildi. Spec'i atlarsan yeniden
> keşfedersin.

---

## 0) AGENT'A GÖREV

Bir kurumsal web sitesinin (içerik + blog yayınlayan herhangi bir site) SEO ve AI
otomasyonunu yöneten bir modül kuracaksın.

**İLK ADIM — kod yazma, önce stack'i öğren.** Kullanıcıya sor:

- Web framework + dil?
- DB + ORM? Migration aracı?
- Kuyruk var mı (uzun AI çağrıları kuyruğa alınmalı)?
- Zamanlayıcı (cron / scheduler) var mı, nasıl koşuyor?
- Admin paneli ne? Mevcut tablo/form/rozet desenleri?
- **Mevcut içerik modelleri neler?** (Blog, Sayfa, Ürün, Kategori...) Yoksa onları da mı kuracağız?
- Site kaç dilli? İçerik dili ne?
- Google Search Console mülkü var mı, tipi ne (`sc-domain:` mi `https://` mi)?

**Sonra:** §8'deki faz sırasıyla kur. Her fazı bitirince kullanıcıya göster, onay al.

**Kurmadan önce oku:** §7 Tuzaklar. O bölümdeki kararlar spec'in parçasıdır, öneri değil.

---

## 1) SİSTEM HARİTASI — 9 modül

| # | Modül | Özü | AI kullanır mı |
|---|---|---|---|
| 1 | Kelime & hedef sahipliği | Her kelimenin TEK sahibi olur | Hayır |
| 2 | İçerik SEO skoru | Deterministik 0-100 puan + sorun listesi | **Hayır** |
| 3 | AI içerik üretim hattı | Konu üret → yazı üret → taslak → zamanlı yayın | Evet |
| 4 | Çakışma koruması (üretim anı) | Tekrar konuyu üretmeden ENGELLE | Hayır |
| 5 | Çakışma temizleyici (geçmiş) | Yayınlanmış tekrarları birleştir | Hayır |
| 6 | İç link motoru | Kural + öneri + tavan, render anında uygula | Hayır (öneri deterministik) |
| 7 | Search Console katmanı | Performans, sıralama geçmişi, indeks kontrolü | Hayır |
| 8 | Yönlendirme + 404 | 404'leri yakala, 301 öner/uygula | Hayır (string benzerliği) |
| 9 | AI ajan keşfi | llms.txt, schema.org, Link başlıkları | Hayır |

**AI yalnız şurada kullanılır:** içerik/konu üretimi, meta üretimi, FAQ üretimi, analitik
rapor yorumu. Skorlama, çakışma tespiti, yönlendirme önerisi, iç link önerisi — hepsi
**deterministik**. Sebebi: ücretsiz, tekrarlanabilir, cron'da güvenli, test edilebilir.

---

## 2) VERİ MODELİ

Tablo adları örnektir; kullanıcının konvansiyonuna uyarla.

### Çekirdek

| Tablo | Amaç | Kritik alanlar |
|---|---|---|
| `seo_keywords` | Kelime havuzu + sahiplik | `keyword`, `search_intent`, `keyword_type`, `priority`, `owner_blog_id`, `target_id`, `assignment_status`, `slot`, `status` |
| `seo_targets` | Hedef sayfalar (ticari) | `name`, `url`, `target_type`, `status`, `content_type`, `content_id` |
| `seo_analyses` | İçerik başına skor | `content_type`, `content_id`, `score`, `scores` (JSON), `issues` (JSON), `analyzed_at`, `google_index_status`, `google_index_detail` |
| `ai_generations` | AI işlem denetimi | `operation`, `content_type`, `content_id`, `batch_key`, `model`, `status`, `input` (JSON), `output`, `error` |

### Search Console

| Tablo | Amaç |
|---|---|
| `seo_search_queries` | GSC ham satırları. `dimension` = `query` \| `page`; `path`, `clicks`, `impressions`, `ctr`, `position`, `period_start/end`, `row_hash` (tekilleştirme) |
| `seo_rank_history` | Kelime başına günlük sıralama. `keyword_id`, `data_date`, `position_avg`, `clicks`, `impressions`, `ranking_url`, `target_match` |

### İç link

| Tablo | Amaç |
|---|---|
| `internal_link_rules` | Canlı kurallar: `anchor_text`, `target_url`, `priority`, `max_per_article`, `scope_type`, `is_active` |
| `internal_link_registry` | Hedef başına anchor havuzu + sahiplik: `target_url`, `target_hash`, `owner_keyword`, `anchor_pool`, `status` |
| `internal_link_suggestion_rejects` | Reddedilen öneri bir daha çıkmasın: `blog_post_id`, `target_url`, `anchor_text`, `row_hash` |

### Yönlendirme / gözlem

| Tablo | Amaç |
|---|---|
| `redirects` | `from_path`, `to_path`, `status_code`, `is_active`, `source`, `hits`, **`from_hash`** (= `md5(normalize(from_path))`, unique) |
| `not_found_logs` | `path`, `path_hash`, `hits`, `last_referrer`, `resolved` |
| `ai_crawler_visits` | AI botlarının ziyaretleri: `bot`, `path`, `key_hash`, `hits`, `last_status` |

### İçerik (muhtemelen zaten var)

`blogs` (`title`, `slug`, `category_id`, `meta_title`, `meta_description`, `status`,
`publish_at`), `blog_categories`, `pages`, ürün/çözüm tabloları.

---

## 3) MODÜL DETAYLARI

### 3.1 Kelime & hedef sahipliği

**Tek kural:** bir kelimenin **tek** sahibi olur — ya bir hedef sayfa (`target_id`) ya bir
blog yazısı (`owner_blog_id`). İkisi birden olamaz, sahipsiz kelime "atanmamış" sayılır.

Atama durumları: `ACTIVE`, `UNASSIGNED`, `HOLD_NO_OWNER`, `HOLD_NO_KEYWORD`,
`HOLD_CANNIBALIZATION`. Bu `status` (aktif/pasif boolean) ile **karıştırılmamalı** — ayrı alan.

Kelime tipleri: `COMMERCIAL_PRIMARY`, `COMMERCIAL_VARIANT`, `BLOG_PRIMARY`,
`BLOG_SECONDARY`, `BLOG_SUPPORTING`.

Bu model üretim hattını besler (§3.3) ve cannibalization kontrolünün temelidir.

### 3.2 İçerik SEO skoru — deterministik

100 puan, dört alt skor: **meta 30 + içerik 40 + teknik 15 + link 15**.

Örnek kontroller: meta title var mı / 60 karakteri aşıyor mu, meta description 155,
H1 tek mi, kelime sayısı, görsel alt metni, iç link var mı, slug ASCII mi, yayında mı.

**Sorun metinleri SABİT string olmalı** (enum/const). Dashboard sayımları bu tam metinlerle
eşleşir; metni değiştirirsen sayım sessizce bozulur.

Cron'da günlük koşar, `seo_analyses`'e yazar. **AI çağırmaz.**

### 3.3 AI içerik üretim hattı

Akış: `günlük komut` → kategori seç → **konu üret** (AI) → çakışma filtreleri → **yazı üret**
(AI, kuyrukta) → taslak kaydet → `publish_at` gelince yayınla.

**Konu üretimi prompt'u şunları içermeli:**
- Kategori adı + istenen konu sayısı (birden fazla aday iste — filtreye takılırsa yedek olsun)
- **Mevcut tüm blog başlıkları** (sınırsız, kırpmadan)
- **Yalnız sahiplenilmemiş kelime havuzu** (§7.3)
- **Permütasyon yasağı** — örnekle (§7.2)
- "Uygun konu yoksa boş liste döndür" izni

JSON şema: `{"topics":[{"title","slug","primary_keyword","search_intent","outline":[...]}]}`

**Yazı üretimi prompt'u:** başlık, kategori, ana keyword, önerilen bölümler, uzunluk,
H2/H3 hiyerarşisi, sonda CTA. İç link ekleme talimatı **kapalı** olmalı (§3.6).

JSON şema: `{"title","slug","meta_title","meta_description","body_html","faqs":[{"q","a"}],"image_suggestion"}`

**Her AI çağrısı `ai_generations`'a yazılır** (operation, input, output, status, hata).
Bozuk JSON'da bir kez yeniden dene, modele hatayı söyleyerek.

### 3.4 Çakışma koruması — ÜRETİM ANINDA

Konu üreticisinden çıkan her aday **iki filtreden** geçer:

**Filtre 1 — başlık benzerliği.** Tam eşleşme YETMEZ (§7.1). Algoritma:

```
tokens(başlık):
  1. Türkçe büyük harfleri elle eşle: İ→i, I→ı, Ş→ş, Ğ→ğ, Ü→ü, Ö→ö, Ç→ç
  2. küçük harfe çevir, artık U+0307 kombine noktayı sil        ← §7.5
  3. harf dışı her şeyi boşluğa çevir
  4. 3 harften kısa kelimeleri ve durak kelimeleri at
     (ve, ile, için, bir, bu, da, de, en, ne, nasıl, nedir, mi/mı/mu/mü,
      daha, çok, gibi, her, olan, olarak, nelerdir, hangi, kadar, ise, ya, veya, ki, göre)
  5. her kelimeyi İLK 6 HARFE indir (kaba gövdeleme)             ← §7.1
  6. küme olarak döndür

score(a,b) = |tokens(a) ∩ tokens(b)| / |tokens(a) ∪ tokens(b)|   (Jaccard)
```

Eşik aşılırsa konu **reddedilir**. Eşiği tahminle seçme — §7.1'deki kalibrasyon yöntemini uygula.

**Filtre 2 — keyword cannibalization.** Adayın `primary_keyword`'ünü hedefleyen içerik sayısı
tavanı aşıyorsa konu **reddedilir**. Uyarı yazıp geçme (§7.4).

**Reddedilen her aday gerekçesiyle log'a yazılır.** Sessiz eleme, "blog üretilmiyor"
şikâyetini teşhis edilemez yapar.

Hiç konu kalmazsa komut **sebebi söyler**: boşta kelime sayısı + aktif kategori sayısı +
nereye bakılacağı. Sessizce başarısız olmaz.

### 3.5 Çakışma temizleyici — GEÇMİŞ İÇİN

Üretim filtresi bundan sonrasını korur; yayınlanmış tekrarları temizlemez. Ayrı bir ekran:

**İKİ EŞİK, bilerek farklı:**
- **Tarama eşiği** (ör. 0.55): "çakışma olabilir" → ekranda listelenir, **buton yok**
- **Otomatik birleştirme eşiği** (ör. 0.90): "pratikte aynı yazı" → tek tuşla birleştirilir

%67 benzeyen iki yazıyı otomatik birleştirmek **içerik silmektir** (§7.6).

**Birleştirme ne yapar:**
1. 301 yönlendirme oluştur (kaybeden → kazanan)
2. Kaybedeni **yayından kaldır** (`status = 0`) — **ASLA SİLME** (§7.7)
3. Tek transaction: yönlendirme yazılamazsa yazı da yayında kalsın (yoksa 404 üretirsin)

**Kazananı veri seçer**, sen değil: `tıklama → gösterim → eski yayın tarihi → kısa slug`.
Son ölçüt önemli: çakışan ikinci yazı genelde `-2` ekli slug'la kaydedilir (§7.8).

**İdempotent olmalı** — buton iki kez tıklanabilir, ikinci çağrı hata değil no-op.
**Hedef yayında değilse reddet** — yoksa 301 zinciri 404'e gider.

### 3.6 İç link motoru

Üç parça:

1. **Kurallar** (`internal_link_rules`): anchor metni → hedef URL, öncelik, makale başına tavan
2. **Öneri motoru**: blog gövdesinde doğal anchor fırsatı bul, 0-100 skorla, onay/red akışı
3. **Uygulayıcı**: render anında linkleri bas, tavanları **her istekte** kontrol et

**Kurallar:**
- Link yalnız **düz metne** basılır — başlık (h1-h6), mevcut `<a>`, `code/pre/script` içine **asla**
- Dolu makaleye (boş kontenjan 0) öneri çıkmaz
- Reddedilen öneri (blog+hedef+anchor) bir daha çıkmaz
- Motor kelime **uydurmaz** — yalnız gerçek hedef havuzunu kullanır
- **AI gövdeye link gömmez.** Link basmanın tek sahibi kural motorudur; AI'ye bırakırsan
  tavan, sahiplik ve kapsam kuralları delinir.

Motor bir **kill switch** ile kapatılabilir olmalı ve **varsayılan KAPALI** gelmeli.

### 3.7 Search Console katmanı

- **Performans senkronu**: `query` ve `page` boyutlarında GSC satırlarını çek, `row_hash` ile
  tekilleştir. Mülk tüm alan adını kapsar — kurumsal olmayan yolları (`is_corporate`) ayır.
- **Sıralama geçmişi**: takip edilen kelimeler için günlük pozisyon → `seo_rank_history`.
  `ranking_url` ile beklenen URL'yi karşılaştır → `target_match` (yanlış sayfa sıralanıyor mu).
- **İndeks kontrolü**: URL Inspection API ile sayfa indekslenmiş mi.

**Google yetkileri (§7.9):** URL Inspection için servis hesabı mülke **"Tam"** kullanıcı
olarak eklenmeli; Indexing API için **"Sahip"** olmalı — sahiplik ayrı ekrandan verilir.
Mülk tipi (`sc-domain:` vs `https://`) yapılandırmayla **birebir** eşleşmeli.

### 3.8 Yönlendirme + 404

- 404 yakalanınca `not_found_logs`'a yaz (hit sayacını artır), **isteği bozma**
- Yönlendirme yalnız **404 anında** çalışır — global middleware değil (geçerli URL'lere sıfır maliyet)
- Öneri **deterministik**: string benzerliğiyle en olası güncel hedefi bul, **hedef uydurma**
- `from_hash` alanını **modelin kendisi** doldursun (kayıt öncesi hook). Elle INSERT'te
  unutulursa yönlendirme sessizce çalışmaz (§7.10)

### 3.9 AI ajan keşfi

- **`llms.txt` / `llms-full.txt`** üret (cron'da, request-time değil). İçerik **veritabanından**
  gelir — ürün, çözüm, entegrasyon listeleri; hiçbir şey uydurulmaz.
- **`robots.txt`** AI botlarını açıkça karşılasın.
- **`Link` yanıt başlıkları** (RFC 8288): `llms.txt` ve `sitemap.xml` adreslerini HTTP
  başlığından duyur — robots okumayan ajanlar siteyi böyle bulur.
- **`Content-Signal`** başlığı: `search=yes, ai-input=yes, ai-train=no` gibi.
- **schema.org (JSON-LD)**: veri yalnız DB/ayarlardan. Puan, yorum sayısı gibi alanlar
  gerçek veri yoksa **hiç basılmaz** (uydurma yıldız = manuel ceza riski).
- Başlık eklerken `GET` yanında **`HEAD`** isteklerini de kapsa (§7.11).

---

## 4) ZAMANLANMIŞ GÖREVLER

Hepsi overlap guard'lı. Saatler örnektir; sıra önemli.

| Zaman | Görev |
|---|---|
| 01:00 | sitemap üret |
| 01:10 | AI keşif dosyalarını üret (llms.txt) |
| 02:00 | SEO skorla (tüm içerik) |
| 03:00 | İndeks kontrolü (GSC URL Inspection) |
| 04:00 | Günlük blog konusu üret + kuyruğa al |
| 05:00 | Search Console performans senkronu |
| 05:10 | Kelime sıralama senkronu |
| 05:20 (haftalık) | Yeni kelime keşfi |
| 05:30 (haftalık) | Düşük CTR meta tazeleme |
| 05:40 | Yönlendirme önerisi (+ otomatik uygula) |
| 05:50 | İç linkleri uygula |
| her 5 dk | Zamanı gelen blogları yayınla |

**Sıra mantığı:** veri çekme (05:00-05:10) → türetilmiş işler (05:20+). Ters çevirirsen
türetilmiş işler bir gün eski veriyle koşar.

---

## 5) AYARLAR

Key/value ayar deposu (EAV + cache). Gruplar:

**İçerik/marka:** dil, ton, marka sesi, hedef kitle, kaçınılacaklar, varsayılan şehir/ülke,
içerik uzunluğu, skor hedefi

**Blog otomasyonu:** günlük üretim açık/kapalı, günlük adet, yayın saat aralığı (başlangıç/bitiş),
otomatik yayın

**AI otomasyonu:** otomatik meta, otomatik FAQ, düşük-CTR meta tazeleme (+ limit),
AI'nin iç link basması (varsayılan KAPALI)

**İç link motoru:** açık/kapalı (varsayılan KAPALI), otomatik uygula, makale başına tavan,
mutlak tavan, anasayfa tavanı, minimum skor, mevcut linkleri değiştir, parti boyutu

**Çakışma:** tarama eşiği, otomatik birleştirme eşiği, keyword başına azami sahip

Eşikler **kod içine gömülmemeli** — yapılandırılabilir olsun, ama **geçersiz değer filtreyi
sessizce kapatmasın** (0 veya 1'den büyük eşik → güvenli varsayılana düş).

---

## 6) LOGLAMA TASARIMI

Otomasyonun izi **ayrı bir günlük dosyada** tutulmalı, ana uygulama log'unda değil.

**Neden ayrı:** sık koşan bir görev (dakikada/5 dakikada bir) ana log'a yazarsa gerçek
hataları gömer. Ayrı dosyada "X neden olmadı?" tek `grep` ile cevaplanır.

**Üç seviye disiplini:**

1. **Her tur bir satır yaz — değişiklik olmasa bile.** "Değişiklik yoksa sus" davranışı,
   "hiç koşmadı" ile "koşup atladı" ayrımını imkânsız kılar. Bu ayrım teşhisin yarısıdır.
2. **Rutin sonuçlar `debug`, anormal olanlar `info`.** Rutin = hiçbir şey değişmedi ve tüm
   sebepler beklenen türden ("zaten istenen durumda", "yapılandırılmamış"). Bir hata varsa,
   yanındaki sebepler rutin olsa bile satır `info`'ya yükselir.
3. **Seviye env'den ayarlanabilsin** — teşhis gerektiğinde bir günlüğüne `debug` açılır.

**Log yazamamak otomasyonu durdurmamalı** (try/catch), ama **yutma**: kanal yoksa varsayılan
kanala düş. Sessiz yutma teşhisi öldürür (§7.12).

Günlük rotasyon + makul saklama (14 gün).

---

## 7) TUZAKLAR — canlıda yaşanmış, ölçülmüş

Bu bölümdeki her madde gerçek bir hatadır. Spec'in parçasıdır.

### 7.1 Tam eşleşmeli tekrar filtresi işe yaramaz

Filtre `in_array(strtolower($title), $existing)` idi. Sonuç: **77 yazılık blogda 4 çift
kelimesi kelimesine aynı yazı yayınlandı.** Tek kelime veya tek ek fark filtreyi geçiyordu:

| Fark | Çift |
|---|---|
| soru eki | "…Katkıları **Nelerdir?**" ↔ "…Katkıları" |
| çoğul+edat | "**Restoran** POS Fiyatları…" ↔ "**Restoranlar İçin** POS Fiyatları…" |
| bulunma hâli | "**Restoranlarda** Online Sipariş…" ↔ "**Restoranlar İçin** Online Sipariş…" |
| fiil eki | "Artır**manın** Yolları" ↔ "Artır**ma** Yolları" |

**Gövdeleme şart.** Onsuz "restoran" ≠ "restoranlar" ≠ "restoranlarda" sayılır ve bu çiftler
yine kaçar. İlk 6 harfe indirince dördü de **%100** çıkıyor.

**Eşiği veriyle kalibre et.** Tüm yayındaki başlık çiftlerini ölç, dağılımı çıkar:

```
eşik 0.70 →   4 çift   (kelimesi kelimesine aynı olanlar)
eşik 0.60 →  11 çift
eşik 0.55 →  16 çift   ← seçilen
eşik 0.50 →  45 çift
eşik 0.40 → 141 çift   (artık alakasızlar da giriyor)
```

**Sıkı tarafı seç.** Maliyet asimetrik: yanlış RET ucuzdur (üretici bir sonraki adaya geçer),
kaçan TEKRAR kalıcı SEO hasarıdır ve elle birleştirme + 301 gerektirir.

### 7.2 "Benzerini üretme" demek yetmez

Model aynı konuyu dolgu kelimesi değiştirerek geri veriyordu. Prompt'a **örnekli açık yasak** koy:

```
YASAK — bunlar YENİ konu SAYILMAZ:
  • Mevcut başlığın kelime sırasını, ekini veya çoğulunu değiştirmek
  • Mevcut başlığa soru eki eklemek
  • Aynı konuyu şu dolgu kelimeleriyle tekrarlamak: faydaları, önemi, avantajları,
    verimlilik, stratejiler, ipuçları, çözümler, rehber, nasıl artırılır, yolları
  • Aynı ana keyword'ü hedefleyen ikinci bir yazı
Uygun konu yoksa boş liste döndür.
```

### 7.3 Kelime havuzuna kullanılmışları koyma

Prompt havuzu "önceliklendir" diye sunuyordu ama havuz **tüm** kelimeleri içeriyordu. Sonuç:
hakkında zaten 30 yazı olan kelime tekrar tekrar öneriliyordu.

Havuza yalnız **sahiplenilmemiş** kelimeler girmeli. Eleme iki ölçüt: (a) sahip alanı dolu,
(b) kelime metni mevcut bir içeriğin başlık/meta'sında geçiyor. Gerçek veride 159 kelimenin
**42'si** bu filtreyle elendi.

**Performans:** içerikleri **tek seferde** yükle. Kelime başına tam tarama yaparsan 159
kelimede 636 sorgu edersin.

### 7.4 Üretip kimsenin okumadığı uyarı

Cannibalization kontrolü **vardı** — ama sonucu `$topic['warnings'][]` dizisine yazıp
bırakıyordu ve o alanı **hiçbir yer okumuyordu**. Tespit ediliyor, hiçbir şey olmuyordu.

**Kural:** bir kontrol ya engeller ya da görünür bir yere yazar. "Uyarı üret, sonra düşün"
diye bir şey yok.

### 7.5 Türkçe büyük İ tuzağı

`mb_strtolower('İ')` → `"i" + U+0307` (kombine nokta) üretir ve düz `i` ile **eşleşmez**.
Aynı şekilde JS'de `/i` regex bayrağı `İ` harfini katlamaz.

Normalleştirmede büyük harfleri **elle eşle**, sonra artık kombine noktayı sil. Bunu
testle kilitle — sessizce yanlış sonuç veren bir hatadır.

### 7.6 Tek eşikle otomatik birleştirme

Tarama eşiği (çakışma var) ile birleştirme eşiği (aynı yazı) **ayrı** olmalı. Gerçek veride
%67 benzeyen iki yazı vardı: biri "müşteri memnuniyeti", diğeri "karlılık" — **ayrı konular**.
Tek eşikle otomatik birleştirseydin içerik silmiş olurdun.

### 7.7 Silme yerine yayından kaldırma

Birleştirmede kaybeden yazı **silinmez**: yayından kaldırılır + 301 ile yönlendirilir.
Silmek geri dönüşsüzdür ve yönlendirme hedefini de yok eder. Karar yanlışsa yazıyı tekrar
yayınlamak yeterli olmalı.

### 7.8 Slug çakışmasına sayı eklemek sorunu gizler

Sistem çakışan slug'a `-2` ekleyip yayınlıyordu. Yani çakışmayı **fark ediyor**, sonra
görmezden geliyordu. Gerçek duplicate'lerin ikisinde bu iz vardı.

Slug çakışması bir **sinyaldir** — sayı eklemeden önce "bu konu zaten var mı" diye sor.

### 7.9 Google Search Console yetkileri

`403 PERMISSION_DENIED — "You do not own this site"` hatasının üç sebebi olabilir:

1. Servis hesabı mülke kullanıcı olarak eklenmemiş
2. **Mülk tipi uyuşmuyor** — `sc-domain:site.com` (Domain) vs `https://site.com/` (URL öneki).
   Yapılandırma hangisiyse GSC'deki mülk de o olmalı.
3. İncelenen URL mülkün dışında (www / http farkı)

**İki farklı yetki seviyesi:** URL Inspection için **"Tam"** yeter; Indexing API için
**"Sahip"** şart ve sahiplik ayrı ekrandan (sahiplik doğrulama → yetkilendirilmiş sahipler)
verilir — kullanıcı ekleme penceresinde "Sahip" seçeneği çıkmaz.

### 7.10 Hash alanını elle doldurma

Yönlendirme tablosunda `from_hash` uygulamanın arama anahtarıdır. Elle `INSERT` yazarken
unutulursa **301 sessizce çalışmaz**. Alanı modelin kayıt-öncesi hook'u doldursun; SQL
örneklerinde de `MD5(...)` açıkça yer alsın.

Ayrıca: `source` gibi kolonların **uzunluk sınırına** dikkat. `varchar(20)` bir kolona
23 karakterlik bir değer yazmak canlıda her birleştirmeyi SQL hatasıyla düşürür.

### 7.11 HEAD isteklerini unutma

Başlık ekleyen middleware `GET` ile sınırlandırılmıştı; `curl -I` (HEAD) yanıt vermeyince
"500 veriyor" sanıldı. Güvenli metotların hepsini kapsa.

### 7.12 Sessiz yutma teşhisi öldürür

Log çağrısı `try/catch` ile sarılıp hata **yutuluyordu**. Kanal yapılandırması eksik olunca
hiçbir iz kalmıyordu. Doğrusu: yut ama **varsayılan kanala düş**.

İlgili: yapılandırma cache'i. Yeni bir log kanalı eklediysen cache temizlenmeden kanal
görünmez. Deploy adımlarına yaz.

### 7.13 Ölçek sessiz katil

Bir projede log'ların **%99'u** (125.000 olayın 124.539'u) tek bir gürültü kaynağından
geliyordu ve gerçek hataları gömüyordu. Bir şeyi log'lamadan önce "bu günde kaç satır
üretir" diye hesapla.

Aynı disiplin sorgularda da geçerli: O(n²) bir karşılaştırma 77 kayıtta anlık, 1000 kayıtta
değil. Rozet/sayaç gibi sürekli hesaplanan değerleri **önbelleğe al** — ama veriyi değiştiren
işlem önbelleği **temizlesin**, yoksa kullanıcı işlemi yapar ve eski sayıyı görmeye devam eder.

### 7.14 Zamanlama hassasiyeti

Kararı dakika hassasiyetinde hesaplayıp görevi 5 dakikada bir koşturmak, kararı 5 dakika
geciktirir. Dış etkisi olan işlerde (sıralama, açılış/kapanış) bu doğrudan kayıptır.

**Sıklığı kararın hassasiyetiyle eşitle.** Maliyeti önce ölç: dış servise ancak gerçek bir
değişiklik varken çıkılıyorsa sık koşmak bedavadır.

### 7.15 Üretim hızı ≠ içerik kapasitesi

Asıl kök sebep: **3 aktif kategori × günde 3 yazı**. Her gün her kategoriden bir yazı, sonsuza
kadar. 26 gün sonra modelden "bu kategoride benzersiz konu bul" isteniyordu; elinde kelime
permütasyonundan başka bir şey kalmıyordu.

**Filtre tekrarı engeller ama yeni konu üretmez.** Üretim hacmi konu envanterine bağlıdır.
Sistem havuz tükendiğinde **durup haber vermeli**, sessizce tekrar üretmemeli.

---

## 8) UYGULAMA SIRASI

Her fazı bitirince kullanıcıya göster, onay al.

1. **Altyapı** — AI client (tek, OpenAI-uyumlu), Google auth sarmalayıcı, ayar deposu
   (EAV + cache), `ai_generations`, kuyruk + zamanlayıcı iskeleti, ortak yardımcılar
   (yol normalleştirme, hash, upsert-increment), **log kanalı** (§6)
2. **Gözlem & yönlendirme** — 404 log, AI bot log, yönlendirme (404 hook). En bağımsız,
   en somut; buradan başlamak iyi.
3. **AI keşif dosyaları** — llms.txt, robots, schema.org, Link başlıkları
4. **Veri katmanı** — GSC performans senkronu + sıralama geçmişi
5. **Deterministik skor** — SEO analizi + dashboard
6. **Kelime & hedef sahipliği** — CRUD + atama durumları
7. **AI üretim hattı** — konu üretimi + **çakışma filtreleri (§3.4)** + yazı üretimi +
   zamanlı yayın. *Filtreleri üretimle birlikte kur, sonraya bırakma.*
8. **Çakışma temizleyici** — tarama ekranı + birleştirme (§3.5)
9. **İç link motoru** — kurallar → öneri → uygulayıcı. Kill switch KAPALI başlasın.

---

## 9) KABUL KRİTERLERİ

Bunlar test edilebilir; testleri yaz.

**Çakışma filtresi**
- [ ] Gerçek duplicate çiftleri eşiği geçiyor (uydurma değil, canlıdan alınmış başlıklarla)
- [ ] Farklı konular engellenmiyor (fazla sıkı filtre üretimi durdurur)
- [ ] Türkçe büyük İ katlanıyor: `tokens('İşletme') == tokens('işletme')`
- [ ] Aynı başlık = 1.0, alakasız = 0.0, boş başlık = 0.0 (asla "tekrar" sayılmamalı)
- [ ] Geçersiz eşik (0 veya >1) güvenli varsayılana düşüyor

**Birleştirme**
- [ ] Yazı silinmiyor, yalnız yayından kalkıyor
- [ ] 301 oluşuyor ve hash alanı doğru
- [ ] İdempotent: ikinci çağrı hata değil
- [ ] Hedef yayında değilse reddediyor
- [ ] Kazanan trafiğe göre seçiliyor (veri yoksa tarihe düşüyor)
- [ ] %67 benzeyen çift otomatik birleştirmeye **girmiyor**

**Loglama**
- [ ] Rutin turlar sessiz, değişiklik ve hatalar görünür
- [ ] Hata varsa, yanındaki rutin sebepler onu susturmuyor
- [ ] Kanal ayrı dosyaya yazıyor, ana log'a değil

**Zamanlama**
- [ ] Görev sıklığı kararın hassasiyetiyle uyumlu (testle kilitle)

---

## 10) KIRMIZI ÇİZGİLER

1. **UYDURMA YASAK.** Hiçbir metrik/içerik/yorum/müşteri/rakam uydurulmaz — hepsi DB/GSC/GA'dan.
   Prompt'a "listede olmayanı uydurma" yaz **ve** kod tarafında çıktıyı sanitize et
   (slug ASCII, meta_title ≤60, meta_description ≤155). **Modele güvenilmez.**
2. **API anahtarı asla** log'a, DB'ye, URL'ye, hata mesajına yazılmaz.
3. **Loglama isteği bozmaz.** Hepsi fail-safe — ama sessizce yutma, varsayılana düş.
4. **Sabit sorun metinleri.** Dashboard sayımları tam metinle eşleşir.
5. **Graceful degradation.** Google kimliği yoksa GSC aksiyonları gizlenir, çökmez.
   AI anahtarı yoksa AI aksiyonları gizlenir.
6. **Yönlendirme yalnız 404'te** çalışır — global middleware değil.
7. **Keşif dosyaları build-time** (cron), request-time değil.
8. **Skorlama, çakışma tespiti, yönlendirme önerisi, iç link önerisi deterministiktir.**
   AI yalnız: içerik üretimi/optimizasyon, meta, FAQ, analitik rapor yorumu.
9. **İçerik silinmez.** Yayından kaldır + yönlendir.
10. **Otomatik yıkıcı işlem yok.** Geri alınamaz bir şey yapan her akış ya onay ister
    ya da geri alınabilir olur.

---

*Kaynak: eyyopos.com (Laravel 11 + Filament) üzerinde çalışan implementasyon, Eylül 2026.*
