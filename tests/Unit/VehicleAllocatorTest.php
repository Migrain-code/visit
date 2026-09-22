<?php

namespace Tests\Unit;

use App\Services\Allocation\AllocationResult;
use App\Services\Allocation\VehicleAllocator;
use PHPUnit\Framework\TestCase;

/**
 * Dağıtım kuralları. En önemlisi: GRUP BÖLÜNMEZ ve araç kapasitesi AŞILMAZ.
 */
class VehicleAllocatorTest extends TestCase
{
    /** @param array<int, int> $capacities  @return array<int, array{id: int, capacity: int}> */
    private function fleet(array $capacities): array
    {
        $vehicles = [];

        foreach (array_values($capacities) as $i => $capacity) {
            $vehicles[] = ['id' => 100 + $i, 'capacity' => $capacity];
        }

        return $vehicles;
    }

    /** @param array<int, int> $sizes  @return array<int, array{id: int, size: int}> */
    private function parties(array $sizes): array
    {
        $groups = [];

        foreach (array_values($sizes) as $i => $size) {
            $groups[] = ['id' => $i + 1, 'size' => $size];
        }

        return $groups;
    }

    /** Hiçbir araç taşmamalı; yük toplamları atamalarla tutarlı olmalı. */
    private function assertValid(array $vehicles, array $groups, AllocationResult $result): void
    {
        $capacity = array_column($vehicles, 'capacity', 'id');
        $loads = array_fill_keys(array_keys($capacity), 0);

        foreach ($groups as $group) {
            $this->assertArrayHasKey($group['id'], $result->assignments, 'Her grubun bir sonucu olmalı.');
            $vehicle = $result->assignments[$group['id']];

            if ($vehicle === null) {
                continue;
            }

            $this->assertArrayHasKey($vehicle, $capacity, 'Grup var olmayan bir araca atandı.');
            $loads[$vehicle] += $group['size'];
        }

        foreach ($loads as $vehicle => $load) {
            if (! isset($result->overloaded[$vehicle])) {
                $this->assertLessThanOrEqual($capacity[$vehicle], $load, "Araç {$vehicle} kapasitesini aştı.");
            }

            $this->assertSame($load, $result->loads[$vehicle]);
        }
    }

    public function test_a_group_that_does_not_fit_moves_whole_to_another_vehicle(): void
    {
        // 19 koltuklu araç: 5+5+5 = 15 dolu, 4 boş. Sıradaki 4'lü sığar, sonraki 4'lü SIĞMAZ
        // ve bölünmeden ikinci araca geçer.
        $vehicles = $this->fleet([19, 19]);
        $groups = $this->parties([5, 5, 5, 4, 4]);

        $result = (new VehicleAllocator)->allocate($vehicles, $groups);

        $this->assertValid($vehicles, $groups, $result);
        $this->assertTrue($result->allPlaced());
        $this->assertSame(19, $result->loads[100], 'İlk araç tam dolmalı.');
        $this->assertSame(4, $result->loads[101]);
    }

    public function test_group_is_left_out_rather_than_split(): void
    {
        // Toplam 7 boş koltuk var (4 + 3) ama 5 kişilik grup hiçbir araca BÜTÜN sığmıyor.
        $vehicles = $this->fleet([4, 3]);
        $groups = $this->parties([5]);

        $result = (new VehicleAllocator)->allocate($vehicles, $groups);

        $this->assertNull($result->assignments[1]);
        $this->assertSame(AllocationResult::REASON_TOO_BIG, $result->unplaced[1]);
        $this->assertSame([100 => 0, 101 => 0], $result->loads);
    }

    public function test_vehicles_are_filled_in_list_order(): void
    {
        $vehicles = $this->fleet([19, 19]);
        $groups = $this->parties([3, 4, 5, 5, 9, 10]);

        $result = (new VehicleAllocator)->allocate($vehicles, $groups);

        $this->assertValid($vehicles, $groups, $result);
        $this->assertTrue($result->allPlaced());
        $this->assertSame(19, $result->loads[100]);
        $this->assertSame(17, $result->loads[101]);
    }

    public function test_uses_the_fewest_vehicles_and_reports_the_empty_ones(): void
    {
        // 40 yolcu: 50'lik tek araç yeter; 19 ve 24'lük boş kalır.
        $vehicles = $this->fleet([19, 24, 50]);
        $groups = $this->parties([5, 5, 5, 5, 4, 4, 4, 4, 2, 2]);

        $result = (new VehicleAllocator)->allocate($vehicles, $groups);

        $this->assertValid($vehicles, $groups, $result);
        $this->assertTrue($result->allPlaced());
        $this->assertSame([102], $result->usedVehicles());
        $this->assertSame([100, 101], $result->emptyVehicles());
    }

    public function test_prefers_fewer_seats_when_vehicle_count_is_equal(): void
    {
        // 20 yolcu tek araca sığar: 24'lük, 50'likten önce seçilir.
        $vehicles = $this->fleet([50, 24, 19]);
        $groups = $this->parties([5, 5, 5, 5]);

        $result = (new VehicleAllocator)->allocate($vehicles, $groups);

        $this->assertSame([101], $result->usedVehicles());
    }

    public function test_finds_a_full_placement_that_first_fit_would_miss(): void
    {
        // Büyükten küçüğe "ilk sığana koy" burada başarısız olur:
        // 10'luk araca 6+3, 10'luk araca 5+... → 4+4 açıkta kalır. Doğru çözüm: 6+4 | 5+3+...
        $vehicles = $this->fleet([10, 11]);
        $groups = $this->parties([6, 5, 4, 3, 3]);

        $result = (new VehicleAllocator)->allocate($vehicles, $groups);

        $this->assertValid($vehicles, $groups, $result);
        $this->assertTrue($result->allPlaced(), 'Toplam 21 yolcu 21 koltuğa bölünmeden sığar: 6+4 ve 5+3+3.');
    }

    public function test_places_the_most_passengers_when_not_everyone_fits(): void
    {
        $vehicles = $this->fleet([9, 9]);
        $groups = $this->parties([4, 4, 4, 3, 3]);

        $result = (new VehicleAllocator)->allocate($vehicles, $groups);

        $this->assertValid($vehicles, $groups, $result);
        // 9 elde edilemez (4+4=8, 4+3=7, 3+3=6...). En iyisi 8 + 7 = 15.
        $this->assertSame(15, array_sum($result->loads));
        $this->assertCount(1, $result->unplaced);
        $this->assertSame(AllocationResult::REASON_NO_SPACE, array_values($result->unplaced)[0]);
    }

    public function test_pinned_groups_stay_and_reduce_free_seats(): void
    {
        $vehicles = $this->fleet([10, 10]);
        $groups = [
            ['id' => 1, 'size' => 6, 'fixed' => 101],
            ['id' => 2, 'size' => 6],
            ['id' => 3, 'size' => 4],
            ['id' => 4, 'size' => 4],
        ];

        $result = (new VehicleAllocator)->allocate($vehicles, $groups);

        $this->assertValid($vehicles, $groups, $result);
        $this->assertSame(101, $result->assignments[1], 'Sabitlenen grup yerinden oynamaz.');
        $this->assertTrue($result->allPlaced());
        $this->assertSame([100 => 10, 101 => 10], $result->loads);
    }

    public function test_overloaded_pins_are_reported_and_nobody_is_added_there(): void
    {
        $vehicles = $this->fleet([5, 10]);
        $groups = [
            ['id' => 1, 'size' => 4, 'fixed' => 100],
            ['id' => 2, 'size' => 3, 'fixed' => 100],
            ['id' => 3, 'size' => 2],
        ];

        $result = (new VehicleAllocator)->allocate($vehicles, $groups);

        $this->assertSame([100 => 2], $result->overloaded);
        $this->assertSame(101, $result->assignments[3]);
    }

    public function test_no_vehicles_means_everyone_waits(): void
    {
        $result = (new VehicleAllocator)->allocate([], $this->parties([2, 3]));

        $this->assertSame([1 => AllocationResult::REASON_NO_VEHICLE, 2 => AllocationResult::REASON_NO_VEHICLE], $result->unplaced);
    }

    public function test_empty_groups_take_no_seat(): void
    {
        $vehicles = $this->fleet([5]);
        $groups = $this->parties([0, 5]);

        $result = (new VehicleAllocator)->allocate($vehicles, $groups);

        $this->assertNull($result->assignments[1]);
        $this->assertSame(100, $result->assignments[2]);
        $this->assertTrue($result->allPlaced(), 'Yolcusu olmayan grup "açıkta kaldı" sayılmaz.');
    }

    /**
     * Küçük örneklerde TÜM olasılıkları deneyen kaba kuvvetle karşılaştırma:
     * algoritma en çok yolcuyu yerleştirmeli; herkes sığıyorsa en az araçla ve
     * (eşitlikte) en az koltukla sığdırmalı.
     */
    public function test_matches_brute_force_on_random_small_instances(): void
    {
        mt_srand(20260922);
        $allocator = new VehicleAllocator;

        for ($round = 0; $round < 400; $round++) {
            $capacities = [];

            for ($i = 0, $n = mt_rand(1, 3); $i < $n; $i++) {
                $capacities[] = mt_rand(3, 12);
            }

            $sizes = [];

            for ($i = 0, $n = mt_rand(1, 7); $i < $n; $i++) {
                $sizes[] = mt_rand(1, 6);
            }

            $vehicles = $this->fleet($capacities);
            $groups = $this->parties($sizes);
            $result = $allocator->allocate($vehicles, $groups);

            $this->assertValid($vehicles, $groups, $result);
            $this->assertTrue($result->exact);

            [$bestPlaced, $bestVehicles, $bestSeats] = $this->bruteForce($capacities, $sizes);
            $label = 'araçlar=['.implode(',', $capacities).'] gruplar=['.implode(',', $sizes).']';

            $this->assertSame($bestPlaced, array_sum($result->loads), "Yerleşen yolcu sayısı en iyi değil: {$label}");

            if ($bestPlaced === array_sum($sizes)) {
                $used = $result->usedVehicles();
                $seats = array_sum(array_map(fn ($id) => $capacities[$id - 100], $used));

                $this->assertCount($bestVehicles, $used, "Gereğinden çok araç kullanıldı: {$label}");
                $this->assertSame($bestSeats, $seats, "Daha az koltuklu araçlarla sığardı: {$label}");
            }
        }
    }

    /** @return array{0: int, 1: int, 2: int} en çok yolcu, (herkes sığıyorsa) en az araç ve o araç sayısında en az koltuk */
    private function bruteForce(array $capacities, array $sizes): array
    {
        $bins = count($capacities);
        $total = array_sum($sizes);
        $bestPlaced = 0;
        $bestVehicles = PHP_INT_MAX;
        $bestSeats = PHP_INT_MAX;
        $options = $bins + 1; // her grup: bir araç ya da açıkta
        $combos = $options ** count($sizes);

        for ($code = 0; $code < $combos; $code++) {
            $loads = array_fill(0, $bins, 0);
            $placed = 0;
            $rest = $code;
            $valid = true;

            foreach ($sizes as $size) {
                $choice = $rest % $options;
                $rest = intdiv($rest, $options);

                if ($choice === $bins) {
                    continue;
                }

                $loads[$choice] += $size;
                $placed += $size;

                if ($loads[$choice] > $capacities[$choice]) {
                    $valid = false;

                    break;
                }
            }

            if (! $valid) {
                continue;
            }

            $bestPlaced = max($bestPlaced, $placed);

            if ($placed === $total) {
                $used = 0;
                $seats = 0;

                foreach ($loads as $bin => $load) {
                    if ($load > 0) {
                        $used++;
                        $seats += $capacities[$bin];
                    }
                }

                if ([$used, $seats] < [$bestVehicles, $bestSeats]) {
                    [$bestVehicles, $bestSeats] = [$used, $seats];
                }
            }
        }

        return [$bestPlaced, $bestVehicles, $bestSeats];
    }

    public function test_a_busy_departure_is_allocated_quickly(): void
    {
        mt_srand(7);
        $sizes = [];

        for ($i = 0; $i < 70; $i++) {
            $sizes[] = mt_rand(1, 6);
        }

        $vehicles = $this->fleet([50, 50, 50, 46, 24, 19, 19]);
        $groups = $this->parties($sizes);

        $started = microtime(true);
        $result = (new VehicleAllocator)->allocate($vehicles, $groups);
        $elapsed = microtime(true) - $started;

        $this->assertValid($vehicles, $groups, $result);
        $this->assertTrue($result->allPlaced());
        $this->assertLessThan(3.0, $elapsed, 'Dağıtım panelde beklenmeyecek kadar hızlı olmalı.');
    }

    public function test_hard_instance_stays_valid_even_if_the_search_budget_runs_out(): void
    {
        mt_srand(99);
        $sizes = [];

        for ($i = 0; $i < 90; $i++) {
            $sizes[] = mt_rand(2, 7);
        }

        // Koltuk yetmiyor: en çok yolcu araması zor bir örnek.
        $vehicles = $this->fleet([50, 50, 46, 31, 27, 19, 17, 13]);
        $groups = $this->parties($sizes);

        $started = microtime(true);
        $result = (new VehicleAllocator)->allocate($vehicles, $groups);

        $this->assertValid($vehicles, $groups, $result);
        $this->assertLessThan(8.0, microtime(true) - $started);
        $this->assertGreaterThan(240, array_sum($result->loads), 'Koltukların büyük kısmı dolmalı (toplam 253).');
    }
}
