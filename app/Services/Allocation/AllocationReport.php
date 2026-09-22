<?php

namespace App\Services\Allocation;

use App\Models\TourGroup;
use Illuminate\Support\Collection;

/**
 * Bir seferin dağıtım sonucu — panelde gösterilecek hâliyle.
 */
final class AllocationReport
{
    /**
     * @param  Collection<int, TourGroup>  $unplaced  yerleşmeyen gruplar
     * @param  array<int, string>  $reasons  grup kimliği => açıklama
     * @param  array<int, string>  $emptyVehicles  boş kalan araçların adları
     * @param  array<int, string>  $overloaded  elle sabitleme yüzünden taşan araçlar
     */
    public function __construct(
        public readonly int $placedGroups,
        public readonly int $placedPassengers,
        public readonly int $movedGroups,
        public readonly Collection $unplaced,
        public readonly array $reasons,
        public readonly array $emptyVehicles,
        public readonly array $overloaded,
        public readonly ?string $suggestion,
        public readonly bool $exact,
    ) {}

    public function allPlaced(): bool
    {
        return $this->unplaced->isEmpty();
    }

    public function unplacedPassengers(): int
    {
        return (int) $this->unplaced->sum('passenger_count');
    }

    /** Bildirim başlığı. */
    public function headline(): string
    {
        if ($this->placedGroups === 0 && $this->unplaced->isEmpty()) {
            return 'Dağıtılacak grup yok.';
        }

        return $this->allPlaced()
            ? "{$this->placedGroups} grup ({$this->placedPassengers} yolcu) araçlara yerleştirildi."
            : "{$this->unplaced->count()} grup ({$this->unplacedPassengers()} yolcu) hiçbir araca BÖLÜNMEDEN sığmadı.";
    }

    /** Bildirim gövdesi: satır satır. @return array<int, string> */
    public function lines(): array
    {
        $lines = [];

        if (! $this->allPlaced()) {
            $lines[] = "Yerleşen: {$this->placedGroups} grup, {$this->placedPassengers} yolcu.";

            foreach ($this->unplaced as $group) {
                $lines[] = '• '.$group->display_name.' — '.($this->reasons[$group->getKey()] ?? 'yer yok');
            }
        }

        if ($this->suggestion) {
            $lines[] = $this->suggestion;
        }

        foreach ($this->overloaded as $message) {
            $lines[] = $message;
        }

        if ($this->emptyVehicles !== []) {
            $lines[] = 'Boş kalan araç: '.implode(', ', $this->emptyVehicles).'. İhtiyaç yoksa seferden çıkarabilirsiniz.';
        }

        if (! $this->exact) {
            $lines[] = 'Not: grup sayısı çok fazla olduğu için arama süre sınırında durduruldu; sonuç geçerli ama en iyi dağılım olmayabilir.';
        }

        return $lines;
    }
}
