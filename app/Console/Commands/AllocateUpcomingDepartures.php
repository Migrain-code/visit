<?php

namespace App\Console\Commands;

use App\Models\TourDeparture;
use App\Services\Allocation\DepartureAllocator;
use App\Support\AutomationLog;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tur zamanı yaklaşan seferlerde araçsız kalan grupları kendiliğinden yerleştirir.
 *
 * YALNIZ bekleyen gruplara dokunur: yerleşmiş ve elle sabitlenmiş gruplar yerinde
 * kalır. Yolculara araç bilgisi verildikten sonra gece çalışan bir görevin dağılımı
 * baştan karıştırması istenmez; "baştan dağıt" kararı panelde, insana aittir.
 *
 * Grup bölünmez: bütün hâlinde hiçbir araca sığmayan grup bekleyenlerde kalır ve
 * günlükte raporlanır; panel de o seferi uyarı rozetiyle gösterir.
 */
class AllocateUpcomingDepartures extends Command
{
    protected $signature = 'tours:allocate-upcoming {--hours= : Kalkışa kaç saat kala (varsayılan: ayarlardaki değer, yoksa 48)}';

    protected $description = 'Kalkışı yaklaşan seferlerde araçsız kalan grupları boş koltuklara yerleştirir (gruplar bölünmez)';

    public function handle(DepartureAllocator $allocator): int
    {
        $hours = (int) ($this->option('hours') ?: setting('auto_allocate_hours', 48));

        if ($hours <= 0) {
            AutomationLog::summary('tours.allocate', reasons: ['disabled']);
            $this->info('Otomatik yerleştirme kapalı (saat değeri 0).');

            return self::SUCCESS;
        }

        $departures = TourDeparture::query()
            ->whereBetween('starts_at', [now(), now()->addHours($hours)])
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->whereHas('vehicles')
            ->whereHas('seatHoldingGroups', fn (Builder $q) => $q->whereNull('departure_vehicle_id')->where('passenger_count', '>', 0))
            ->with('tour:id,title')
            ->orderBy('starts_at')
            ->get();

        if ($departures->isEmpty()) {
            AutomationLog::summary('tours.allocate', ['hours' => $hours], reasons: ['no_candidates']);
            $this->info("Önümüzdeki {$hours} saatte yerleştirme bekleyen sefer yok.");

            return self::SUCCESS;
        }

        $placed = 0;
        $waiting = [];

        foreach ($departures as $departure) {
            $report = $allocator->allocate($departure, keepExisting: true);
            $placed += $report->movedGroups;

            $this->line(sprintf('  %s: %s', $departure->label, $report->headline()));

            if (! $report->allPlaced()) {
                $waiting[] = $departure->code.' ('.$report->unplacedPassengers().' yolcu)';
            }
        }

        AutomationLog::summary('tours.allocate', [
            'hours' => $hours,
            'departures' => $departures->count(),
            'groups_placed' => $placed,
        ], errors: $waiting === [] ? [] : ['Bölünmeden sığmayan gruplar var: '.implode(', ', $waiting)], changed: $placed > 0);

        $this->info("{$departures->count()} sefer işlendi, {$placed} grup yerleştirildi.");

        if ($waiting !== []) {
            $this->warn('Araç eklenmesi gereken seferler: '.implode(', ', $waiting));
        }

        return self::SUCCESS;
    }
}
