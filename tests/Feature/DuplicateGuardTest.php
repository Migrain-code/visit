<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\SeoKeyword;
use App\Models\SeoTarget;
use App\Models\Setting;
use App\Services\Seo\CannibalizationGuard;
use App\Services\Seo\DuplicateGuard;
use App\Support\SeoConfig;
use App\Support\TurkishText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Spec §9 — "Çakışma filtresi" kabul kriterleri.
 */
class DuplicateGuardTest extends TestCase
{
    use RefreshDatabase;

    // Tur ve ilçe başlıkları da karşılaştırmaya girer; bu yüzden seed gerekli.
    protected bool $seed = true;

    private function blog(string $title, array $attributes = []): Blog
    {
        return Blog::create(array_merge([
            'title' => $title,
            'slug' => TurkishText::slug($title),
            'body_html' => '<p>İçerik</p>',
            'status' => Blog::STATUS_PUBLISHED,
            'publish_at' => now()->subDay(),
        ], $attributes));
    }

    public function test_blocks_a_near_duplicate_title(): void
    {
        $this->blog('Pamukkale Travertenlerinde Dikkat Edilecekler');

        $decision = app(DuplicateGuard::class)->check('Pamukkale Traverteninde Dikkat Edilecekler');

        $this->assertFalse($decision->accepted);
        $this->assertSame('duplicate_title', $decision->reasonCode);
        $this->assertNotNull($decision->reason, 'ret gerekçesi taşınmalı — sessiz eleme yok');
    }

    public function test_allows_a_genuinely_new_topic(): void
    {
        $this->blog('Pamukkale Travertenlerinde Dikkat Edilecekler');

        $this->assertTrue(app(DuplicateGuard::class)->check('Otobüs Yolculuğunda Yanınıza Almanız Gerekenler')->accepted);
    }

    public function test_draft_posts_also_block_duplicates(): void
    {
        // Aynı konu iki kez kuyruğa girmesin.
        $this->blog('Bozcaada Feribotu Ne Kadar Sürer', ['status' => Blog::STATUS_DRAFT]);

        $decision = app(DuplicateGuard::class)->check('Bozcaada Feribotu Ne Kadar Sürer?');

        $this->assertFalse($decision->accepted);
        $this->assertStringContainsString('(taslak)', (string) $decision->conflictWith);
    }

    public function test_tour_and_district_titles_also_block(): void
    {
        // Blog, mevcut tur sayfasının konusunu tekrarlamamalı.
        $tour = app(DuplicateGuard::class)->check('Ayder Yaylası Turu');

        $this->assertFalse($tour->accepted);
        $this->assertSame('Tur: Ayder Yaylası Turu', $tour->conflictWith);

        // İlçe (kalkış noktası) sayfasının konusu da öyle.
        $district = app(DuplicateGuard::class)->check('Ardeşen Çıkışlı Turlar');

        $this->assertFalse($district->accepted);
        $this->assertSame('İlçe sayfası: Ardeşen Çıkışlı Turlar', $district->conflictWith);
    }

    public function test_a_blog_about_a_destination_does_not_collide_with_the_tour_page(): void
    {
        // Filtre konuyu engeller, varış noktasını DEĞİL: Ayder hakkında bilgi yazısı
        // yazılabilmeli; yoksa tur satan bir sitede blog hattı hiç konu bulamaz.
        $this->assertTrue(app(DuplicateGuard::class)->check('Ayder\'de Kaplıca İçin En Uygun Mevsim')->accepted);
    }

    public function test_invalid_threshold_falls_back_to_safe_default(): void
    {
        $guard = app(DuplicateGuard::class);
        $default = (float) config('seo.duplicate.scan_threshold');

        foreach (['0', '0.0', '1.5', '-1', 'abc', '99'] as $bad) {
            SeoConfig::set('duplicate_scan_threshold', $bad);
            Setting::flush();

            $this->assertSame($default, $guard->threshold(), "geçersiz eşik '{$bad}' varsayılana düşmeli");
        }

        // Geçerli değer kabul edilir.
        SeoConfig::set('duplicate_scan_threshold', '0.7');
        Setting::flush();
        $this->assertSame(0.7, $guard->threshold());
    }

    public function test_cannibalization_blocks_a_second_owner(): void
    {
        $this->blog('Göbeklitepe Gezi Notları', ['primary_keyword' => 'göbeklitepe nasıl gezilir']);

        $decision = app(CannibalizationGuard::class)->check('göbeklitepe nasıl gezilir');

        $this->assertFalse($decision->accepted);
        $this->assertSame('cannibalization', $decision->reasonCode);
        // Kontrol ya ENGELLER ya görünür yere yazar; sessiz uyarı yok (spec §7.4).
        $this->assertStringContainsString('göbeklitepe nasıl gezilir', (string) $decision->reason);
    }

    public function test_cannibalization_counts_assigned_targets(): void
    {
        $target = SeoTarget::create(['name' => 'Ayder Yaylası Turu', 'url' => '/ayder-yaylasi-turu', 'target_type' => 'tour']);

        // Kelime başlangıç havuzundan gelir; burada bir hedefe SAHİPLENDİRİYORUZ.
        $keyword = SeoKeyword::where('keyword', 'günübirlik ayder turu')->firstOrFail();
        $keyword->update(['target_id' => $target->getKey()]);

        $this->assertSame(SeoKeyword::ASSIGN_ACTIVE, $keyword->refresh()->assignment_status);

        $decision = app(CannibalizationGuard::class)->check('Günübirlik Ayder Turu');

        $this->assertFalse($decision->accepted, 'büyük/küçük harf farkı çakışmayı gizlememeli');
        $this->assertStringContainsString('Hedef: Ayder Yaylası Turu', (string) $decision->conflictWith);
    }

    public function test_cannibalization_allows_a_free_keyword(): void
    {
        $this->assertTrue(app(CannibalizationGuard::class)->check('tamamen yeni bir kelime öbeği')->accepted);
        $this->assertTrue(app(CannibalizationGuard::class)->check(null)->accepted);
    }
}
