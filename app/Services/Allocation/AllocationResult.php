<?php

namespace App\Services\Allocation;

/**
 * Dağıtımın SAF sonucu: hangi grup hangi araca gitti, kim açıkta kaldı.
 * Veritabanına dokunmaz; yazma işi DepartureAllocator'dadır.
 */
final class AllocationResult
{
    public const REASON_NO_VEHICLE = 'no_vehicle';

    public const REASON_TOO_BIG = 'too_big';

    public const REASON_NO_SPACE = 'no_space';

    /**
     * @param  array<int|string, int|string|null>  $assignments  grup kimliği => araç kimliği (yerleşmediyse null)
     * @param  array<int|string, string>  $unplaced  grup kimliği => neden (REASON_*)
     * @param  array<int|string, int>  $loads  araç kimliği => dolu koltuk
     * @param  array<int|string, int>  $overloaded  araç kimliği => taşan koltuk (yalnız elle sabitlenenlerden doğar)
     * @param  bool  $exact  arama sınırına takılmadan bitti mi (sonuç kanıtlanmış en iyi mi)
     */
    public function __construct(
        public readonly array $assignments,
        public readonly array $unplaced,
        public readonly array $loads,
        public readonly array $overloaded,
        public readonly bool $exact,
    ) {}

    public function allPlaced(): bool
    {
        return $this->unplaced === [];
    }

    /** @return array<int, int|string> Yolcu taşıyan araçlar */
    public function usedVehicles(): array
    {
        return array_keys(array_filter($this->loads, fn (int $load) => $load > 0));
    }

    /** @return array<int, int|string> Boş kalan araçlar */
    public function emptyVehicles(): array
    {
        return array_keys(array_filter($this->loads, fn (int $load) => $load === 0));
    }
}
