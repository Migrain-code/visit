<?php

namespace Tests\Feature;

use App\Models\AiGeneration;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Province;
use App\Models\SeoKeyword;
use App\Models\Setting;
use App\Services\Ai\AiClient;
use App\Services\Ai\AiException;
use App\Services\Ai\ArticleGenerator;
use App\Services\Ai\ContentSanitizer;
use App\Services\Ai\TopicGenerator;
use App\Services\Seo\CatalogKeywordBuilder;
use App\Services\Seo\CommercialKeywordAssigner;
use App\Services\Seo\TargetSynchroniser;
use App\Support\SeoConfig;
use App\Support\TurkishText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiPipelineTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'seo.ai.api_key' => 'test-key',
            'seo.ai.base_url' => 'https://openrouter.ai/api/v1',
            'seo.ai.model' => 'test/model',
        ]);
    }

    private function fakeAi(array $payload): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($payload, JSON_UNESCAPED_UNICODE)]]],
            ]),
        ]);
    }

    // ---------- İstemci ----------

    public function test_ai_actions_are_hidden_without_a_key(): void
    {
        config(['seo.ai.api_key' => null]);

        $this->assertFalse((new AiClient)->isConfigured());
    }

    public function test_api_key_never_appears_in_the_audit_record(): void
    {
        $this->fakeAi(['topics' => []]);

        app(TopicGenerator::class)->generate(BlogCategory::first(), 1);

        $generation = AiGeneration::first();
        $this->assertNotNull($generation);
        $this->assertStringNotContainsString('test-key', json_encode($generation->input));
        $this->assertStringNotContainsString('test-key', (string) $generation->output);
    }

    public function test_every_call_is_audited(): void
    {
        $this->fakeAi(['topics' => []]);

        app(TopicGenerator::class)->generate(BlogCategory::first(), 1);

        $generation = AiGeneration::first();
        $this->assertSame('blog.topics', $generation->operation);
        $this->assertSame(AiGeneration::STATUS_SUCCESS, $generation->status);
        $this->assertSame('test/model', $generation->model);
    }

    public function test_bad_json_is_retried_once_then_recorded_as_failed(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => 'bu JSON değil']]],
            ]),
        ]);

        try {
            (new AiClient)->json('blog.topics', 'sys', 'user');
            $this->fail('istisna bekleniyordu');
        } catch (AiException) {
            // beklenen
        }

        // Bir kez yeniden denendi → toplam 2 istek.
        Http::assertSentCount(2);
        $this->assertSame(AiGeneration::STATUS_FAILED, AiGeneration::first()->status);
    }

    // ---------- Konu üretimi + filtreler ----------

    public function test_topic_prompt_contains_all_existing_titles_and_the_ban_list(): void
    {
        $this->fakeAi(['topics' => []]);

        app(TopicGenerator::class)->generate(BlogCategory::first(), 1);

        $prompt = AiGeneration::first()->input['user'];

        // Mevcut TÜM başlıklar kırpılmadan verilmeli (spec §3.3).
        $this->assertStringContainsString('- Ayder Yaylası Turu', $prompt);
        $this->assertStringContainsString('- Ardeşen Çıkışlı Turlar', $prompt);
        // Örnekli permütasyon yasağı (spec §7.2).
        $this->assertStringContainsString('YASAK', $prompt);
        $this->assertStringContainsString('soru eki eklemek', $prompt);
        $this->assertStringContainsString('{"topics":[]}', $prompt);
        // YALNIZ sahipsiz kelimeler (spec §7.3).
        $this->assertStringContainsString('henüz bir içeriğe atanmamıştır', $prompt);
        $this->assertStringContainsString('- zilkale nerede nasıl gidilir', $prompt);
    }

    public function test_owned_keywords_are_not_offered_to_the_topic_generator(): void
    {
        // Tur sayfasının sahiplendiği ticari kelime blog havuzuna VERİLMEZ (spec §7.3).
        app(TargetSynchroniser::class)->sync();
        app(CommercialKeywordAssigner::class)->assign();

        $this->fakeAi(['topics' => []]);
        app(TopicGenerator::class)->generate(BlogCategory::first(), 1);

        $prompt = AiGeneration::first()->input['user'];

        $this->assertStringNotContainsString('- günübirlik ayder turu', $prompt);
        $this->assertStringContainsString('- zilkale nerede nasıl gidilir', $prompt);
    }

    public function test_business_context_follows_the_active_provinces(): void
    {
        // Koda gömülü şehir adı YOK: iş tanımı panelde aktif olan illerden türetilir.
        Province::where('slug', '!=', 'rize')->update(['is_active' => false]);

        $this->fakeAi(['topics' => []]);
        app(TopicGenerator::class)->generate(BlogCategory::first(), 1);

        $system = AiGeneration::first()->input['system'];

        $this->assertStringContainsString('Rize çıkışlı', $system);
        $this->assertStringContainsString('seyahat acentesi', $system);
        $this->assertStringNotContainsString('Trabzon', $system);
        $this->assertStringNotContainsString('Tekirdağ', $system);
        $this->assertStringNotContainsString('montaj', TurkishText::lower($system));
    }

    public function test_duplicate_topics_are_rejected_with_a_reason(): void
    {
        $this->fakeAi(['topics' => [
            ['title' => 'Ayder Yaylası Turu', 'slug' => 'ayder-yaylasi-turu-2', 'primary_keyword' => 'x', 'outline' => []],
        ]]);

        $result = app(TopicGenerator::class)->generate(BlogCategory::first(), 1);

        $this->assertSame([], $result['accepted']);
        $this->assertCount(1, $result['rejected']);
        // Sessiz eleme YOK: gerekçe taşınır (spec §3.4).
        $this->assertSame('duplicate_title', $result['rejected'][0]['reason_code']);
        $this->assertNotEmpty($result['rejected'][0]['reason']);
    }

    public function test_topic_targeting_a_keyword_owned_by_a_tour_page_is_rejected(): void
    {
        // Tur sayfasının kelimesini hedefleyen blog, o sayfayı yer (cannibalization).
        app(TargetSynchroniser::class)->sync();
        app(CatalogKeywordBuilder::class)->build();

        $this->fakeAi(['topics' => [[
            'title' => 'Kaplıca Dahil Yayla Gezisi Kaç Lira Tutar',
            'slug' => 'kaplica-dahil-yayla-gezisi-kac-lira',
            'primary_keyword' => 'ayder yaylası turu fiyatları',
            'outline' => [],
        ]]]);

        $result = app(TopicGenerator::class)->generate(BlogCategory::first(), 1);

        $this->assertSame([], $result['accepted']);
        $this->assertSame('cannibalization', $result['rejected'][0]['reason_code']);
    }

    public function test_two_similar_candidates_in_one_batch_are_deduplicated(): void
    {
        $this->fakeAi(['topics' => [
            ['title' => 'Sümela Manastırına Nasıl Çıkılır', 'slug' => 'a', 'primary_keyword' => 'sümela manastırına nasıl çıkılır', 'outline' => []],
            ['title' => 'Sümela Manastırına Çıkılırken Bilinenler', 'slug' => 'b', 'primary_keyword' => 'zilkale nerede nasıl gidilir', 'outline' => []],
        ]]);

        $result = app(TopicGenerator::class)->generate(BlogCategory::first(), 2);

        $this->assertCount(1, $result['accepted']);
        $this->assertSame('duplicate_in_batch', $result['rejected'][0]['reason_code']);
    }

    public function test_a_good_topic_is_accepted(): void
    {
        $this->fakeAi(['topics' => [[
            'title' => 'Zilkale Ziyareti İçin Pratik Bilgiler',
            'slug' => 'gobeklitepe-ziyareti-pratik-bilgiler',
            'primary_keyword' => 'zilkale nerede nasıl gidilir',
            'search_intent' => 'informational',
            'outline' => ['Ulaşım', 'Giriş ücreti', 'Gezi süresi'],
        ]]]);

        $result = app(TopicGenerator::class)->generate(BlogCategory::first(), 1);

        $this->assertCount(1, $result['accepted']);
        $this->assertSame('zilkale nerede nasıl gidilir', $result['accepted'][0]['primary_keyword']);
    }

    // ---------- Yazı üretimi + temizleme ----------

    public function test_article_output_is_sanitised(): void
    {
        $this->fakeAi([
            'title' => 'Zilkale Gezi Notları',
            'slug' => 'Zilkale Gezi Notları!',
            'meta_title' => str_repeat('çok uzun başlık ', 10),
            'meta_description' => str_repeat('çok uzun açıklama ', 30),
            'body_html' => '<h1>Başlık</h1><p>Metin <a href="https://spam.example">spam linki</a></p>'
                .'<script>alert(1)</script><p onclick="x()">tıkla</p>',
            'faqs' => [['q' => 'Soru?', 'a' => 'Cevap.']],
            'image_suggestion' => 'Zilkale kazı alanı',
        ]);

        $blog = app(ArticleGenerator::class)->generate(BlogCategory::first(), [
            'title' => 'Zilkale Gezi Notları',
            'slug' => 'gobeklitepe-gezi-notlari',
            'primary_keyword' => 'zilkale nerede nasıl gidilir',
            'outline' => [],
        ]);

        // Slug ASCII (spec §10.1)
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $blog->slug);
        // Uzunluk sınırları kod tarafında zorlanır — modele güvenilmez.
        $this->assertLessThanOrEqual(60, mb_strlen($blog->meta_title));
        $this->assertLessThanOrEqual(156, mb_strlen($blog->meta_description));
        // Gövdeden <a>, <script>, <h1> ve olay işleyicileri SÖKÜLÜR (spec §3.6, §10.1).
        $this->assertStringNotContainsString('<a ', $blog->body_html);
        $this->assertStringNotContainsString('spam.example', $blog->body_html);
        $this->assertStringNotContainsString('<script', $blog->body_html);
        $this->assertStringNotContainsString('<h1', $blog->body_html);
        $this->assertStringNotContainsString('onclick', $blog->body_html);
        $this->assertStringContainsString('spam linki', $blog->body_html, 'link metni korunmalı');

        $this->assertSame(Blog::STATUS_DRAFT, $blog->status, 'yazı TASLAK olarak kaydedilmeli');
        $this->assertSame(Blog::SOURCE_AI, $blog->source);
    }

    public function test_generated_article_takes_ownership_of_its_keyword(): void
    {
        $this->fakeAi(['title' => 'Uzungöl Gezisi', 'slug' => 'uzungol-gezisi', 'body_html' => '<p>x</p>', 'faqs' => []]);

        $keyword = SeoKeyword::where('keyword', 'uzungöl\'de ne yapılır')->firstOrFail();
        $this->assertFalse($keyword->hasOwner());

        $blog = app(ArticleGenerator::class)->generate(BlogCategory::first(), [
            'title' => 'Uzungöl Gezisi',
            'slug' => 'uzungol-gezisi',
            'primary_keyword' => 'uzungöl\'de ne yapılır',
            'outline' => [],
        ]);

        // Kelime havuzdan DÜŞER (spec §7.3).
        $this->assertSame($blog->getKey(), $keyword->refresh()->owner_blog_id);
        $this->assertSame(SeoKeyword::ASSIGN_ACTIVE, $keyword->assignment_status);
    }

    public function test_sanitiser_cuts_on_word_boundaries(): void
    {
        $sanitizer = new ContentSanitizer;

        $title = $sanitizer->metaTitle('Rize Ardeşen çıkışlı rehberli Ayder ve Uzungöl yayla turları düzenliyoruz');
        $this->assertLessThanOrEqual(60, mb_strlen($title));
        $this->assertStringEndsNotWith(' ', $title);
        // Kelime ortasından kesilmemeli.
        $this->assertStringContainsString('Rize', $title);
    }

    // ---------- Komutlar ----------

    public function test_generate_command_is_silent_when_ai_is_not_configured(): void
    {
        config(['seo.ai.api_key' => null]);

        $this->artisan('blog:generate')->assertSuccessful();
        $this->assertSame(0, Blog::count());
    }

    public function test_generate_command_explains_why_nothing_was_produced(): void
    {
        SeoConfig::set('blog_daily_enabled', true);
        Setting::flush();

        $this->fakeAi(['topics' => [
            ['title' => 'Ayder Yaylası Turu', 'slug' => 'x', 'primary_keyword' => 'y', 'outline' => []],
        ]]);

        // Sessizce başarısız OLMAZ: sebebi ve nereye bakılacağını söyler (spec §3.4).
        $this->artisan('blog:generate')
            ->expectsOutputToContain('Boştaki kelime (havuz)')
            ->assertSuccessful();
    }

    public function test_publish_due_command_publishes_scheduled_drafts(): void
    {
        $due = Blog::create(['title' => 'Zamanı gelen', 'slug' => 'zamani-gelen', 'status' => Blog::STATUS_DRAFT, 'publish_at' => now()->subMinute()]);
        $future = Blog::create(['title' => 'Gelecek', 'slug' => 'gelecek', 'status' => Blog::STATUS_DRAFT, 'publish_at' => now()->addDay()]);

        $this->artisan('blog:publish-due')->assertSuccessful();

        $this->assertSame(Blog::STATUS_PUBLISHED, $due->refresh()->status);
        $this->assertSame(Blog::STATUS_DRAFT, $future->refresh()->status);
    }

    public function test_daily_generation_does_not_pile_onto_existing_stock(): void
    {
        SeoConfig::set('blog_daily_enabled', true);
        Setting::flush();

        // Bir aylık stok üretilmiş olsun.
        foreach (range(1, 10) as $day) {
            Blog::create([
                'title' => 'Planlı yazı '.$day,
                'slug' => 'planli-yazi-'.$day,
                'body_html' => '<p>x</p>',
                'status' => Blog::STATUS_DRAFT,
                'publish_at' => now()->addDays($day),
            ]);
        }

        Http::fake(); // AI çağrısı YAPILMAMALI

        $this->artisan('blog:generate')
            ->expectsOutputToContain('Stok yeterli')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame(10, Blog::count(), 'stok varken yeni yazı üretilmemeli');
    }

    public function test_generation_resumes_when_stock_runs_low(): void
    {
        SeoConfig::set('blog_daily_enabled', true);
        Setting::flush();

        Blog::create([
            'title' => 'Tek planlı yazı', 'slug' => 'tek-planli-yazi', 'body_html' => '<p>x</p>',
            'status' => Blog::STATUS_DRAFT, 'publish_at' => now()->addDay(),
        ]);

        $this->fakeAi(['topics' => [[
            'title' => 'Zilkale Ziyareti İçin Pratik Bilgiler',
            'slug' => 'gobeklitepe-ziyareti-pratik-bilgiler',
            'primary_keyword' => 'zilkale nerede nasıl gidilir',
            'outline' => [],
        ]]]);

        // Stok 3 günün altında → üretim yapılır.
        $this->artisan('blog:generate')->assertSuccessful();

        // En az bir AI çağrısı yapıldı (konu üretimi) ve yeni bir taslak eklendi.
        $this->assertGreaterThan(0, Http::recorded()->count());
        $this->assertSame(2, Blog::count(), 'stok azken yeni yazı üretilmeli');
    }
}
