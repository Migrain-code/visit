<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\TourCategory;
use App\Services\InternalLink\LinkApplier;
use App\Services\InternalLink\RuleBuilder;
use App\Services\Seo\TargetSynchroniser;
use App\Support\SeoConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * İç linkler alan adından BAĞIMSIZ yazılmalı.
 *
 * Link motoru gövdeyi veritabanına kalıcı yazar. Tam adres (url()) yazılırsa o
 * anki alan adı içeriğe gömülür: yerelde üretilen içerik canlıya taşındığında
 * bütün iç linkler 127.0.0.1'e gider ve kırılır.
 */
class InternalLinkPortabilityTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_applied_links_are_root_relative(): void
    {
        config(['app.url' => 'http://127.0.0.1:8000']);

        app(TargetSynchroniser::class)->sync();
        app(RuleBuilder::class)->build();
        SeoConfig::set('internal_links_enabled', true);
        Setting::flush();

        $html = app(LinkApplier::class)->apply(
            '<p>Ardeşen çıkışlı turlar ve Ayder Yaylası Turu hakkında bilgi.</p>',
            'blog',
            '/blog/ornek',
        );

        $this->assertStringContainsString('class="internal-link"', $html, 'Kural uygulanmadı; test anlamsız kalır.');
        $this->assertStringNotContainsString('127.0.0.1', $html);
        $this->assertStringNotContainsString('http', $html);
        $this->assertMatchesRegularExpression('#<a href="/[a-z0-9\-/]+" class="internal-link">#', $html);
        $this->assertStringContainsString('<a href="/rize/ardesen" class="internal-link">Ardeşen çıkışlı turlar</a>', $html);
        $this->assertStringContainsString('<a href="/ayder-yaylasi-turu" class="internal-link">Ayder Yaylası Turu</a>', $html);
    }

    public function test_links_written_to_the_database_stay_root_relative(): void
    {
        config(['app.url' => 'http://127.0.0.1:8000']);

        app(TargetSynchroniser::class)->sync();
        app(RuleBuilder::class)->build();
        SeoConfig::set('internal_links_enabled', true);
        Setting::flush();

        $category = TourCategory::where('slug', 'yayla-turlari')->firstOrFail();
        $category->forceFill(['content' => '<p>Yayla Turları içinde en çok sorulan program Ayder Yaylası Turu olur.</p>'])->saveQuietly();

        // links:apply gövdeyi KALICI yazar; alan adı içeriğe gömülmemeli.
        $this->artisan('links:apply', ['--force' => true, '--type' => ['category']])->assertSuccessful();

        $content = (string) $category->refresh()->content;

        $this->assertStringContainsString('<a href="/ayder-yaylasi-turu" class="internal-link">Ayder Yaylası Turu</a>', $content);
        $this->assertStringNotContainsString('127.0.0.1', $content);
        $this->assertStringNotContainsString('href="http', $content);
        // Sayfa KENDİNE link vermez.
        $this->assertStringNotContainsString('href="/turlar/kultur-turlari"', $content);

        // İkinci çalıştırma hiçbir şeyi değiştirmemeli (cron her gün koşar).
        $this->artisan('links:apply', ['--force' => true, '--type' => ['category']])->assertSuccessful();
        $this->assertSame($content, (string) $category->refresh()->content);
    }
}
