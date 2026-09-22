<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsTagTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_measurement_id_produces_the_official_gtag_snippet(): void
    {
        Setting::set('google_analytics_id', 'G-J48B75G7Y3');
        Setting::flush();

        $html = $this->get('/')->assertOk()->getContent();

        // Komutlar Google'ın etiketiyle aynı: dataLayer kuyruğu ve config satırı hemen yazılır.
        $this->assertStringContainsString('window.dataLayer = window.dataLayer || []', $html);
        $this->assertStringContainsString("gtag('config', \"G-J48B75G7Y3\")", $html);

        // Betik ise ERTELENİR (PageSpeed: TBT). Engelleyen bir <script src> olmamalı,
        // ama betiğin adresi yükleyicide bulunmalı.
        $this->assertStringNotContainsString('<script async src="https://www.googletagmanager.com', $html);
        $this->assertStringContainsString('https://www.googletagmanager.com/gtag/js?id=', $html);
        $this->assertStringContainsString("['pointerdown', 'keydown', 'touchstart', 'scroll']", $html);
    }

    public function test_no_tag_is_emitted_when_the_field_is_empty(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('googletagmanager.com', $html);
    }

    public function test_tag_appears_on_every_public_page(): void
    {
        Setting::set('google_analytics_id', 'G-J48B75G7Y3');
        Setting::flush();

        foreach (['/', '/turlar', '/turlar/yayla-turlari', '/tur-takvimi', '/ayder-yaylasi-turu', '/rize/ardesen', '/blog', '/iletisim', '/rezervasyon', '/rezervasyon/tesekkurler'] as $url) {
            $this->get($url)->assertOk()->assertSee('G-J48B75G7Y3', false);
        }
    }

    public function test_admin_panel_is_not_tracked(): void
    {
        Setting::set('google_analytics_id', 'G-J48B75G7Y3');
        Setting::flush();

        // Yönetim paneli ziyaretleri Analytics'e gitmemeli; ziyaretçi verisini kirletir.
        $this->actingAs(User::first());

        $this->get('/admin/site-settings')->assertOk()->assertDontSee('googletagmanager.com', false);
    }
}
