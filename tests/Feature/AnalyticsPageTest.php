<?php

namespace Tests\Feature;

use App\Filament\Pages\Analytics;
use App\Filament\Widgets\RequestTrendChart;
use App\Filament\Widgets\SearchTrafficChart;
use App\Models\AiCrawlerVisit;
use App\Models\Blog;
use App\Models\ReservationRequest;
use App\Models\User;
use App\Support\ChartPalette;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsPageTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function seedVisits(): void
    {
        $rows = [
            ['ClaudeBot', '/', 40, 200],
            ['ClaudeBot', '/kapadokya-turu', 12, 200],
            ['ClaudeBot', '/tekirdag/corlu', 6, 200],
            ['ClaudeBot', '/eski-adres', 2, 404],
            ['GPTBot', '/', 25, 200],
            ['GPTBot', '/turlar', 5, 200],
            ['PerplexityBot', '/iletisim', 3, 200],
        ];

        foreach ($rows as [$bot, $path, $hits, $status]) {
            AiCrawlerVisit::create([
                'bot' => $bot,
                'path' => $path,
                'key_hash' => md5($bot.'|'.$path),
                'hits' => $hits,
                'last_status' => $status,
                'last_seen_at' => now()->subHours(2),
            ]);
        }
    }

    public function test_page_requires_login(): void
    {
        $this->get('/admin/analytics')->assertRedirect('/admin/login');
    }

    public function test_page_renders_for_admin(): void
    {
        $this->actingAs(User::first());

        $this->get('/admin/analytics')->assertOk();
    }

    public function test_bot_visits_are_grouped_into_one_card_per_bot(): void
    {
        $this->seedVisits();
        $this->actingAs(User::first());

        $html = $this->get('/admin/analytics')->assertOk()->getContent();

        // Her bot TEK kart: ham satırlar değil, toplam gösterilir.
        $this->assertSame(1, substr_count($html, '>ClaudeBot</span>'), 'ClaudeBot tek kartta toplanmalı');
        $this->assertSame(1, substr_count($html, '>GPTBot</span>'));
        $this->assertSame(1, substr_count($html, '>PerplexityBot</span>'));

        // ClaudeBot toplamı 40+12+6+2 = 60
        $this->assertStringContainsString('>60</span>', $html);
        // Hatalı isteği ayrıca işaretler.
        $this->assertStringContainsString('1 hata', $html);
    }

    public function test_bot_cards_are_sorted_by_volume_with_fixed_colour_order(): void
    {
        $this->seedVisits();
        $this->actingAs(User::first());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $data = Livewire::test(Analytics::class)->instance()->getViewData();
        $bots = $data['bots'];

        $this->assertSame(['ClaudeBot', 'GPTBot', 'PerplexityBot'], $bots->pluck('bot')->all());
        $this->assertSame(60, $bots->first()['hits']);
        $this->assertSame(4, $bots->first()['paths']);

        // Renk sabit sırayla atanır, döngüye sokulmaz.
        $this->assertSame(ChartPalette::LIGHT[0], $bots[0]['color']);
        $this->assertSame(ChartPalette::LIGHT[1], $bots[1]['color']);
        $this->assertSame(ChartPalette::LIGHT[2], $bots[2]['color']);

        // Paylar toplamı %100'ü aşmamalı.
        $this->assertLessThanOrEqual(101, $bots->sum('share'));
    }

    public function test_bot_card_shows_top_paths_only(): void
    {
        $this->seedVisits();
        $this->actingAs(User::first());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $claude = Livewire::test(Analytics::class)->instance()->getViewData()['bots']->firstWhere('bot', 'ClaudeBot');

        $this->assertCount(3, $claude['top'], 'kart en çok okunan 3 adresi göstermeli');
        $this->assertSame('/', $claude['top'][0]['path']);
        $this->assertSame(40, $claude['top'][0]['hits']);
    }

    public function test_empty_state_is_shown_without_visits(): void
    {
        $this->actingAs(User::first());

        $html = $this->get('/admin/analytics')->assertOk()->getContent();

        $this->assertStringContainsString('Henüz yapay zeka botu ziyareti kaydedilmedi', $html);
    }

    public function test_tiles_report_real_counts(): void
    {
        $this->seedVisits();

        ReservationRequest::create(['name' => 'A', 'phone' => '05321112233', 'kvkk_accepted' => true]);
        ReservationRequest::create(['name' => 'B', 'phone' => '05321112234', 'kvkk_accepted' => true]);

        $this->actingAs(User::first());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $tiles = collect(Livewire::test(Analytics::class)->instance()->getViewData()['tiles'])->keyBy('label');

        $this->assertSame(2, $tiles['Rezervasyon talebi']['value']);
        $this->assertSame(93, $tiles['AI bot ziyareti']['value']); // 60 + 30 + 3
        $this->assertStringContainsString('3 farklı bot', $tiles['AI bot ziyareti']['unit']);
    }

    public function test_search_chart_is_hidden_until_google_is_connected(): void
    {
        $this->assertFalse(SearchTrafficChart::canView());
    }

    public function test_request_chart_covers_thirty_days_as_a_single_series(): void
    {
        ReservationRequest::create(['name' => 'A', 'phone' => '05321112233', 'kvkk_accepted' => true]);

        $this->actingAs(User::first());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $widget = Livewire::test(RequestTrendChart::class)->instance();

        // getData() korumalı; grafik verisini doğrudan okuyoruz.
        $method = new \ReflectionMethod($widget, 'getData');
        $method->setAccessible(true);
        $data = $method->invoke($widget);

        $this->assertCount(30, $data['labels']);
        // Tek seri: ikinci bir eksen veya ikinci bir veri kümesi YOK.
        $this->assertCount(1, $data['datasets']);
        $this->assertSame(1, array_sum($data['datasets'][0]['data']));
        $this->assertSame(ChartPalette::PRIMARY, $data['datasets'][0]['backgroundColor']);
    }

    public function test_upcoming_posts_are_listed(): void
    {
        Blog::create([
            'title' => 'Yaklaşan yazı', 'slug' => 'yaklasan-yazi', 'body_html' => '<p>x</p>',
            'status' => Blog::STATUS_DRAFT, 'publish_at' => now()->addDays(2),
        ]);

        $this->actingAs(User::first());

        $this->get('/admin/analytics')->assertOk()->assertSee('Yaklaşan yazı');
    }
}
