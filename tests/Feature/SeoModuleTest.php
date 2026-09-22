<?php

namespace Tests\Feature;

use App\Models\AiCrawlerVisit;
use App\Models\Blog;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Models\SeoAnalysis;
use App\Models\SeoKeyword;
use App\Models\SeoTarget;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Models\TourDeparture;
use App\Services\Discovery\LlmsTxtGenerator;
use App\Services\Seo\CatalogKeywordBuilder;
use App\Services\Seo\CommercialKeywordAssigner;
use App\Services\Seo\ContentRegistry;
use App\Services\Seo\ContentScorer;
use App\Services\Seo\KeywordPool;
use App\Services\Seo\RedirectSuggester;
use App\Services\Seo\TargetSynchroniser;
use App\Support\SeoIssue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SeoModuleTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /**
     * llms.txt dosyalarını üretir, içeriklerini döndürür ve public/ klasörünü ESKİ hâline getirir.
     *
     * Üretici gerçek public/ klasörüne yazar; test arkasında dosya bırakmamalı ve var olan
     * dosyayı test verisiyle ezmemelidir.
     *
     * @return array{index: string, full: string}
     */
    private function generateLlms(): array
    {
        $paths = ['index' => public_path('llms.txt'), 'full' => public_path('llms-full.txt')];
        $before = array_map(fn (string $path) => is_file($path) ? file_get_contents($path) : null, $paths);

        try {
            app(LlmsTxtGenerator::class)->generate();

            return array_map(fn (string $path) => (string) file_get_contents($path), $paths);
        } finally {
            foreach ($paths as $key => $path) {
                $before[$key] === null ? @unlink($path) : file_put_contents($path, $before[$key]);
            }
        }
    }

    // ---------- Gözlem & yönlendirme (modül 8) ----------

    public function test_404_is_logged_without_breaking_the_response(): void
    {
        $this->get('/boyle-bir-sayfa-yok')->assertNotFound();

        $log = NotFoundLog::where('path', '/boyle-bir-sayfa-yok')->first();

        $this->assertNotNull($log);
        $this->assertSame(1, $log->hits);

        // Aynı adres tekrar istenince sayaç ARTAR, yeni satır açılmaz.
        $this->get('/boyle-bir-sayfa-yok')->assertNotFound();
        $this->assertSame(2, $log->refresh()->hits);
        $this->assertSame(1, NotFoundLog::where('path', '/boyle-bir-sayfa-yok')->count());
    }

    public function test_bot_probes_are_not_logged(): void
    {
        // Gürültü gerçek hataları gömer (spec §7.13).
        foreach (['/wp-admin/setup-config.php', '/.env', '/xmlrpc.php', '/phpmyadmin/index.php'] as $probe) {
            $this->get($probe)->assertNotFound();
        }

        $this->assertSame(0, NotFoundLog::count());
    }

    public function test_redirect_hash_is_filled_by_the_model(): void
    {
        // Elle hash verilmiyor; model doldurmalı (spec §7.10).
        $redirect = Redirect::create([
            'from_path' => '/Eski/Adres/',
            'to_path' => '/rize/ardesen',
        ]);

        $this->assertSame(md5('/eski/adres'), $redirect->from_hash);
        $this->assertSame('/eski/adres', $redirect->from_path);

        $this->get('/eski/adres')->assertRedirect(url('/rize/ardesen'));
        $this->assertSame(1, $redirect->refresh()->hits);
    }

    public function test_redirect_chains_resolve_to_the_final_target(): void
    {
        Redirect::create(['from_path' => '/a', 'to_path' => '/b']);
        Redirect::create(['from_path' => '/b', 'to_path' => '/rize/ardesen']);

        $this->get('/a')->assertRedirect(url('/rize/ardesen'));
    }

    public function test_redirect_only_runs_on_404(): void
    {
        // Var olan bir sayfaya yönlendirme tanımlansa bile sayfa normal çalışır (spec §10.6).
        Redirect::create(['from_path' => '/ayder-yaylasi-turu', 'to_path' => '/']);

        $this->get('/ayder-yaylasi-turu')->assertOk();

        // Tur yayından kalkınca adres 404 olur; yönlendirme ANCAK o zaman devreye girer.
        Tour::where('slug', 'ayder-yaylasi-turu')->update(['is_active' => false]);

        $this->get('/ayder-yaylasi-turu')->assertRedirect(url('/'));
    }

    public function test_redirect_suggestion_is_deterministic_and_never_invents(): void
    {
        $suggester = app(RedirectSuggester::class);

        $suggestion = $suggester->suggest('/eski-bolum/rize/ardesen');
        $this->assertNotNull($suggestion);
        $this->assertContains($suggestion['path'], $suggester->targets()->all(), 'hedef gerçek sayfalardan biri olmalı');

        // Yazım hatalı tur adresi gerçek tur sayfasına önerilir.
        $this->assertSame('/ayder-yaylasi-turu', $suggester->suggest('/ayder-yaylasi-turlari')['path'] ?? null);

        // Hiçbir şeye benzemeyen adres için öneri ZORLANMAZ.
        $this->assertNull($suggester->suggest('/zzz-qqq-xyzabc-999'));
    }

    public function test_redirect_suggestion_never_points_to_an_unpublished_tour(): void
    {
        Tour::where('slug', 'ayder-yaylasi-turu')->update(['is_active' => false]);

        $suggester = app(RedirectSuggester::class);

        $this->assertNotContains('/ayder-yaylasi-turu', $suggester->targets()->all());
        $this->assertNotSame('/ayder-yaylasi-turu', $suggester->suggest('/ayder-yaylasi-turlari')['path'] ?? null);
    }

    public function test_ai_bot_visits_are_recorded(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; ClaudeBot/1.0)')->get('/')->assertOk();

        $visit = AiCrawlerVisit::first();
        $this->assertNotNull($visit);
        $this->assertSame('ClaudeBot', $visit->bot);
        $this->assertSame(200, $visit->last_status);
    }

    public function test_ordinary_visitors_are_not_recorded(): void
    {
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh) Safari/605.1')->get('/')->assertOk();

        $this->assertSame(0, AiCrawlerVisit::count());
    }

    // ---------- AI ajan keşfi (modül 9) ----------

    public function test_discovery_headers_are_present_on_get_and_head(): void
    {
        foreach (['get', 'head'] as $method) {
            $response = $this->$method('/');

            $response->assertOk();
            $response->assertHeader('Content-Signal', 'search=yes, ai-input=yes, ai-train=no');
            $this->assertStringContainsString('rel="llms-txt"', $response->headers->get('Link'));
            $this->assertStringContainsString('rel="sitemap"', $response->headers->get('Link'));
        }
    }

    public function test_robots_txt_welcomes_ai_agents_and_points_to_llms(): void
    {
        $body = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('User-agent: GPTBot', $body);
        $this->assertStringContainsString('User-agent: ClaudeBot', $body);
        $this->assertStringContainsString(url('/llms.txt'), $body);
        $this->assertStringContainsString('Sitemap: '.url('/sitemap.xml'), $body);
        $this->assertStringContainsString('Disallow: /admin', $body);
    }

    public function test_llms_txt_is_built_from_the_database_only(): void
    {
        // Yayında olmayan tur dosyaya GİRMEZ.
        Tour::where('slug', 'batum-tiflis-turu')->update(['is_active' => false]);

        ['index' => $fresh, 'full' => $full] = $this->generateLlms();

        // Her tur ve kategori veritabanından gelmeli.
        foreach (Tour::where('is_active', true)->get() as $tour) {
            $this->assertStringContainsString('['.$tour->title.']('.url('/'.$tour->slug).')', $fresh);
            $this->assertStringContainsString('## '.$tour->title, $full);
        }

        foreach (TourCategory::where('is_active', true)->get() as $category) {
            $this->assertStringContainsString('['.$category->name.']('.url($category->path()).')', $fresh);
        }

        $this->assertStringNotContainsString('Batum Tiflis Turu', $fresh);
        $this->assertStringNotContainsString('Batum Tiflis Turu', $full);

        foreach (['## Turlar', '## Tur kategorileri', '## Kalkış noktaları', '## Diğer sayfalar'] as $heading) {
            $this->assertStringContainsString($heading, $fresh);
        }

        $this->assertStringContainsString(site_name(), $fresh);
        $this->assertStringContainsString(url('/llms-full.txt'), $fresh);
        $this->assertStringContainsString('['.'Ardeşen]('.url('/rize/ardesen').')', $fresh);
    }

    public function test_llms_txt_leaves_the_public_folder_as_it_found_it(): void
    {
        $existed = is_file(public_path('llms.txt'));
        $before = $existed ? file_get_contents(public_path('llms.txt')) : null;

        $this->generateLlms();

        $this->assertSame($existed, is_file(public_path('llms.txt')));
        $this->assertSame($before, $existed ? file_get_contents(public_path('llms.txt')) : null);
    }

    public function test_llms_txt_lists_tour_prices_and_never_invents_one(): void
    {
        $priced = Tour::where('slug', 'ayder-yaylasi-turu')->firstOrFail();
        $priced->update(['price' => 12345, 'currency' => 'TRY', 'duration_days' => 3, 'duration_nights' => 2]);

        // Fiyatı girilmemiş tur: dosyada fiyat UYDURULMAZ.
        $unpriced = Tour::where('slug', 'uzungol-turu')->firstOrFail();
        $unpriced->update(['price' => null, 'old_price' => null]);

        // Yabancı para birimiyle satılan tur (katalogda yok; bu test için açılır).
        Tour::create([
            'title' => 'Kutaisi Turu', 'slug' => 'kutaisi-turu', 'price' => 185, 'currency' => 'EUR',
            'duration_days' => 2, 'duration_nights' => 1, 'is_active' => true,
        ]);

        ['index' => $index, 'full' => $full] = $this->generateLlms();

        $line = fn (string $text, string $needle) => collect(explode("\n", $text))->first(fn ($l) => str_contains($l, $needle));

        $this->assertStringContainsString('(2 Gece 3 Gün, kişi başı 12.345 ₺)', $line($index, '[Ayder Yaylası Turu]'));
        $this->assertStringNotContainsString('kişi başı', $line($index, '[Uzungöl Turu]'));
        $this->assertStringNotContainsString('₺', $line($index, '[Uzungöl Turu]'));

        // Yabancı para birimi olduğu gibi yazılır; TL'ye ÇEVRİLMEZ.
        $this->assertStringContainsString('(1 Gece 2 Gün, kişi başı 185 €)', $line($index, '[Kutaisi Turu]'));
        $this->assertStringNotContainsString('₺', $line($index, '[Kutaisi Turu]'));

        // Tam metin: süre, fiyat, program ve fiyata dahil olanlar tur kaydından gelir.
        $section = Str::before(Str::after($full, '## Ayder Yaylası Turu'), "\n## ");

        $this->assertStringContainsString('URL: '.url('/ayder-yaylasi-turu'), $section);
        $this->assertStringContainsString('Süre: 2 Gece 3 Gün', $section);
        $this->assertStringContainsString('Kişi başı fiyat: 12.345 ₺', $section);
        $this->assertStringContainsString('- '.$priced->itinerary[0]['title'].':', $section);
        $this->assertStringContainsString('Fiyata dahil: '.$priced->included[0], $section);
        $this->assertStringContainsString('Fiyata dahil değil: '.$priced->excluded[0], $section);

        $unpricedSection = Str::before(Str::after($full, '## Uzungöl Turu'), "\n## ");
        $this->assertStringNotContainsString('Kişi başı fiyat', $unpricedSection);
    }

    public function test_llms_txt_lists_only_bookable_departures(): void
    {
        // Örnek seferleri gizle; yalnız bu testin seferleri değerlendirilsin.
        TourDeparture::query()->update(['is_public' => false]);

        $tour = Tour::where('slug', 'ayder-yaylasi-turu')->firstOrFail();
        $hiddenTour = Tour::where('slug', 'batum-tiflis-turu')->firstOrFail();
        $hiddenTour->update(['is_active' => false]);

        $departure = fn (array $attributes) => TourDeparture::create($attributes + [
            'tour_id' => $tour->getKey(), 'starts_at' => now()->addDays(10)->setTime(20, 0), 'status' => 'open', 'is_public' => true,
        ]);

        // Her seferin fiyatı farklı: dosyada hangisinin geçtiği fiyattan anlaşılır.
        $bookable = $departure(['price' => 9871]);
        $departure(['price' => 9872, 'starts_at' => now()->subDays(5)]);      // geçmiş
        $departure(['price' => 9873, 'status' => 'closed']);                  // kayıt kapalı
        $departure(['price' => 9874, 'status' => 'cancelled']);               // iptal
        $departure(['price' => 9875, 'is_public' => false]);                  // sitede gizli
        $departure(['price' => 9876, 'tour_id' => $hiddenTour->getKey()]);    // turu yayında değil

        $index = $this->generateLlms()['index'];

        $this->assertStringContainsString('## Yaklaşan tur tarihleri', $index);
        $this->assertStringContainsString(
            '- '.$bookable->refresh()->date_range_label.': [Ayder Yaylası Turu]('.url('/ayder-yaylasi-turu').') — kişi başı 9.871 ₺',
            $index,
        );
        $this->assertStringContainsString('Güncel takvim: '.url('/tur-takvimi'), $index);

        foreach (['9.872', '9.873', '9.874', '9.875', '9.876'] as $price) {
            $this->assertStringNotContainsString($price, $index, 'satışta olmayan sefer listelenmemeli');
        }

        $section = Str::before(Str::after($index, '## Yaklaşan tur tarihleri'), "\n## ");
        $this->assertSame(1, substr_count($section, "\n- "), 'yalnız satıştaki sefer listelenmeli');
    }

    public function test_llms_txt_has_no_departure_section_when_nothing_is_on_sale(): void
    {
        // Satışta sefer yoksa tarih UYDURULMAZ: bölüm hiç basılmaz.
        TourDeparture::query()->update(['status' => 'closed']);

        $index = $this->generateLlms()['index'];

        $this->assertStringNotContainsString('## Yaklaşan tur tarihleri', $index);
        $this->assertStringNotContainsString('Güncel takvim', $index);
        $this->assertStringContainsString('## Turlar', $index);
    }

    public function test_departure_without_its_own_price_falls_back_to_the_tour_price(): void
    {
        TourDeparture::query()->update(['is_public' => false]);

        $tour = Tour::where('slug', 'gunubirlik-batum-turu')->firstOrFail();
        $tour->update(['price' => 2345]);

        TourDeparture::create(['tour_id' => $tour->getKey(), 'starts_at' => now()->addDays(7)->setTime(5, 0)]);

        $index = $this->generateLlms()['index'];

        $this->assertStringContainsString('[Günübirlik Batum Turu]('.url('/gunubirlik-batum-turu').') — kişi başı 2.345 ₺', $index);
    }

    // ---------- Deterministik skor (modül 2) ----------

    public function test_scorer_never_calls_ai_and_is_repeatable(): void
    {
        $content = app(ContentRegistry::class)->all()->firstWhere('contentType', 'tour');
        $scorer = app(ContentScorer::class);

        $first = $scorer->score($content);
        $second = $scorer->score($content);

        $this->assertSame($first, $second, 'skor deterministik olmalı');
        $this->assertGreaterThanOrEqual(0, $first['score']);
        $this->assertLessThanOrEqual(100, $first['score']);
        $this->assertSame(['meta', 'content', 'technical', 'links'], array_keys($first['scores']));
    }

    public function test_score_weights_sum_to_one_hundred(): void
    {
        $this->assertSame(100, array_sum(config('seo.score.weights')));
    }

    public function test_issue_strings_are_fixed_constants(): void
    {
        // Dashboard sayımları TAM metinle eşleşir (spec §10.4).
        $content = app(ContentRegistry::class)->all()->firstWhere('contentType', 'tour');
        $result = app(ContentScorer::class)->score($content);

        foreach ($result['issues'] as $issue) {
            $this->assertContains($issue, SeoIssue::all(), "'{$issue}' sabit listede yok");
        }
    }

    public function test_scoring_command_persists_results(): void
    {
        $this->artisan('seo:score')->assertSuccessful();

        $this->assertGreaterThan(0, SeoAnalysis::count());

        $analysis = SeoAnalysis::where('content_type', 'tour')->first();
        $this->assertNotNull($analysis->analyzed_at);
        $this->assertIsArray($analysis->scores);
    }

    // ---------- Hedef sayfalar (modül 1) ----------

    public function test_targets_are_derived_from_real_content(): void
    {
        app(TargetSynchroniser::class)->sync();

        $this->assertGreaterThan(0, SeoTarget::count());

        $tourTargets = SeoTarget::where('target_type', 'tour')->get();
        $this->assertCount(Tour::count(), $tourTargets);

        foreach ($tourTargets as $target) {
            $this->assertTrue(
                Tour::where('slug', ltrim($target->url, '/'))->exists(),
                'hedef gerçek bir tura karşılık gelmeli',
            );
            $this->assertSame('tour', $target->content_type);
        }

        // Kategori hedefleri "/turlar/{slug}" adresindedir; liste sayfası da aynı tiptedir.
        foreach (SeoTarget::where('target_type', 'category')->whereNotNull('content_id')->get() as $target) {
            $this->assertSame(TourCategory::findOrFail($target->content_id)->path(), $target->url);
        }

        foreach (['/', '/turlar', '/tur-takvimi', '/rize', '/rize/ardesen', '/kvkk-aydinlatma-metni'] as $url) {
            $this->assertDatabaseHas('seo_targets', ['url' => $url, 'status' => true]);
        }
    }

    public function test_every_active_target_is_a_page_that_really_answers(): void
    {
        app(TargetSynchroniser::class)->sync();

        // Hedef UYDURULMAZ: aktif her hedef ziyaretçiye 200 dönen bir sayfadır.
        $targets = SeoTarget::where('status', true)->whereIn('target_type', ['home', 'tour', 'category', 'province', 'page'])->get();

        $this->assertGreaterThan(15, $targets->count());

        foreach ($targets as $target) {
            $this->get($target->url)->assertOk();
        }
    }

    public function test_target_is_deactivated_not_deleted_when_a_tour_is_unpublished(): void
    {
        app(TargetSynchroniser::class)->sync();

        Tour::where('slug', 'ayder-yaylasi-turu')->update(['is_active' => false]);
        $result = app(TargetSynchroniser::class)->sync();

        $target = SeoTarget::where('url', '/ayder-yaylasi-turu')->first();

        $this->assertNotNull($target, 'hedef SİLİNMEMELİ (spec §10.9)');
        $this->assertFalse((bool) $target->status);
        $this->assertSame(0, $result['created'], 'ikinci senkron yeni hedef açmamalı');

        // Elle eklenen "custom" hedefe dokunulmaz.
        $custom = SeoTarget::create(['name' => 'Kampanya', 'url' => '/kampanya', 'target_type' => 'custom', 'status' => true]);
        app(TargetSynchroniser::class)->sync();

        $this->assertTrue((bool) $custom->refresh()->status);
    }

    // ---------- Katalog kelimeleri (tur + kategori) ----------

    public function test_catalog_keywords_are_owned_by_the_tour_and_category_pages(): void
    {
        app(TargetSynchroniser::class)->sync();

        $result = app(CatalogKeywordBuilder::class)->build();

        $this->assertSame(['created', 'assigned', 'deactivated', 'items'], array_keys($result));
        $this->assertContains('Ayder Yaylası Turu', $result['items']);
        $this->assertContains('Yayla Turları', $result['items']);
        // Tur başına 3, kategori başına 2 kelime.
        $this->assertSame(Tour::count() * 3 + TourCategory::count() * 2, $result['created']);
        $this->assertSame($result['created'], $result['assigned']);

        foreach (['ayder yaylası turu', 'ayder yaylası turu fiyatları', 'ayder yaylası turu programı'] as $text) {
            $keyword = SeoKeyword::where('keyword', $text)->firstOrFail();

            // Ticari kelimenin sahibi para kazandıran TUR sayfasıdır (spec §3.1).
            $this->assertSame('/ayder-yaylasi-turu', $keyword->target?->url, $text);
            $this->assertSame(SeoKeyword::ASSIGN_ACTIVE, $keyword->assignment_status);
            $this->assertStringStartsWith('Katalog otomasyonu', (string) $keyword->note);
        }

        foreach (['yayla turları', 'yayla turları fiyatları'] as $text) {
            $this->assertSame('/turlar/yayla-turlari', SeoKeyword::where('keyword', $text)->firstOrFail()->target?->url, $text);
        }

        // Kategori için "programı" kalıbı üretilmez: kategorinin programı olmaz.
        $this->assertDatabaseMissing('seo_keywords', ['keyword' => 'yayla turları programı']);

        // Sahiplenilen kelime blog havuzuna DÜŞMEZ.
        $this->assertFalse(app(KeywordPool::class)->available()->contains('keyword', 'ayder yaylası turu fiyatları'));
    }

    public function test_catalog_keywords_use_turkish_lowercase(): void
    {
        app(TargetSynchroniser::class)->sync();
        app(CatalogKeywordBuilder::class)->build();

        // mb_strtolower "Ş/Ç/İ" harflerinde aranan hâlinden farklı bir kelime üretir.
        $this->assertDatabaseHas('seo_keywords', ['keyword' => 'zilkale ve palovit şelalesi turu fiyatları']);
        $this->assertDatabaseHas('seo_keywords', ['keyword' => 'sümela karaca mağarası hamsiköy turu programı']);
        $this->assertDatabaseHas('seo_keywords', ['keyword' => 'göl ve vadi turları']);
        $this->assertDatabaseHas('seo_keywords', ['keyword' => 'batum ve gürcistan turları fiyatları']);
    }

    public function test_catalog_build_is_idempotent(): void
    {
        app(TargetSynchroniser::class)->sync();
        app(CatalogKeywordBuilder::class)->build();
        $count = SeoKeyword::count();

        $second = app(CatalogKeywordBuilder::class)->build();

        $this->assertSame(0, $second['created']);
        $this->assertSame(0, $second['assigned']);
        $this->assertSame(0, $second['deactivated']);
        $this->assertSame($count, SeoKeyword::count());
    }

    public function test_catalog_keywords_are_deactivated_when_a_tour_is_unpublished(): void
    {
        $this->artisan('seo:catalog-keywords')->assertSuccessful();

        $this->assertTrue((bool) SeoKeyword::where('keyword', 'ayder yaylası turu fiyatları')->value('status'));

        Tour::where('slug', 'ayder-yaylasi-turu')->update(['is_active' => false]);
        $this->artisan('seo:catalog-keywords')->assertSuccessful();

        $keyword = SeoKeyword::where('keyword', 'ayder yaylası turu fiyatları')->first();

        $this->assertNotNull($keyword, 'kelime SİLİNMEMELİ, yalnız pasife alınmalı (spec §10.9)');
        $this->assertFalse((bool) $keyword->status);
        // Başka turların kelimeleri etkilenmez.
        $this->assertTrue((bool) SeoKeyword::where('keyword', 'uzungöl turu fiyatları')->value('status'));

        // Tur tekrar yayına alınırsa kelime geri gelir.
        Tour::where('slug', 'ayder-yaylasi-turu')->update(['is_active' => true]);
        $this->artisan('seo:catalog-keywords')->assertSuccessful();

        $this->assertTrue((bool) $keyword->refresh()->status);
        $this->assertSame('/ayder-yaylasi-turu', $keyword->target?->url);
    }

    public function test_catalog_keywords_are_deactivated_when_a_category_closes(): void
    {
        app(TargetSynchroniser::class)->sync();
        app(CatalogKeywordBuilder::class)->build();

        TourCategory::where('slug', 'gol-ve-vadi-turlari')->update(['is_active' => false]);
        app(TargetSynchroniser::class)->sync();
        $result = app(CatalogKeywordBuilder::class)->build();

        $this->assertSame(2, $result['deactivated']);
        $this->assertFalse((bool) SeoKeyword::where('keyword', 'göl ve vadi turları fiyatları')->value('status'));
    }

    public function test_catalog_builder_respects_a_manual_owner(): void
    {
        app(TargetSynchroniser::class)->sync();

        // Editör "ayder yaylası turu programı" kelimesini bilinçli olarak kategori sayfasına vermiş.
        $category = SeoTarget::where('url', '/turlar/kultur-turlari')->firstOrFail();
        $manual = SeoKeyword::create(['keyword' => 'ayder yaylası turu programı', 'target_id' => $category->getKey(), 'note' => 'Elle']);

        app(CatalogKeywordBuilder::class)->build();

        $this->assertSame($category->getKey(), $manual->refresh()->target_id, 'yaşayan elle atamaya DOKUNULMAZ');
        $this->assertSame(1, SeoKeyword::where('keyword', 'ayder yaylası turu programı')->count(), 'kelime çoğaltılmamalı');

        // Sahibi yayından kalkarsa kelime sahipsiz kalmasın diye tur sayfasına döner.
        TourCategory::where('slug', 'kultur-turlari')->update(['is_active' => false]);
        app(TargetSynchroniser::class)->sync();
        app(CatalogKeywordBuilder::class)->build();

        $this->assertSame('/ayder-yaylasi-turu', $manual->refresh()->target?->url);
    }

    // ---------- Ticari kelime ataması ----------

    public function test_commercial_assigner_matches_by_distinctive_title_words(): void
    {
        app(TargetSynchroniser::class)->sync();

        $assigner = app(CommercialKeywordAssigner::class);

        // "günübirlik", "turu", "yaylası" gibi birçok turda geçen kelimeler ayırt edici DEĞİLDİR.
        $this->assertSame(['ayder'], $assigner->distinctiveTerms('Ayder Yaylası Turu'));
        $this->assertSame(['batum'], $assigner->distinctiveTerms('Günübirlik Batum Turu'));
        $this->assertSame(['pokut', 'sal'], $assigner->distinctiveTerms('Pokut ve Sal Yaylası Turu'));
        $this->assertSame(['batum', 'tiflis'], $assigner->distinctiveTerms('Batum Tiflis Turu'));
        $this->assertSame([], $assigner->distinctiveTerms('Günübirlik Yayla Turu'));

        $result = $assigner->assign();

        $this->assertSame(['assigned', 'skipped', 'details'], array_keys($result));
        $this->assertSame('/ayder-yaylasi-turu', SeoKeyword::where('keyword', 'günübirlik ayder turu')->firstOrFail()->target?->url);
        // Kelime sırası önemli değil: "…uzungöl turu fiyatları" → Uzungöl Turu.
        $this->assertSame('/uzungol-turu', SeoKeyword::where('keyword', 'günübirlik uzungöl turu fiyatları')->firstOrFail()->target?->url);
        // Yalnız o tura ait kelime tek başına yeter: "pokut" başka hiçbir turun adında geçmez.
        $this->assertSame('/pokut-ve-sal-yaylasi-turu', SeoKeyword::where('keyword', 'pokut yaylası turu fiyatları')->firstOrFail()->target?->url);
        $this->assertContains('günübirlik ayder turu → Ayder Yaylası Turu', $result['details']);
    }

    public function test_commercial_assigner_requires_every_distinctive_word_or_a_unique_one(): void
    {
        app(TargetSynchroniser::class)->sync();
        app(CommercialKeywordAssigner::class)->assign();

        // "batum" iki turda geçer: tek başına "Batum Tiflis Turu"nu değil, adı yalnız
        // "batum"dan oluşan Günübirlik Batum Turu'nu seçer.
        $this->assertSame('/gunubirlik-batum-turu', SeoKeyword::where('keyword', 'kimlikle batum turu')->firstOrFail()->target?->url);
        // Yalnız genel kelimelerden oluşan kelime hiçbir tura gitmez.
        $this->assertFalse(SeoKeyword::where('keyword', 'karadeniz günübirlik turlar')->firstOrFail()->hasOwner());
    }

    public function test_commercial_assigner_refuses_ambiguous_matches(): void
    {
        // İki tur da yalnız "ayder" ile ayırt ediliyor → hangisi olduğu BİLİNEMEZ.
        Tour::create(['title' => 'Ayder Gezisi', 'slug' => 'ayder-gezisi', 'is_active' => true]);
        app(TargetSynchroniser::class)->sync();

        $result = app(CommercialKeywordAssigner::class)->assign();

        $keyword = SeoKeyword::where('keyword', 'günübirlik ayder turu')->firstOrFail();

        $this->assertFalse($keyword->hasOwner(), 'eşitlikte kelimeye DOKUNULMAZ');
        $this->assertNotContains('günübirlik ayder turu → Ayder Yaylası Turu', $result['details']);
    }

    public function test_commercial_assigner_prefers_the_most_specific_tour(): void
    {
        Tour::create(['title' => 'Ayder Kaplıca Turu', 'slug' => 'ayder-kaplica-turu', 'is_active' => true]);
        app(TargetSynchroniser::class)->sync();

        SeoKeyword::create(['keyword' => 'ayder kaplıca turu rezervasyon', 'keyword_type' => 'COMMERCIAL_VARIANT']);

        app(CommercialKeywordAssigner::class)->assign();

        // İkisi de eşleşir; daha çok kelimesi tutan (daha özel) tur kazanır.
        $this->assertSame('/ayder-kaplica-turu', SeoKeyword::where('keyword', 'ayder kaplıca turu rezervasyon')->firstOrFail()->target?->url);
        // "kaplıca" geçmeyen kelime genel Ayder turunda kalır.
        $this->assertSame('/ayder-yaylasi-turu', SeoKeyword::where('keyword', 'günübirlik ayder turu')->firstOrFail()->target?->url);
    }

    public function test_commercial_assigner_matches_on_word_starts_only(): void
    {
        app(TargetSynchroniser::class)->sync();

        // "sal", "masal" içinde eşleşmemeli (Pokut ve Sal Yaylası Turu).
        $trap = SeoKeyword::create(['keyword' => 'masal gibi yayla turu', 'keyword_type' => 'COMMERCIAL_VARIANT']);
        // Çekim eki alan kelime eşleşir: "pokut'a", "sal'a".
        $inflected = SeoKeyword::create(['keyword' => 'pokut\'a ve sal\'a araçlı tur', 'keyword_type' => 'COMMERCIAL_VARIANT']);

        app(CommercialKeywordAssigner::class)->assign();

        $this->assertFalse($trap->refresh()->hasOwner());
        $this->assertSame('/pokut-ve-sal-yaylasi-turu', $inflected->refresh()->target?->url);
    }

    public function test_commercial_assigner_leaves_blog_keywords_and_owned_keywords_alone(): void
    {
        app(TargetSynchroniser::class)->sync();

        $blog = Blog::create(['title' => 'Test', 'slug' => 'test-yazi', 'status' => Blog::STATUS_PUBLISHED]);
        $owned = SeoKeyword::create(['keyword' => 'ayder turu erken rezervasyon', 'keyword_type' => 'COMMERCIAL_VARIANT', 'owner_blog_id' => $blog->getKey()]);

        app(CommercialKeywordAssigner::class)->assign();

        // Bilgi amaçlı kelime blog hattınındır; tur sayfasına ÇEKİLMEZ.
        $this->assertFalse(SeoKeyword::where('keyword', 'ayder yaylasında ne yapılır')->firstOrFail()->hasOwner());
        // Sahibi olan kelimeye dokunulmaz.
        $this->assertSame($blog->getKey(), $owned->refresh()->owner_blog_id);
        $this->assertNull($owned->target_id);
    }

    public function test_commercial_assigner_ignores_unpublished_tours(): void
    {
        Tour::where('slug', 'ayder-yaylasi-turu')->update(['is_active' => false]);
        app(TargetSynchroniser::class)->sync();

        app(CommercialKeywordAssigner::class)->assign();

        $this->assertFalse(SeoKeyword::where('keyword', 'günübirlik ayder turu')->firstOrFail()->hasOwner());
    }

    // ---------- Kelime & hedef sahipliği (modül 1) ----------

    public function test_a_keyword_can_have_only_one_owner(): void
    {
        app(TargetSynchroniser::class)->sync();

        $target = SeoTarget::first();
        $blog = Blog::create(['title' => 'Test', 'slug' => 'test-yazi', 'status' => Blog::STATUS_PUBLISHED]);

        $keyword = SeoKeyword::create([
            'keyword' => 'iki sahipli kelime denemesi',
            'target_id' => $target->getKey(),
            'owner_blog_id' => $blog->getKey(),
        ]);

        // Hedef kazanır, blog sahipliği DÜŞER (spec §3.1).
        $this->assertSame($target->getKey(), $keyword->target_id);
        $this->assertNull($keyword->owner_blog_id);
        $this->assertSame(SeoKeyword::ASSIGN_ACTIVE, $keyword->assignment_status);
    }

    public function test_unassigned_keyword_is_marked_as_such(): void
    {
        $keyword = SeoKeyword::create(['keyword' => 'sahipsiz deneme kelimesi']);

        $this->assertSame(SeoKeyword::ASSIGN_UNASSIGNED, $keyword->assignment_status);
        $this->assertFalse($keyword->hasOwner());
    }

    public function test_keyword_pool_excludes_assigned_and_covered_words(): void
    {
        $pool = app(KeywordPool::class);
        $before = $pool->stats();

        // Mevcut içerikte geçen bir kelime havuza GİRMEMELİ (spec §7.3).
        SeoKeyword::create(['keyword' => 'Ayder Yaylası Turu']);

        $freshPool = app(KeywordPool::class);
        $after = $freshPool->stats();

        $this->assertSame($before['free'], $after['free'], 'içerikte geçen kelime havuzu büyütmemeli');
        $this->assertSame($before['covered'] + 1, $after['covered']);

        // Tur kategorisi adı da "mevcut içerik" sayılır.
        $this->assertTrue($freshPool->isCovered('yayla turları'));

        // Sahipsiz ve içerikte geçmeyen kelime havuza girer.
        SeoKeyword::create(['keyword' => 'bambaşka bir konu öbeği xyz']);
        $this->assertSame($before['free'] + 1, app(KeywordPool::class)->stats()['free']);
    }

    public function test_keyword_pool_loads_content_once(): void
    {
        // Kelime başına tam tarama 159 kelimede 636 sorgu eder (spec §7.3).
        $pool = app(KeywordPool::class);

        DB::enableQueryLog();
        $pool->available();
        $first = count(DB::getQueryLog());

        DB::flushQueryLog();
        $pool->available();
        $second = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(15, $first, 'içerik tek seferde yüklenmeli');
        $this->assertLessThanOrEqual(2, $second, 'ikinci çağrı önbellekten gelmeli');
    }
}
