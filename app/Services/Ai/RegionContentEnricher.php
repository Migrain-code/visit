<?php

namespace App\Services\Ai;

use App\Models\District;
use App\Models\Province;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Support\AutomationLog;
use App\Support\BusinessContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Bölge, tur ve kategori sayfalarının içeriğini zenginleştirir (spec §1: AI yalnız içerik üretir).
 *
 * KURAL: mevcut benzersiz giriş metni KORUNUR, üzerine bölgeye özgü H2 bölümleri eklenir.
 * Şablonla çoğaltılmış içerik üretilmez; prompt'a yalnız O BÖLGEYE ait gerçek veriler verilir
 * (biniş noktaları, bağlı olduğu il, mevcut metin, düzenlenen turlar).
 *
 * Rakam, ödül, tecrübe yılı gibi doğrulanamaz iddialar YASAKTIR (spec §10.1) ve çıktı
 * ayrıca kod tarafında temizlenir.
 */
class RegionContentEnricher
{
    public function __construct(
        protected AiClient $ai,
        protected ContentSanitizer $sanitizer,
    ) {}

    /** @return array{words_before: int, words_after: int, h2: int} */
    public function enrich(Model $model): array
    {
        [$system, $user, $meta] = match (true) {
            $model instanceof District => $this->districtPrompt($model),
            $model instanceof Province => $this->provincePrompt($model),
            $model instanceof Tour => $this->tourPrompt($model),
            $model instanceof TourCategory => $this->categoryPrompt($model),
            default => throw new AiException('Bu içerik tipi zenginleştirilemiyor: '.$model::class),
        };

        $before = $this->sanitizer->wordCount($this->bodyOf($model));

        $payload = $this->ai->json('seo.content', $system, $user, $meta);

        $body = $this->sanitizer->bodyHtml($payload['body_html'] ?? null);

        if ($this->sanitizer->wordCount($body) < 200) {
            throw new AiException('Üretilen içerik çok kısa, kaydedilmedi.');
        }

        $updates = [$this->bodyField($model) => $body];

        // Meta açıklama 70-155 aralığına çekilir.
        if (filled($payload['meta_description'] ?? null)) {
            $updates['meta_description'] = $this->sanitizer->metaDescription($payload['meta_description'], $model->meta_description);
        }

        if (filled($payload['meta_title'] ?? null)) {
            $updates['meta_title'] = $this->sanitizer->metaTitle($payload['meta_title'], $model->meta_title);
        }

        $model->forceFill($updates)->save();

        $after = $this->sanitizer->wordCount($body);

        AutomationLog::summary('seo.enrich', [
            'type' => class_basename($model),
            'id' => $model->getKey(),
            'words_before' => $before,
            'words_after' => $after,
            'h2' => preg_match_all('/<h2[\s>]/i', $body),
        ], changed: true);

        return ['words_before' => $before, 'words_after' => $after, 'h2' => preg_match_all('/<h2[\s>]/i', $body)];
    }

    private function bodyField(Model $model): string
    {
        return $model instanceof Tour ? 'description' : 'content';
    }

    private function bodyOf(Model $model): ?string
    {
        return $model->{$this->bodyField($model)};
    }

    private function systemPrompt(): string
    {
        return implode("\n", [
            'Sen '.BusinessContext::describe().' olan bir firmanın',
            'SEO içerik yazarısın. Türkçe, sade, gezme isteği uyandıran ama güven veren bir dille yazarsın.',
            '',
            'MUTLAK KURALLAR:',
            '- ASLA UYDURMA. Rakam, yıl, müşteri sayısı, ödül, sertifika, "20 yıllık tecrübe", "%98 memnuniyet"',
            '  gibi doğrulanamaz hiçbir ifade kullanma. Yalnız sana verilen gerçek bilgileri kullan.',
            '- Verilmeyen biniş noktası, ilçe, otel veya gezilecek yer adı UYDURMA.',
            '- Fiyat ve tarih rakamı verme; güncel fiyat ve tarihlerin tur takviminde olduğunu söyleyebilirsin.',
            '- Müze ücreti, vize koşulu, sefer saati gibi değişen bilgileri kesin ifadeyle yazma.',
            '- <a> etiketi KULLANMA. Hiçbir link ekleme; linkleri ayrı bir sistem basar.',
            '- <h1> kullanma; sayfanın tek H1 başlığını şablon basar. Ara başlıklar <h2>, alt başlıklar <h3>.',
            '- Pazarlama klişesi ve abartı kullanma. Okuyucunun işine yarayan somut bilgi ver.',
            '',
            'ÇIKTI: yalnızca geçerli JSON. Markdown, kod bloğu veya açıklama ekleme.',
            'Şema: {"body_html":"","meta_title":"","meta_description":""}',
        ]);
    }

    /** @return array<int, string> yayındaki turların adları */
    private function tourTitles(): array
    {
        return Tour::query()->where('is_active', true)->orderBy('sort_order')->pluck('title')->all();
    }

    /** @return array{0: string, 1: string, 2: array} */
    private function districtPrompt(District $district): array
    {
        $province = $district->province;
        $points = (array) $district->pickup_points;

        $lines = [
            'GÖREV: Aşağıdaki ilçe sayfasının içeriğini genişlet. Sayfa, turlarımıza BU İLÇEDEN katılmak',
            'isteyenlere yöneliktir ("'.$district->name.' çıkışlı turlar").',
            '',
            'İLÇE: '.$district->name,
            'İL: '.$province?->name,
            '',
            'MEVCUT METİN (bu metnin bilgilerini KORU, sil veya çelişme — üzerine ekleyerek genişlet):',
            strip_tags((string) $district->content),
            '',
            'BU İLÇEDEKİ BİNİŞ NOKTALARIMIZ (yalnız bunları kullan, yenisini uydurma; liste boşsa biniş',
            'noktasının rezervasyon sırasında bildirildiğini söyle):',
            implode(', ', $points),
            '',
            'DÜZENLEDİĞİMİZ TURLAR:',
            '- '.implode("\n- ", $this->tourTitles()),
        ];

        if ($faqs = (array) $district->faqs) {
            $lines[] = '';
            $lines[] = 'BU İLÇE İÇİN BİLİNEN SORU-CEVAPLAR (bilgi kaynağı olarak kullan, aynen tekrarlama):';

            foreach ($faqs as $faq) {
                $lines[] = '- '.($faq['question'] ?? '').' → '.($faq['answer'] ?? '');
            }
        }

        $lines[] = '';
        $lines[] = 'İSTENEN YAPI (400-550 kelime):';
        $lines[] = '1. Mevcut metinden gelen giriş paragrafları (genişletilmiş hâli, H2 başlığı olmadan)';
        $lines[] = '2. <h2>'.$district->name.' çıkışlı turlarımız</h2>';
        $lines[] = '   Yukarıdaki tur listesinden söz et; tur uydurma, fiyat ve tarih verme.';
        $lines[] = '3. <h2>Biniş noktaları ve kalkış</h2>';
        $lines[] = '   Yolcuların nereden alındığı; mevcut metindeki ve yukarıdaki bilgiyi kullan.';
        $lines[] = '4. <h2>Tura katılmadan önce</h2>';
        $lines[] = '   Yolcunun ne hazırlaması gerektiği; somut ve kısa maddeler (<ul><li>).';
        $lines[] = '';
        $lines[] = 'ÖNEMLİ: Bu metin yalnız '.$district->name.' için yazılıyor. Başka bir ilçeye kopyalanabilecek';
        $lines[] = 'genel bir metin OLMASIN; ilçenin kendi özelliklerine (biniş noktaları, konumu) değin.';
        $lines[] = '';
        $lines[] = 'meta_title: en fazla 60 karakter, "'.$district->name.'" geçsin.';
        $lines[] = 'meta_description: 120-150 karakter arası, "'.$district->name.'" geçsin, harekete geçirici olsun.';

        return [
            $this->systemPrompt(),
            implode("\n", $lines),
            ['content_type' => 'district', 'content_id' => $district->getKey(), 'batch_key' => 'enrich.district'],
        ];
    }

    /** @return array{0: string, 1: string, 2: array} */
    private function provincePrompt(Province $province): array
    {
        $districts = $province->activeDistricts()->pluck('name')->all();

        $lines = [
            'GÖREV: Aşağıdaki il sayfasının içeriğini genişlet. Sayfa, turlarımıza BU İLDEN katılmak',
            'isteyenlere yöneliktir ("'.$province->name.' çıkışlı turlar").',
            '',
            'İL: '.$province->name,
            '',
            'MEVCUT METİN (bilgilerini KORU, üzerine ekleyerek genişlet):',
            strip_tags((string) $province->content),
            '',
            'BU İLDE YOLCU ALDIĞIMIZ AKTİF İLÇELER (yalnız bunları say, başka ilçe UYDURMA):',
            implode(', ', $districts),
            '',
            'DÜZENLEDİĞİMİZ TURLAR:',
            '- '.implode("\n- ", $this->tourTitles()),
            '',
            'İSTENEN YAPI (400-550 kelime):',
            '1. Giriş paragrafları (H2 başlığı olmadan)',
            '2. <h2>'.$province->name.' çıkışlı turlarımız</h2>',
            '3. <h2>Yolcu aldığımız ilçeler</h2> — yalnız yukarıdaki ilçeleri anlat',
            '4. <h2>Rezervasyon süreci</h2> — bilgi alma, kayıt, yolcu bilgileri, kalkış günü',
            '',
            'meta_title: en fazla 60 karakter. meta_description: 120-150 karakter.',
        ];

        return [
            $this->systemPrompt(),
            implode("\n", $lines),
            ['content_type' => 'province', 'content_id' => $province->getKey(), 'batch_key' => 'enrich.province'],
        ];
    }

    /** @return array{0: string, 1: string, 2: array} */
    private function tourPrompt(Tour $tour): array
    {
        $lines = [
            'GÖREV: Aşağıdaki tur sayfasının açıklama metnini genişlet.',
            '',
            'TUR: '.$tour->title,
            'SÜRE: '.$tour->duration_label,
            'KISA AÇIKLAMA: '.$tour->short_description,
            'GEZİLECEK YERLER: '.$tour->destinations,
            'ULAŞIM: '.$tour->transport,
            'KONAKLAMA: '.$tour->accommodation,
            '',
            'MEVCUT METİN (bilgilerini KORU, üzerine ekleyerek genişlet):',
            strip_tags((string) $tour->description),
            '',
            'GÜN GÜN PROGRAM (sayfada ayrı bir bölüm olarak zaten var, metinde TEKRARLAMA;',
            'bağlam olarak kullan, programda OLMAYAN bir yeri gezilecekmiş gibi yazma):',
            '- '.implode("\n- ", collect($tour->itinerary ?? [])->map(fn ($d) => ($d['title'] ?? '').': '.($d['description'] ?? ''))->all()),
            '',
            'KALKIŞ NOKTALARIMIZ: '.BusinessContext::regions(),
            '',
            'İSTENEN YAPI (400-550 kelime):',
            '1. Giriş paragrafları (H2 başlığı olmadan)',
            '2. <h2>Bu turda sizi neler bekliyor?</h2> — programdaki yerlerin öne çıkan yanları',
            '3. <h2>Kimler için uygun?</h2> — aileler, çiftler, ileri yaş, çocuklu misafirler açısından',
            '4. <h2>Yanınıza ne almalısınız?</h2> — mevsime ve programa göre somut maddeler (<ul><li>)',
            '',
            'meta_title: en fazla 60 karakter. meta_description: 120-150 karakter.',
        ];

        return [
            $this->systemPrompt(),
            implode("\n", $lines),
            ['content_type' => 'tour', 'content_id' => $tour->getKey(), 'batch_key' => 'enrich.tour'],
        ];
    }

    /** @return array{0: string, 1: string, 2: array} */
    private function categoryPrompt(TourCategory $category): array
    {
        $tours = $category->activeTours()->pluck('title')->all();

        $lines = [
            'GÖREV: Aşağıdaki tur kategorisi sayfasının içeriğini genişlet.',
            '',
            'KATEGORİ: '.$category->name,
            'KISA AÇIKLAMA: '.$category->description,
            '',
            'MEVCUT METİN (bilgilerini KORU, üzerine ekleyerek genişlet):',
            strip_tags((string) $category->content),
            '',
            'BU KATEGORİDEKİ TURLARIMIZ (yalnız bunlardan söz et, tur UYDURMA):',
            '- '.implode("\n- ", $tours),
            '',
            'İSTENEN YAPI (350-500 kelime):',
            '1. Giriş paragrafları (H2 başlığı olmadan)',
            '2. <h2>'.$category->name.' nasıl geçer?</h2>',
            '3. <h2>Hangi turu seçmeliyim?</h2> — yukarıdaki turları kime uygun olduğuna göre karşılaştır',
            '4. <h2>Rezervasyon ve kalkış</h2>',
            '',
            'meta_title: en fazla 60 karakter. meta_description: 120-150 karakter.',
        ];

        return [
            $this->systemPrompt(),
            implode("\n", $lines),
            ['content_type' => 'category', 'content_id' => $category->getKey(), 'batch_key' => 'enrich.category'],
        ];
    }
}
