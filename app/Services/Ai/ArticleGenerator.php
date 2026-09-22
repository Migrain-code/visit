<?php

namespace App\Services\Ai;

use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\SeoKeyword;
use App\Support\AutomationLog;
use App\Support\BusinessContext;
use App\Support\SeoConfig;
use App\Support\TurkishText;
use Illuminate\Support\Str;

/**
 * AI blog yazısı üretimi (spec §3.3).
 *
 * İç link ekleme talimatı KAPALIDIR: gövdeye link basmanın tek sahibi iç link
 * motorudur (spec §3.6).
 */
class ArticleGenerator
{
    public function __construct(
        protected AiClient $ai,
        protected ContentSanitizer $sanitizer,
    ) {}

    /**
     * Taslak yazıyı oluşturur ve yayın zamanını planlar.
     *
     * @param  array{title: string, slug: string, primary_keyword: ?string, outline: array<int, string>}  $topic
     */
    public function generate(BlogCategory $category, array $topic, ?\DateTimeInterface $publishAt = null): Blog
    {
        $minWords = SeoConfig::int('blog_min_words', (int) config('seo.blog.min_words', 700), 200, 5000);
        $maxWords = SeoConfig::int('blog_max_words', (int) config('seo.blog.max_words', 1200), $minWords, 8000);

        $payload = $this->ai->json(
            'blog.article',
            $this->systemPrompt(),
            $this->userPrompt($category, $topic, $minWords, $maxWords),
            ['content_type' => 'blog_topic', 'batch_key' => 'article.'.Str::limit($topic['slug'], 40, '')],
        );

        $title = $this->sanitizer->title($payload['title'] ?? $topic['title']);
        $body = $this->sanitizer->bodyHtml($payload['body_html'] ?? null);

        // UZUNLUĞU MODELE BIRAKMA: prompt'ta istenen uzunluk çoğu modelde tutmaz.
        // Kısa gelirse bir kez genişletme turu yapılır (spec §10.1: modele güvenilmez).
        $body = $this->expandIfShort($body, $title, $topic, $minWords, $maxWords);
        $slug = $this->uniqueSlug($this->sanitizer->slug($payload['slug'] ?? $topic['slug'], $title));

        $blog = Blog::create([
            'blog_category_id' => $category->getKey(),
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $this->sanitizer->metaDescription($payload['meta_description'] ?? null, $title),
            'body_html' => $body,
            'image_alt' => $this->sanitizer->title($payload['image_suggestion'] ?? null, 150) ?: null,
            'meta_title' => $this->sanitizer->metaTitle($payload['meta_title'] ?? null, $title),
            'meta_description' => $this->sanitizer->metaDescription($payload['meta_description'] ?? null, $title),
            'primary_keyword' => $topic['primary_keyword'] ?: null,
            'faqs' => $this->sanitizer->faqs($payload['faqs'] ?? []),
            'status' => Blog::STATUS_DRAFT,
            'publish_at' => $publishAt,
            'source' => Blog::SOURCE_AI,
        ]);

        // Kelimeyi bu yazıya SAHİPLENDİR — havuzdan düşsün (spec §3.1, §7.3).
        $this->assignKeyword($blog);

        AutomationLog::summary('blog.article', [
            'blog_id' => $blog->getKey(),
            'title' => $title,
            'words' => $this->sanitizer->wordCount($body),
            'faqs' => count($blog->faqs ?? []),
            'publish_at' => $publishAt?->format('Y-m-d H:i'),
        ], changed: true);

        return $blog;
    }

    /**
     * Gövde istenen uzunluğun belirgin altındaysa BİR KEZ genişletme turu yapar.
     *
     * Genişletme, mevcut metni yeniden yazmak yerine üzerine bölüm ekler; böylece
     * ilk turdaki doğru bilgiler kaybolmaz.
     */
    private function expandIfShort(string $body, string $title, array $topic, int $minWords, int $maxWords): string
    {
        $words = $this->sanitizer->wordCount($body);

        // %85'in altındaysa genişlet; ufak sapmalar için tur harcama.
        if ($words >= (int) ($minWords * 0.85)) {
            return $body;
        }

        try {
            $payload = $this->ai->json(
                'blog.article',
                $this->systemPrompt(),
                implode("\n", [
                    'Aşağıdaki yazı çok kısa: '.$words.' kelime. İstenen: '.$minWords.'-'.$maxWords.' kelime.',
                    '',
                    'GÖREV: Bu yazıyı GENİŞLET. Mevcut bölümleri ve bilgileri KORU, silme.',
                    'Yeni <h2> bölümleri ekleyerek ve var olan bölümleri derinleştirerek en az '.$minWords.' kelimeye çıkar.',
                    '',
                    'Eklenebilecek bölüm fikirleri (yazıya uyanları seç):',
                    '- Gezilecek yerler ve her birinde ne yapılır',
                    '- En uygun mevsim, hava ve ne giyilir',
                    '- Yanınıza almanız gerekenler ve sık yapılan hatalar',
                    '- Yöresel lezzetler ve alışveriş',
                    '- Turla gitmenin bireysel gitmeye göre farkı',
                    '- Kalkış noktalarına ('.BusinessContext::join(BusinessContext::provinceNames()).') özgü pratik notlar',
                    '',
                    'BAŞLIK: '.$title,
                    '',
                    'MEVCUT YAZI:',
                    $body,
                    '',
                    'Kurallar aynen geçerli: uydurma yok, <a> yok, <h1> yok, sonda CTA paragrafı.',
                    'Şema: {"body_html":""}',
                ]),
                ['batch_key' => 'article.expand'],
            );

            $expanded = $this->sanitizer->bodyHtml($payload['body_html'] ?? null);

            // Genişletme başarısızsa orijinali koru — daha kısa bir metinle değiştirme.
            if ($this->sanitizer->wordCount($expanded) > $words) {
                AutomationLog::summary('blog.expand', [
                    'title' => $title,
                    'before' => $words,
                    'after' => $this->sanitizer->wordCount($expanded),
                ], changed: true);

                return $expanded;
            }
        } catch (\Throwable $e) {
            AutomationLog::error('blog.expand', $e->getMessage(), ['title' => $title]);
        }

        return $body;
    }

    private function assignKeyword(Blog $blog): void
    {
        if (blank($blog->primary_keyword)) {
            return;
        }

        $keyword = SeoKeyword::query()
            ->where('keyword_hash', md5(TurkishText::lower($blog->primary_keyword)))
            ->first();

        if ($keyword && ! $keyword->hasOwner()) {
            $keyword->update(['owner_blog_id' => $blog->getKey()]);
        }
    }

    private function uniqueSlug(string $slug): string
    {
        $slug = $slug !== '' ? $slug : 'yazi';
        $base = $slug;
        $i = 2;

        // NOT: slug çakışması bir SİNYALDİR (spec §7.8). Çakışma filtresi konuyu zaten
        // elemiş olmalıdır; buraya düşülmesi beklenmez, bu yüzden log'lanır.
        while (Blog::query()->where('slug', $slug)->exists()) {
            AutomationLog::info('blog.slug_collision', ['base' => $base, 'attempt' => $i]);
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    private function systemPrompt(): string
    {
        return implode("\n", [
            'Sen '.BusinessContext::describe().' olan bir firmanın',
            'içerik yazarısın. Türkçe, sade, gezme isteği uyandıran ama güven veren bir dille yazarsın.',
            '',
            'KURALLAR:',
            '- ASLA uydurma: fiyat, tarih, müşteri adı, yorum, ödül, "20 yıllık tecrübe" gibi doğrulanamaz ifade KULLANMA.',
            '- Müze giriş ücreti, vize koşulu, sefer saati gibi DEĞİŞEN bilgileri kesin rakamla verme; "güncel bilgiyi rezervasyon sırasında teyit edin" de.',
            '- Gezilecek yerler hakkında yalnız genel kabul görmüş, doğrulanabilir bilgileri yaz. Emin olmadığın tarihî ayrıntıyı yazma.',
            '- Abartı ve pazarlama klişesi kullanma. Okuyucunun işine yarayan somut bilgi ver.',
            '- Gövdede <a> etiketi KULLANMA. Hiçbir link ekleme. Linkleri başka bir sistem ekler.',
            '- <h1> kullanma; sayfanın tek H1 başlığını şablon basar. Ara başlıklar <h2> ve <h3> olmalı.',
            '- Yazının sonunda kısa bir harekete geçirici paragraf (CTA) olsun: telefon/WhatsApp ile iletişim.',
            '',
            'ÇIKTI KURALI: yalnızca geçerli JSON döndür. Markdown, kod bloğu veya açıklama ekleme.',
            'Şema: {"title":"","slug":"","meta_title":"","meta_description":"","body_html":"","faqs":[{"q":"","a":""}],"image_suggestion":""}',
        ]);
    }

    /** @param array<string, mixed> $topic */
    private function userPrompt(BlogCategory $category, array $topic, int $minWords, int $maxWords): string
    {
        $lines = [];
        $lines[] = 'BAŞLIK: '.$topic['title'];
        $lines[] = 'KATEGORİ: '.$category->name;

        if (filled($topic['primary_keyword'] ?? null)) {
            $lines[] = 'ANA ANAHTAR KELİME: '.$topic['primary_keyword'];
            $lines[] = 'Bu kelime başlıkta, meta açıklamada ve gövdede doğal biçimde geçsin. Kelimeyi tıka basa tekrarlama.';
        }

        if (! empty($topic['outline'])) {
            $lines[] = '';
            $lines[] = 'ÖNERİLEN BÖLÜMLER (H2 olarak kullan):';

            foreach ($topic['outline'] as $item) {
                $lines[] = '- '.$item;
            }
        }

        $lines[] = '';
        $lines[] = 'UZUNLUK: '.$minWords.'-'.$maxWords.' kelime. Bu bir alt sınırdır, kısa yazma.';
        $lines[] = 'Bunu tutturmak için EN AZ 5 adet <h2> bölümü yaz ve her bölümde en az 2 dolu paragraf kullan.';
        $lines[] = 'Maddeleri tek kelimeyle geçme; her maddeyi bir cümleyle açıkla.';
        $lines[] = 'YAPI: kısa giriş paragrafı → H2 bölümleri (gerekirse H3 alt başlık) → kapanış CTA paragrafı.';
        $lines[] = 'Listeler için <ul><li> kullan. Gerekirse <table> ile karşılaştırma tablosu ekle.';
        $lines[] = '';
        $lines[] = 'meta_title en fazla 60 karakter, meta_description en fazla 155 karakter olsun.';
        $lines[] = 'faqs: 3-5 adet, bu yazıya özgü gerçek sorular.';
        $lines[] = 'image_suggestion: yazıya uygun görselin kısa ALT metni (Türkçe, tek cümle).';
        $lines[] = '';
        $lines[] = 'KALKIŞ NOKTALARIMIZ: '.BusinessContext::regions().'.';
        $lines[] = 'Kalkış/biniş yerinden söz ederken yalnız bu gerçek yer adlarını kullan.';

        return implode("\n", $lines);
    }
}
