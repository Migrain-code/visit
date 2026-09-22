<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\Redirect;
use App\Models\SeoKeyword;
use App\Models\SeoSearchQuery;
use App\Models\Setting;
use App\Services\Seo\DuplicateMerger;
use App\Services\Seo\DuplicateScanner;
use App\Support\PathNormalizer;
use App\Support\SeoConfig;
use App\Support\TurkishText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Spec §9 — "Birleştirme" kabul kriterleri.
 */
class DuplicateMergeTest extends TestCase
{
    use RefreshDatabase;

    private function blog(string $title, array $attributes = []): Blog
    {
        // Test verisi: "Sürer" ve "Sürer?" aynı slug'ı üretir, bu yüzden benzersizleştiriyoruz.
        $slug = TurkishText::slug($title);
        $suffix = 2;

        while (Blog::where('slug', $slug)->exists()) {
            $slug = TurkishText::slug($title).'-'.$suffix++;
        }

        return Blog::create(array_merge([
            'title' => $title,
            'slug' => $slug,
            'body_html' => '<p>İçerik</p>',
            'status' => Blog::STATUS_PUBLISHED,
            'publish_at' => now()->subDays(5),
        ], $attributes));
    }

    public function test_loser_is_unpublished_not_deleted(): void
    {
        $winner = $this->blog('Baza Montajı Ne Kadar Sürer');
        $loser = $this->blog('Baza Montajı Ne Kadar Sürer?');

        app(DuplicateMerger::class)->merge($winner, $loser);

        // İÇERİK SİLİNMEZ (spec §7.7, §10.9).
        $this->assertDatabaseHas('blogs', ['id' => $loser->getKey()]);

        $loser->refresh();
        $this->assertSame(Blog::STATUS_DRAFT, $loser->status);
        $this->assertSame($winner->getKey(), $loser->merged_into_id);
        $this->assertNotNull($loser->merged_at);
    }

    public function test_redirect_is_created_with_correct_hash(): void
    {
        $winner = $this->blog('Gardırop Kurulumu');
        $loser = $this->blog('Gardırop Kurulumu 2');

        app(DuplicateMerger::class)->merge($winner, $loser);

        $redirect = Redirect::where('from_hash', PathNormalizer::hash($loser->path()))->first();

        $this->assertNotNull($redirect, 'from_hash modelin kendisi tarafından doldurulmalı (spec §7.10)');
        $this->assertSame(301, $redirect->status_code);
        $this->assertSame($winner->path(), $redirect->to_path);
        $this->assertSame(Redirect::SOURCE_DUPLICATE_MERGE, $redirect->source);
        $this->assertTrue($redirect->is_active);

        // Yönlendirme gerçekten çalışıyor mu?
        $this->get($loser->path())->assertRedirect(url($winner->path()));
    }

    public function test_merge_is_idempotent(): void
    {
        $winner = $this->blog('Masa Montajı');
        $loser = $this->blog('Masa Montajı 2');

        $first = app(DuplicateMerger::class)->merge($winner, $loser);
        $second = app(DuplicateMerger::class)->merge($winner, $loser->refresh());

        $this->assertTrue($first['merged']);
        // İkinci çağrı HATA DEĞİL, no-op.
        $this->assertFalse($second['merged']);
        $this->assertSame('already_merged', $second['reason']);
        $this->assertSame(1, Redirect::count());
    }

    public function test_rejects_merging_into_an_unpublished_target(): void
    {
        $winner = $this->blog('Kitaplık Montajı', ['status' => Blog::STATUS_DRAFT]);
        $loser = $this->blog('Kitaplık Montajı 2');

        // Yayında olmayan hedefe yönlendirmek 301 zincirini 404'e sokar.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/yayında değil/i');

        app(DuplicateMerger::class)->merge($winner, $loser);
    }

    public function test_nothing_is_written_when_merge_is_rejected(): void
    {
        $winner = $this->blog('TV Ünitesi Montajı', ['status' => Blog::STATUS_DRAFT]);
        $loser = $this->blog('TV Ünitesi Montajı 2');

        try {
            app(DuplicateMerger::class)->merge($winner, $loser);
        } catch (RuntimeException) {
            // beklenen
        }

        $this->assertSame(0, Redirect::count());
        $this->assertSame(Blog::STATUS_PUBLISHED, $loser->refresh()->status);
    }

    public function test_winner_is_chosen_by_traffic_then_date(): void
    {
        $older = $this->blog('Baza Kurulumu', ['publish_at' => now()->subDays(30)]);
        $newer = $this->blog('Baza Kurulumu 2', ['publish_at' => now()->subDays(1)]);

        $scanner = app(DuplicateScanner::class);

        // Veri yokken: eski yayın tarihi kazanır.
        [$winner] = $scanner->rank($older, $newer, $scanner->metrics());
        $this->assertTrue($winner->is($older), 'trafik verisi yokken eski yazı kazanmalı');

        // Trafik verisi geldiğinde: tıklama kazanır, tarih değil.
        SeoSearchQuery::create([
            'dimension' => 'page', 'path' => $newer->path(), 'clicks' => 120, 'impressions' => 900,
            'ctr' => 0.13, 'position' => 4.2, 'period_start' => now()->subMonth(), 'period_end' => now(),
            'row_hash' => md5('newer'),
        ]);

        $scanner = app(DuplicateScanner::class);
        [$winner] = $scanner->rank($older, $newer, $scanner->metrics());
        $this->assertTrue($winner->is($newer), 'tıklaması olan yazı kazanmalı');
    }

    public function test_moderately_similar_pair_is_not_auto_mergeable(): void
    {
        // ~%67 benzeyen ama AYRI konular: biri sökme, diğeri kurma.
        // Otomatik birleştirmek içerik silmek olurdu (spec §7.6).
        $this->blog('Ofis Taşımada Mobilya Sökme Rehberi');
        $this->blog('Ofis Taşımada Mobilya Kurma Rehberi');

        $pairs = app(DuplicateScanner::class)->pairs();

        $this->assertCount(1, $pairs, 'çift listelenmeli');
        $this->assertGreaterThanOrEqual(0.55, $pairs->first()['score'], 'tarama eşiğini geçmeli');
        $this->assertLessThan(0.90, $pairs->first()['score'], 'birleştirme eşiğinin altında kalmalı');
        $this->assertFalse($pairs->first()['mergeable'], 'birleştirme butonu çıkmamalı');
    }

    public function test_merge_threshold_can_never_drop_below_scan_threshold(): void
    {
        SeoConfig::set('duplicate_scan_threshold', '0.80');
        SeoConfig::set('duplicate_merge_threshold', '0.40');
        Setting::flush();

        $scanner = app(DuplicateScanner::class);

        $this->assertGreaterThanOrEqual($scanner->scanThreshold(), $scanner->mergeThreshold());
    }

    public function test_keyword_ownership_transfers_to_the_winner(): void
    {
        $winner = $this->blog('Ofis Montajı');
        $loser = $this->blog('Ofis Montajı 2');

        $keyword = SeoKeyword::create(['keyword' => 'ofis mobilya montajı', 'owner_blog_id' => $loser->getKey()]);

        app(DuplicateMerger::class)->merge($winner, $loser);

        $this->assertSame($winner->getKey(), $keyword->refresh()->owner_blog_id);
    }

    public function test_merge_can_be_undone(): void
    {
        $winner = $this->blog('Raf Montajı');
        $loser = $this->blog('Raf Montajı 2');

        app(DuplicateMerger::class)->merge($winner, $loser);
        $this->assertTrue(app(DuplicateMerger::class)->undo($loser->refresh()));

        $loser->refresh();
        $this->assertSame(Blog::STATUS_PUBLISHED, $loser->status);
        $this->assertNull($loser->merged_into_id);
        $this->assertFalse(Redirect::first()->is_active);
    }
}
