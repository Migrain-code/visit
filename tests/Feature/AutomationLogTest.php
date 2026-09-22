<?php

namespace Tests\Feature;

use App\Support\AutomationLog;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

/**
 * Spec §9 — "Loglama" ve "Zamanlama" kabul kriterleri.
 */
class AutomationLogTest extends TestCase
{
    private function spyChannel(): LoggerInterface
    {
        $logger = Mockery::spy(LoggerInterface::class);

        Log::shouldReceive('channel')->with('automation')->andReturn($logger);

        return $logger;
    }

    public function test_routine_rounds_are_quiet(): void
    {
        $logger = $this->spyChannel();

        // Değişiklik yok + tüm sebepler beklenen türden → debug (sessiz).
        AutomationLog::summary('blog.publish', ['published' => 0], reasons: ['nothing_due']);

        $logger->shouldHaveReceived('log')->with('debug', 'blog.publish', Mockery::any())->once();
    }

    public function test_a_round_that_changed_something_is_visible(): void
    {
        $logger = $this->spyChannel();

        AutomationLog::summary('blog.publish', ['published' => 3], changed: true);

        $logger->shouldHaveReceived('log')->with('info', 'blog.publish', Mockery::any())->once();
    }

    public function test_an_unexpected_reason_is_visible(): void
    {
        $logger = $this->spyChannel();

        // 'filtered_out' rutin sebep listesinde YOK → info'ya yükselir.
        AutomationLog::summary('blog.generate', ['queued' => 0], reasons: ['filtered_out']);

        $logger->shouldHaveReceived('log')->with('info', 'blog.generate', Mockery::any())->once();
    }

    public function test_errors_are_not_silenced_by_routine_reasons(): void
    {
        $logger = $this->spyChannel();

        // Yanındaki sebepler rutin olsa BİLE hata satırı yükselir (spec §6, madde 2).
        AutomationLog::summary(
            'gsc.index',
            ['checked' => 0],
            reasons: ['not_configured', 'nothing_due'],
            errors: ['403 PERMISSION_DENIED'],
        );

        $logger->shouldHaveReceived('log')->with('warning', 'gsc.index', Mockery::any())->once();
    }

    public function test_every_round_writes_a_line_even_with_no_change(): void
    {
        $logger = $this->spyChannel();

        // "Hiç koşmadı" ile "koştu ama atladı" ayrımı için satır ŞART (spec §6, madde 1).
        AutomationLog::summary('links.apply', ['changed' => 0], reasons: ['unchanged']);

        $logger->shouldHaveReceived('log')->once();
    }

    public function test_automation_channel_is_a_separate_file(): void
    {
        $automation = config('logging.channels.automation');

        $this->assertNotNull($automation, 'automation kanalı tanımlı olmalı');
        $this->assertSame('daily', $automation['driver']);
        $this->assertStringContainsString('automation.log', $automation['path']);

        // Ana log'a yazmamalı: sık koşan görevler gerçek hataları gömmesin.
        $this->assertNotSame(
            config('logging.channels.single.path'),
            $automation['path'],
            'otomasyon günlüğü ana log dosyasından AYRI olmalı',
        );
    }

    public function test_logging_failure_never_breaks_the_caller(): void
    {
        Log::shouldReceive('channel')->with('automation')->andThrow(new \RuntimeException('kanal yok'));
        Log::shouldReceive('log')->once(); // varsayılan kanala DÜŞER, sessizce yutmaz

        AutomationLog::summary('test.task', ['x' => 1], changed: true);

        $this->assertTrue(true, 'istisna dışarı sızmamalı');
    }

    public function test_publish_frequency_matches_decision_precision(): void
    {
        $events = collect(app(Schedule::class)->events());

        $publish = $events->first(fn ($e) => str_contains((string) $e->description, 'blog:publish-due'));

        $this->assertNotNull($publish, 'blog:publish-due zamanlanmış olmalı');

        // Yayın kararı DAKİKA hassasiyetinde; 5 dakikada bir koşmak kararı geciktirir (spec §7.14).
        $this->assertSame('* * * * *', $publish->expression);
    }

    public function test_data_fetch_runs_before_derived_tasks(): void
    {
        $events = collect(app(Schedule::class)->events());

        $minutes = function (string $command) use ($events): int {
            $event = $events->first(fn ($e) => str_contains((string) $e->description, $command));
            $this->assertNotNull($event, $command.' zamanlanmış olmalı');

            [$minute, $hour] = explode(' ', $event->expression);

            return ((int) $hour) * 60 + (int) $minute;
        };

        // Önce veri çekilir, sonra ondan türetilen işler koşar (spec §4).
        $fetch = $minutes('seo:sync-search-console');

        $this->assertLessThan($minutes('seo:discover-keywords'), $fetch);
        $this->assertLessThan($minutes('seo:refresh-meta'), $fetch);
        $this->assertLessThan($minutes('seo:suggest-redirects'), $fetch);
    }

    public function test_all_scheduled_tasks_guard_against_overlap(): void
    {
        foreach (app(Schedule::class)->events() as $event) {
            $this->assertTrue(
                $event->withoutOverlapping,
                ($event->description ?? '?').' withoutOverlapping olmalı',
            );
        }
    }
}
