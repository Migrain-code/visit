<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\District;
use App\Models\InternalLinkRule;
use App\Models\Province;
use App\Models\SeoAnalysis;
use App\Models\SeoKeyword;
use App\Models\Setting;
use App\Models\Tour;
use App\Services\InternalLink\RuleBuilder;
use App\Services\Seo\CommercialKeywordAssigner;
use App\Services\Seo\ContentRegistry;
use App\Services\Seo\KeywordPool;
use App\Services\Seo\LocationKeywordBuilder;
use App\Services\Seo\TargetSynchroniser;
use App\Support\SeoConfig;
use App\Support\TurkishText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Yalnız ERİŞİLEBİLİR bölgelerin işlenmesi.
 *
 * Erişilebilir = ilçe aktif VE bağlı olduğu il aktif. Aksi hâlde adres 404 döner;
 * o sayfayı skorlamak, kelime üretmek veya link hedefi yapmak boşunadır.
 */
class ActiveRegionScopeTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    /** Yalnız Rize + Ardeşen açık; Trabzon kapalı ama Çaykara açık (tutarsız durum). */
    private function narrowToOneDistrict(): void
    {
        Province::query()->update(['is_active' => false]);
        District::query()->update(['is_active' => false]);

        Province::where('slug', 'rize')->update(['is_active' => true]);
        District::where('slug', 'ardesen')->update(['is_active' => true]);

        // Tuzak: ilçe açık ama ili kapalı → sayfa 404 döner.
        District::where('slug', 'caykara')->update(['is_active' => true]);
    }

    public function test_district_of_an_inactive_province_is_unreachable(): void
    {
        $this->narrowToOneDistrict();

        $this->get('/rize/ardesen')->assertOk();
        $this->get('/trabzon/caykara')->assertNotFound();
    }

    public function test_registry_excludes_unreachable_content(): void
    {
        $this->narrowToOneDistrict();

        $reachable = app(ContentRegistry::class)->reachable();
        $urls = $reachable->pluck('url')->filter()->map(fn ($u) => parse_url($u, PHP_URL_PATH))->all();

        $this->assertContains('/rize', $urls);
        $this->assertContains('/rize/ardesen', $urls);
        $this->assertNotContains('/trabzon/caykara', $urls, 'ili kapalı ilçe erişilebilir sayılmamalı');
        $this->assertNotContains('/trabzon', $urls);

        $this->assertLessThan(app(ContentRegistry::class)->all()->count(), $reachable->count());
    }

    public function test_scoring_skips_unreachable_pages_and_clears_old_scores(): void
    {
        // Önce her şey skorlansın.
        $this->artisan('seo:score', ['--all' => true])->assertSuccessful();
        $before = SeoAnalysis::count();

        $this->narrowToOneDistrict();
        $this->artisan('seo:score')->assertSuccessful();

        $this->assertLessThan($before, SeoAnalysis::count());

        // Kapalı sayfanın eski skoru KALMAMALI: panelde hayalet satır olmasın.
        $caykara = District::where('slug', 'caykara')->first();
        $this->assertDatabaseMissing('seo_analyses', ['content_type' => 'district', 'content_id' => $caykara->id]);

        $ardesen = District::where('slug', 'ardesen')->first();
        $this->assertDatabaseHas('seo_analyses', ['content_type' => 'district', 'content_id' => $ardesen->id]);
    }

    public function test_location_keywords_are_built_only_for_reachable_regions(): void
    {
        $this->narrowToOneDistrict();

        $result = app(LocationKeywordBuilder::class)->build();

        $this->assertSame(['Rize (il)', 'Ardeşen / Rize'], $result['regions']);

        $keywords = SeoKeyword::where('note', 'like', 'Bölge otomasyonu%')->pluck('keyword');

        $this->assertTrue($keywords->contains('ardeşen günübirlik turlar'));
        $this->assertTrue($keywords->contains('ardeşen çıkışlı turlar'));
        $this->assertTrue($keywords->contains('rize günübirlik turlar'));
        $this->assertTrue($keywords->contains('rize çıkışlı turlar'));
        $this->assertTrue($keywords->contains('ardeşen tur firmaları'));
        $this->assertFalse($keywords->contains('çaykara çıkışlı turlar'), 'erişilemeyen ilçe için kelime üretilmemeli');
        $this->assertFalse($keywords->contains('çaykara günübirlik turlar'));
        $this->assertFalse($keywords->contains('ortahisar çıkışlı turlar'));
        $this->assertFalse($keywords->contains('trabzon günübirlik turlar'));
    }

    public function test_location_keywords_include_tour_combinations(): void
    {
        $this->narrowToOneDistrict();
        app(LocationKeywordBuilder::class)->build();

        $keywords = SeoKeyword::pluck('keyword')->map(fn ($k) => TurkishText::lower($k));

        // İl/ilçe + tur birleşimleri blog hattının hedefi olur.
        $this->assertTrue($keywords->contains('ardeşen çıkışlı ayder yaylası turu'));
        $this->assertTrue($keywords->contains('ardeşen çıkışlı günübirlik batum turu'));
        $this->assertTrue($keywords->contains('rize çıkışlı batum tiflis turu'));
    }

    public function test_tour_combinations_are_built_only_for_active_featured_tours(): void
    {
        $this->narrowToOneDistrict();

        // Öne çıkmayan ve yayında olmayan turlar bölgeyle ÇARPILMAZ: yazılamayacak kadar
        // çok ve birbirine çok benzeyen konu üretirdi.
        Tour::where('slug', 'ayder-yaylasi-turu')->update(['is_featured' => false]);
        Tour::where('slug', 'batum-tiflis-turu')->update(['is_active' => false]);

        app(LocationKeywordBuilder::class)->build();

        $keywords = SeoKeyword::pluck('keyword');

        $this->assertFalse($keywords->contains('ardeşen çıkışlı ayder yaylası turu'));
        $this->assertFalse($keywords->contains('ardeşen çıkışlı batum tiflis turu'));
        $this->assertTrue($keywords->contains('ardeşen çıkışlı pokut ve sal yaylası turu'));
    }

    public function test_tour_combination_is_deactivated_when_the_tour_is_unpublished(): void
    {
        $this->narrowToOneDistrict();
        app(LocationKeywordBuilder::class)->build();

        $this->assertTrue((bool) SeoKeyword::where('keyword', 'ardeşen çıkışlı ayder yaylası turu')->value('status'));

        Tour::where('slug', 'ayder-yaylasi-turu')->update(['is_active' => false]);
        app(LocationKeywordBuilder::class)->build();

        $keyword = SeoKeyword::where('keyword', 'ardeşen çıkışlı ayder yaylası turu')->first();

        $this->assertNotNull($keyword, 'kelime SİLİNMEMELİ, yalnız pasife alınmalı');
        $this->assertFalse((bool) $keyword->status);
    }

    public function test_region_keywords_are_deactivated_when_the_region_closes(): void
    {
        app(TargetSynchroniser::class)->sync();
        app(LocationKeywordBuilder::class)->build();

        $this->assertTrue((bool) SeoKeyword::where('keyword', 'ardeşen çıkışlı turlar')->value('status'));

        District::where('slug', 'ardesen')->update(['is_active' => false]);
        app(TargetSynchroniser::class)->sync();
        app(LocationKeywordBuilder::class)->build();

        $keyword = SeoKeyword::where('keyword', 'ardeşen çıkışlı turlar')->first();

        $this->assertNotNull($keyword, 'kelime SİLİNMEMELİ, yalnız pasife alınmalı');
        $this->assertFalse((bool) $keyword->status);

        // Bölge tekrar açılırsa kelime geri gelir.
        District::where('slug', 'ardesen')->update(['is_active' => true]);
        app(TargetSynchroniser::class)->sync();
        app(LocationKeywordBuilder::class)->build();

        $this->assertTrue((bool) $keyword->refresh()->status);
    }

    public function test_district_keyword_is_owned_by_its_page(): void
    {
        $this->narrowToOneDistrict();
        app(TargetSynchroniser::class)->sync();
        app(LocationKeywordBuilder::class)->build();

        $keyword = SeoKeyword::where('keyword', 'ardeşen çıkışlı turlar')->firstOrFail();

        $this->assertNotNull($keyword->target_id);
        $this->assertSame('/rize/ardesen', $keyword->target->url);
        $this->assertSame(SeoKeyword::ASSIGN_ACTIVE, $keyword->assignment_status);

        // İl kelimesinin sahibi il sayfasıdır.
        $this->assertSame('/rize', SeoKeyword::where('keyword', 'rize günübirlik turlar')->firstOrFail()->target?->url);
        $this->assertSame('/rize', SeoKeyword::where('keyword', 'rize çıkışlı turlar')->firstOrFail()->target?->url);

        // Tur birleşimleri blog için SAHİPSİZ kalmalı.
        $tourKeyword = SeoKeyword::where('keyword', 'ardeşen çıkışlı ayder yaylası turu')->firstOrFail();
        $this->assertFalse($tourKeyword->hasOwner());
    }

    public function test_commercial_keywords_go_to_tour_pages(): void
    {
        app(TargetSynchroniser::class)->sync();

        $result = app(CommercialKeywordAssigner::class)->assign();
        $this->assertGreaterThan(0, $result['assigned']);

        $keyword = SeoKeyword::where('keyword', 'günübirlik ayder turu')->firstOrFail();

        // Ticari kelimeyi blog değil, para kazandıran tur sayfası sahiplenmeli.
        $this->assertSame('/ayder-yaylasi-turu', $keyword->target?->url);
        $this->assertFalse(
            app(KeywordPool::class)->available()->contains('keyword', 'günübirlik ayder turu'),
            'sahiplenen ticari kelime blog havuzunda kalmamalı',
        );
    }

    public function test_link_rules_only_target_reachable_pages(): void
    {
        $this->narrowToOneDistrict();
        app(TargetSynchroniser::class)->sync();
        app(RuleBuilder::class)->build();

        $active = InternalLinkRule::where('is_active', true)->pluck('target_url')->unique();

        $this->assertTrue($active->contains('/rize/ardesen'));
        $this->assertFalse($active->contains('/trabzon/caykara'), 'erişilemeyen sayfa link hedefi olmamalı');
        $this->assertFalse($active->contains('/trabzon'));
    }

    public function test_link_rules_are_deactivated_when_a_page_closes(): void
    {
        app(TargetSynchroniser::class)->sync();
        app(RuleBuilder::class)->build();

        $this->assertTrue(InternalLinkRule::where('target_url', '/rize/ardesen')->where('is_active', true)->exists());

        District::where('slug', 'ardesen')->update(['is_active' => false]);
        app(TargetSynchroniser::class)->sync();
        app(RuleBuilder::class)->build();

        $rule = InternalLinkRule::where('target_url', '/rize/ardesen')->first();
        $this->assertNotNull($rule, 'kural SİLİNMEMELİ');
        $this->assertFalse((bool) $rule->is_active);
    }

    public function test_published_blog_gets_internal_links_at_render_time(): void
    {
        app(TargetSynchroniser::class)->sync();
        app(RuleBuilder::class)->build();

        SeoConfig::set('internal_links_enabled', true);
        Setting::flush();

        $blog = Blog::create([
            'title' => 'Yaylada Bulut Denizini İzlerken Dikkat Edilecekler',
            'slug' => 'yaylada-bulut-denizini-izlerken-dikkat',
            'body_html' => '<p>Ardeşen çıkışlı turlar arasında en çok sorulan program Ayder Yaylası Turunun bulut denizi sabahıdır.</p>',
            'status' => Blog::STATUS_PUBLISHED,
            'publish_at' => now()->subHour(),
        ]);

        $html = $this->get($blog->path())->assertOk()->getContent();

        // Link RENDER ANINDA basılır; links:apply cron'unu beklemez.
        $this->assertStringContainsString('class="internal-link"', $html);
        $this->assertStringContainsString('href="/ayder-yaylasi-turu"', $html);
        $this->assertStringContainsString('href="/rize/ardesen"', $html);

        // Veritabanındaki gövde değişmemiş olmalı (kalıcı yazma ayrı bir iştir).
        $this->assertStringNotContainsString('internal-link', $blog->refresh()->body_html);
    }

    public function test_render_time_links_are_off_when_the_engine_is_off(): void
    {
        app(TargetSynchroniser::class)->sync();
        app(RuleBuilder::class)->build();

        $blog = Blog::create([
            'title' => 'Motor Kapalıyken', 'slug' => 'motor-kapaliyken',
            'body_html' => '<p>Ardeşen çıkışlı turlar ve Ayder Yaylası Turu.</p>',
            'status' => Blog::STATUS_PUBLISHED, 'publish_at' => now()->subHour(),
        ]);

        $html = $this->get($blog->path())->assertOk()->getContent();

        $this->assertStringNotContainsString('class="internal-link"', $html);
    }
}
