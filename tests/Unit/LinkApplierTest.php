<?php

namespace Tests\Unit;

use App\Models\InternalLinkRule;
use App\Services\InternalLink\LinkApplier;
use App\Support\SeoConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkApplierTest extends TestCase
{
    use RefreshDatabase;

    private function rule(string $anchor, string $url, int $max = 1, int $priority = 50): InternalLinkRule
    {
        return InternalLinkRule::create([
            'anchor_text' => $anchor,
            'target_url' => $url,
            'max_per_article' => $max,
            'priority' => $priority,
            'scope_type' => 'blog',
            'is_active' => true,
        ]);
    }

    private function enable(array $overrides = []): LinkApplier
    {
        SeoConfig::set('internal_links_enabled', true);

        foreach ($overrides as $key => $value) {
            SeoConfig::set($key, $value);
        }

        return app(LinkApplier::class);
    }

    public function test_engine_is_off_by_default(): void
    {
        $this->rule('gardırop montajı', '/gardirop-montaji');

        $html = '<p>Çorlu gardırop montajı için bize yazın.</p>';

        // Kill switch varsayılan KAPALI (spec §3.6).
        $this->assertSame($html, app(LinkApplier::class)->apply($html));
    }

    public function test_links_only_plain_text(): void
    {
        $this->rule('gardırop montajı', '/gardirop-montaji', max: 5);
        $applier = $this->enable();

        $html = '<h2>gardırop montajı</h2>'
            .'<p>Çorlu gardırop montajı hizmeti.</p>'
            .'<p><a href="/baska">gardırop montajı</a></p>'
            .'<pre>gardırop montajı</pre>'
            .'<code>gardırop montajı</code>';

        $result = $applier->apply($html);

        // Yalnız düz metindeki tek geçiş linklenmeli.
        $this->assertSame(1, substr_count($result, 'class="internal-link"'));
        $this->assertStringContainsString('<h2>gardırop montajı</h2>', $result);
        $this->assertStringContainsString('<pre>gardırop montajı</pre>', $result);
        $this->assertStringContainsString('<code>gardırop montajı</code>', $result);
        $this->assertStringContainsString('<a href="/baska">gardırop montajı</a>', $result);
    }

    public function test_respects_per_article_cap(): void
    {
        $this->rule('montaj', '/mobilya-montaji', max: 10);
        $applier = $this->enable(['internal_links_max_per_article' => 2]);

        $html = '<p>montaj montaj montaj montaj montaj</p>';

        $this->assertSame(2, substr_count($applier->apply($html), 'class="internal-link"'));
    }

    public function test_respects_per_rule_cap(): void
    {
        $this->rule('montaj', '/mobilya-montaji', max: 1);
        $applier = $this->enable(['internal_links_max_per_article' => 10]);

        $html = '<p>montaj ve yine montaj ve tekrar montaj</p>';

        $this->assertSame(1, substr_count($applier->apply($html), 'class="internal-link"'));
    }

    public function test_home_link_has_its_own_cap(): void
    {
        $this->rule('mobilya montaj firması', '/', max: 5);
        $applier = $this->enable(['internal_links_max_home' => 1, 'internal_links_max_per_article' => 10]);

        $html = '<p>mobilya montaj firması, mobilya montaj firması, mobilya montaj firması</p>';

        $this->assertSame(1, substr_count($applier->apply($html), 'class="internal-link"'));
    }

    public function test_does_not_link_to_itself(): void
    {
        $this->rule('gardırop montajı', '/gardirop-montaji');
        $applier = $this->enable();

        $html = '<p>Bu sayfa gardırop montajı hakkında.</p>';

        $this->assertStringNotContainsString('internal-link', $applier->apply($html, 'blog', '/gardirop-montaji'));
    }

    public function test_word_boundaries_are_respected(): void
    {
        $this->rule('montaj', '/mobilya-montaji');
        $applier = $this->enable();

        // "montajcı" ve "demontaj" eşleşmemeli.
        $result = $applier->apply('<p>montajcı ve demontaj işleri</p>');

        $this->assertStringNotContainsString('internal-link', $result);
    }

    public function test_longer_anchor_wins_over_shorter(): void
    {
        $this->rule('montaj', '/mobilya-montaji', priority: 50);
        $this->rule('gardırop montajı', '/gardirop-montaji', priority: 50);

        $applier = $this->enable(['internal_links_max_per_article' => 5]);

        $result = $applier->apply('<p>Çorlu gardırop montajı yapıyoruz.</p>');

        $this->assertStringContainsString('/gardirop-montaji', $result);
        $this->assertSame(1, substr_count($result, 'class="internal-link"'));
    }

    public function test_never_nests_links(): void
    {
        $this->rule('gardırop montajı', '/gardirop-montaji', priority: 90);
        $this->rule('montajı', '/mobilya-montaji', priority: 10);

        $applier = $this->enable(['internal_links_max_per_article' => 5]);

        $result = $applier->apply('<p>gardırop montajı hizmeti</p>');

        // İç içe <a> ASLA oluşmamalı.
        $this->assertSame(1, substr_count($result, '<a '));
        $this->assertStringNotContainsString('<a href="/mobilya-montaji"', $result);
    }

    public function test_invalid_threshold_falls_back_safely(): void
    {
        $this->rule('montaj', '/mobilya-montaji', max: 9);
        // Geçersiz (negatif) tavan güvenli varsayılana düşmeli, filtreyi KAPATMAMALI (spec §5).
        $applier = $this->enable(['internal_links_max_per_article' => -5]);

        $result = $applier->apply('<p>montaj montaj montaj montaj montaj montaj</p>');

        $this->assertSame(4, substr_count($result, 'class="internal-link"'), 'varsayılan tavan 4 olmalı');
    }

    public function test_turkish_inflectional_suffixes_are_linked(): void
    {
        $this->rule('gardırop montajı', '/gardirop-montaji', max: 1);
        $applier = $this->enable(['internal_links_max_per_article' => 10]);

        // Türkçe metinde anchor neredeyse hep ekli geçer.
        $cases = [
            'gardırop montajını yaptık',
            'gardırop montajında dikkat',
            'gardırop montajına geldik',
            'gardırop montajıyla ilgili',
            'gardırop montajından sonra',
        ];

        foreach ($cases as $text) {
            $result = $applier->apply('<p>'.$text.'</p>');
            $this->assertStringContainsString('class="internal-link"', $result, "'{$text}' linklenmeli");
        }
    }

    public function test_derivational_suffixes_are_not_linked(): void
    {
        $this->rule('montaj', '/mobilya-montaji', max: 5);
        $applier = $this->enable(['internal_links_max_per_article' => 10]);

        // -cı, -lık, -sız yapım ekleridir: ayrı kelime üretirler, linklenmemeli.
        foreach (['montajcı geldi', 'montajlık malzeme', 'montajsız teslim', 'demontaj yapıldı'] as $text) {
            $result = $applier->apply('<p>'.$text.'</p>');
            $this->assertStringNotContainsString('internal-link', $result, "'{$text}' linklenmemeli");
        }
    }

    public function test_suffix_is_included_in_the_anchor_text(): void
    {
        $this->rule('çorlu mobilya montaj', '/tekirdag/corlu');
        $applier = $this->enable();

        $result = $applier->apply('<p>Çorlu mobilya montajı için bize yazın.</p>');

        // Bağlantı metni ekiyle birlikte doğal okunmalı.
        $this->assertStringContainsString('>Çorlu mobilya montajı</a>', $result);
    }

    public function test_applying_twice_does_not_accumulate_links(): void
    {
        $this->rule('gardırop montajı', '/gardirop-montaji');
        $this->rule('baza montajı', '/yatak-baza-montaji');
        $this->rule('tv ünitesi', '/tv-unitesi-montaji');

        $applier = $this->enable(['internal_links_max_per_article' => 2]);

        $html = '<p>gardırop montajı, baza montajı ve tv ünitesi kurulumu yapıyoruz.</p>';

        $once = $applier->apply($html);
        $twice = $applier->apply($once);

        // Komut cron'da her gün koşar; ikinci tur HİÇBİR ŞEY değiştirmemeli.
        $this->assertSame(2, substr_count($once, 'class="internal-link"'));
        $this->assertSame($once, $twice, 'ikinci uygulama idempotent olmalı');
    }

    public function test_existing_links_count_against_the_cap(): void
    {
        $this->rule('baza montajı', '/yatak-baza-montaji');
        $applier = $this->enable(['internal_links_max_per_article' => 1]);

        // Gövdede zaten bir site içi link var; tavan doludur.
        $html = '<p>Önceden <a href="/mobilya-montaji">mobilya montajı</a> ve baza montajı.</p>';

        $this->assertStringNotContainsString('class="internal-link"', $applier->apply($html));
    }

    public function test_external_links_do_not_consume_the_budget(): void
    {
        $this->rule('baza montajı', '/yatak-baza-montaji');
        $applier = $this->enable(['internal_links_max_per_article' => 1]);

        $html = '<p>Kaynak: <a href="https://ornek-disaridan.com/x">dış site</a>. baza montajı yapıyoruz.</p>';

        $this->assertStringContainsString('class="internal-link"', $applier->apply($html));
    }
}
