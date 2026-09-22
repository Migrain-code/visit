<?php

namespace App\Services\Allocation;

/**
 * Grupları araçlara yerleştirir — GRUP ASLA BÖLÜNMEZ.
 *
 * Bir grup ya tamamıyla tek bir araca biner ya da hiç yerleşmez. "4 kişilik grup 19
 * koltuklu araçta kalan 3 koltuğa sığmadı" durumunda grubun 3'ü o araca, 1'i başka
 * araca KONMAZ; grup bütün hâlinde başka bir araca gider. Hiçbir araca bütün hâlinde
 * sığmıyorsa açıkta kalır ve kullanıcıya bildirilir.
 *
 * Hedefler, öncelik sırasıyla:
 *   1. En çok yolcuyu yerleştir (mümkünse herkesi).
 *   2. Herkes sığıyorsa EN AZ araçla sığdır; eşitlikte toplam koltuğu en az olan araçları seç.
 *      Boş kalan araç kullanıcıya "iptal edilebilir" diye gösterilir.
 *   3. Seçilen araçları LİSTE SIRASIYLA doldur: ilk araç olabildiğince tam dolar, boşluk
 *      son araçta toplanır.
 *
 * Bu, kutu paketleme probleminin (bin packing) değişken kutulu hâlidir ve genel hâliyle
 * NP-zordur. Gerçek bir seferde 2-6 araç ve en çok birkaç düzine grup olduğu için tam
 * arama pratikte anında biter. Yine de arama bir düğüm bütçesiyle sınırlıdır; bütçe
 * aşılırsa o ana kadarki en iyi sonuç döner ve $exact=false işaretlenir. Kurallar
 * (bölünmeme, kapasiteyi aşmama) her durumda geçerlidir; bütçe yalnız "en iyisi bu mu"
 * kanıtını etkiler.
 *
 * Sınıf SAFTIR: veritabanı bilmez. Girdi/çıktı düz dizilerdir, bu yüzden binlerce
 * rastgele örnekle test edilebilir.
 */
class VehicleAllocator
{
    /** Tek bir çağrıda gezilecek en çok arama düğümü. */
    private const NODE_BUDGET = 400_000;

    private int $nodes = 0;

    private bool $exhausted = false;

    /**
     * @param  array<int, array{id: int|string, capacity: int}>  $vehicles  doldurma sırasıyla
     * @param  array<int, array{id: int|string, size: int, fixed?: int|string|null}>  $groups  kayıt sırasıyla;
     *                                                                                         "fixed" = elle sabitlenen araç
     */
    public function allocate(array $vehicles, array $groups): AllocationResult
    {
        $this->nodes = 0;
        $this->exhausted = false;

        $vehicles = array_values($vehicles);
        $capacity = [];

        foreach ($vehicles as $vehicle) {
            $capacity[$vehicle['id']] = max(0, (int) $vehicle['capacity']);
        }

        $order = array_keys($capacity);
        $residual = $capacity;
        $assignments = [];
        $unplaced = [];
        $free = [];

        // 1) Elle sabitlenen gruplar yerinde kalır; kalan koltuk onlara göre hesaplanır.
        foreach ($groups as $group) {
            $id = $group['id'];
            $size = max(0, (int) $group['size']);
            $fixed = $group['fixed'] ?? null;

            if ($fixed !== null && array_key_exists($fixed, $capacity)) {
                $assignments[$id] = $fixed;
                $residual[$fixed] -= $size;

                continue;
            }

            $assignments[$id] = null;

            if ($size > 0) {
                $free[] = ['id' => $id, 'size' => $size];
            }
        }

        // Sabitlenenler aracı taşırdıysa bu raporlanır; o araca başka kimse konmaz.
        $overloaded = [];

        foreach ($residual as $vehicleId => $left) {
            if ($left < 0) {
                $overloaded[$vehicleId] = -$left;
                $residual[$vehicleId] = 0;
            }
        }

        if ($free !== []) {
            if ($order === []) {
                foreach ($free as $group) {
                    $unplaced[$group['id']] = AllocationResult::REASON_NO_VEHICLE;
                }
            } else {
                [$placed, $unplaced] = $this->place($free, $order, $residual, $capacity);

                foreach ($placed as $groupId => $vehicleId) {
                    $assignments[$groupId] = $vehicleId;
                }
            }
        }

        $loads = array_fill_keys($order, 0);
        $sizes = [];

        foreach ($groups as $group) {
            $sizes[$group['id']] = max(0, (int) $group['size']);
        }

        foreach ($assignments as $groupId => $vehicleId) {
            if ($vehicleId !== null) {
                $loads[$vehicleId] += $sizes[$groupId];
            }
        }

        return new AllocationResult($assignments, $unplaced, $loads, $overloaded, ! $this->exhausted);
    }

    /**
     * Serbest grupları yerleştirir.
     *
     * @param  array<int, array{id: int|string, size: int}>  $groups
     * @param  array<int, int|string>  $order  araç kimlikleri, doldurma sırasıyla
     * @param  array<int|string, int>  $residual  araç => boş koltuk
     * @param  array<int|string, int>  $capacity  araç => toplam koltuk
     * @return array{0: array<int|string, int|string>, 1: array<int|string, string>}
     */
    private function place(array $groups, array $order, array $residual, array $capacity): array
    {
        $unplaced = [];
        $maxResidual = max($residual);
        $maxCapacity = max($capacity);

        // Hiçbir aracın boş yerine bütün hâlinde sığmayan grup baştan elenir.
        $candidates = [];

        foreach ($groups as $group) {
            if ($group['size'] > $maxResidual) {
                $unplaced[$group['id']] = $group['size'] > $maxCapacity
                    ? AllocationResult::REASON_TOO_BIG
                    : AllocationResult::REASON_NO_SPACE;

                continue;
            }

            $candidates[] = $group;
        }

        if ($candidates === []) {
            return [[], $unplaced];
        }

        // Büyük grup önce: yerleştirmesi en zor olan odur. usort kararlı olduğundan
        // eşit büyüklükte kayıt sırası korunur.
        usort($candidates, fn (array $a, array $b) => $b['size'] <=> $a['size']);

        $solution = $this->placeAll($candidates, $order, $residual, $capacity);

        if ($solution !== null) {
            return [$this->fillInOrder($candidates, $solution, $order, $residual), $unplaced];
        }

        // Herkes sığmıyor: en çok yolcuyu yerleştiren dağılım aranır.
        $best = $this->placeMost($candidates, $order, $residual);

        foreach ($candidates as $group) {
            if (! array_key_exists($group['id'], $best)) {
                $unplaced[$group['id']] = AllocationResult::REASON_NO_SPACE;
            }
        }

        return [$best, $unplaced];
    }

    /**
     * Herkesi yerleştiren EN UCUZ araç kümesini bulur: önce en az araç, sonra en az koltuk.
     *
     * İçinde zaten (sabitlenmiş) yolcu olan araç her durumda "kullanımda" sayılır.
     *
     * @return array<int|string, int|string>|null grup => araç; herkes sığmıyorsa null
     */
    private function placeAll(array $groups, array $order, array $residual, array $capacity): ?array
    {
        $total = array_sum(array_column($groups, 'size'));
        $largest = $groups[0]['size'];

        $inUse = [];
        $optional = [];

        foreach ($order as $vehicleId) {
            if ($residual[$vehicleId] < $capacity[$vehicleId]) {
                $inUse[] = $vehicleId;
            } elseif ($residual[$vehicleId] > 0) {
                $optional[] = $vehicleId;
            }
        }

        foreach ($this->subsetsByCost($optional, $capacity) as $subset) {
            $bins = array_values(array_filter($order, fn ($id) => in_array($id, $inUse, true) || in_array($id, $subset, true)));

            if ($bins === []) {
                continue;
            }

            $binResidual = array_map(fn ($id) => $residual[$id], $bins);

            if (array_sum($binResidual) < $total || max($binResidual) < $largest) {
                continue;
            }

            $packed = $this->pack(array_column($groups, 'size'), $binResidual);

            if ($packed !== null) {
                $solution = [];

                foreach ($packed as $index => $bin) {
                    $solution[$groups[$index]['id']] = $bins[$bin];
                }

                return $solution;
            }

            if ($this->exhausted) {
                break;
            }
        }

        return null;
    }

    /**
     * İsteğe bağlı araçların alt kümeleri, maliyet sırasıyla: araç sayısı, toplam koltuk, liste sırası.
     *
     * @param  array<int, int|string>  $optional
     * @return array<int, array<int, int|string>>
     */
    private function subsetsByCost(array $optional, array $capacity): array
    {
        $count = count($optional);

        // Çok kalabalık filoda 2^n alt küme yerine yalnız "ilk k araç" denenir.
        if ($count > 12) {
            $subsets = [];

            for ($k = 0; $k <= $count; $k++) {
                $subsets[] = array_slice($optional, 0, $k);
            }

            return $subsets;
        }

        $subsets = [];

        for ($mask = 0; $mask < (1 << $count); $mask++) {
            $subset = [];
            $seats = 0;

            for ($bit = 0; $bit < $count; $bit++) {
                if ($mask & (1 << $bit)) {
                    $subset[] = $optional[$bit];
                    $seats += $capacity[$optional[$bit]];
                }
            }

            // Eşitlikte listede önce gelen araçlar tercih edilir: maskenin düşük bitleri.
            $subsets[] = [count($subset), $seats, $this->reverseBits($mask, $count), $subset];
        }

        usort($subsets, fn (array $a, array $b) => [$a[0], $a[1], $b[2]] <=> [$b[0], $b[1], $a[2]]);

        return array_column($subsets, 3);
    }

    private function reverseBits(int $mask, int $width): int
    {
        $reversed = 0;

        for ($bit = 0; $bit < $width; $bit++) {
            if ($mask & (1 << $bit)) {
                $reversed |= 1 << ($width - 1 - $bit);
            }
        }

        return $reversed;
    }

    /**
     * Karar problemi: bu boyutlar bu kutulara BÖLMEDEN sığar mı?
     *
     * @param  array<int, int>  $sizes  büyükten küçüğe
     * @param  array<int, int>  $residual  kutu => boş yer
     * @return array<int, int>|null boyut sırası => kutu sırası
     */
    private function pack(array $sizes, array $residual): ?array
    {
        if ($sizes === []) {
            return [];
        }

        $suffix = $this->suffixSums($sizes);
        $failed = [];
        $chosen = [];

        return $this->packFrom(0, $sizes, $residual, $suffix, $failed, $chosen) ? $chosen : null;
    }

    private function packFrom(int $index, array $sizes, array $residual, array $suffix, array &$failed, array &$chosen): bool
    {
        if ($index === count($sizes)) {
            return true;
        }

        if (++$this->nodes > self::NODE_BUDGET) {
            $this->exhausted = true;

            return false;
        }

        if ($suffix[$index] > array_sum($residual)) {
            return false;
        }

        // Kalan gruplar için kutuların KİMLİĞİ değil boş yerleri önemlidir: aynı boş
        // yer dağılımına daha önce gelinip çıkmaz bulunduysa tekrar denenmez.
        $sorted = $residual;
        sort($sorted);
        $key = $index.':'.implode(',', $sorted);

        if (isset($failed[$key])) {
            return false;
        }

        $size = $sizes[$index];
        $tried = [];

        foreach ($residual as $bin => $left) {
            // Boş yeri aynı olan iki kutu bu grup için eşdeğerdir; biri yeter.
            if ($left < $size || isset($tried[$left])) {
                continue;
            }

            $tried[$left] = true;
            $residual[$bin] -= $size;
            $chosen[$index] = $bin;

            if ($this->packFrom($index + 1, $sizes, $residual, $suffix, $failed, $chosen)) {
                return true;
            }

            unset($chosen[$index]);
            $residual[$bin] += $size;

            if ($this->exhausted) {
                return false;
            }
        }

        $failed[$key] = true;

        return false;
    }

    /**
     * Bulunan çözümü "sırayla doldur" biçimine getirir: ilk araç olabildiğince tam
     * dolar, boş koltuklar son araçta toplanır. Her adımda kalan grupların kalan
     * araçlara hâlâ sığdığı DOĞRULANIR; doğrulanamayan adımda eldeki çözüm korunur.
     *
     * @param  array<int, array{id: int|string, size: int}>  $groups  büyükten küçüğe
     * @param  array<int|string, int|string>  $solution  grup => araç
     * @return array<int|string, int|string>
     */
    private function fillInOrder(array $groups, array $solution, array $order, array $residual): array
    {
        $bins = array_values(array_filter($order, fn ($id) => in_array($id, $solution, true)));
        $remaining = $groups;
        $final = [];

        foreach ($bins as $position => $bin) {
            if ($remaining === []) {
                break;
            }

            $later = array_slice($bins, $position + 1);

            if ($later === []) {
                foreach ($remaining as $group) {
                    $final[$group['id']] = $bin;
                }

                break;
            }

            $currentLoad = 0;

            foreach ($remaining as $group) {
                if ($solution[$group['id']] === $bin) {
                    $currentLoad += $group['size'];
                }
            }

            $improved = false;
            $sizes = array_column($remaining, 'size');
            $table = $this->subsetSumTable($sizes, $residual[$bin]);

            for ($target = $residual[$bin]; $target > $currentLoad; $target--) {
                if (! $table[count($sizes)][$target]) {
                    continue;
                }

                $picked = $this->subsetFor($table, $sizes, $target);
                $rest = array_values(array_filter($remaining, fn ($_, $i) => ! isset($picked[$i]), ARRAY_FILTER_USE_BOTH));
                $packed = $this->pack(array_column($rest, 'size'), array_map(fn ($id) => $residual[$id], $later));

                if ($packed === null) {
                    if ($this->exhausted) {
                        break;
                    }

                    continue;
                }

                foreach (array_keys($picked) as $i) {
                    $final[$remaining[$i]['id']] = $bin;
                }

                foreach ($packed as $i => $laterBin) {
                    $solution[$rest[$i]['id']] = $later[$laterBin];
                }

                $remaining = $rest;
                $improved = true;

                break;
            }

            if (! $improved) {
                $keep = [];

                foreach ($remaining as $group) {
                    if ($solution[$group['id']] === $bin) {
                        $final[$group['id']] = $bin;
                    } else {
                        $keep[] = $group;
                    }
                }

                $remaining = $keep;
            }
        }

        return $final;
    }

    /**
     * Herkes sığmadığında: en çok YOLCUYU yerleştiren dağılım (dal-sınır araması).
     *
     * @param  array<int, array{id: int|string, size: int}>  $groups  büyükten küçüğe
     * @return array<int|string, int|string> grup => araç (yerleşmeyenler dizide yok)
     */
    private function placeMost(array $groups, array $order, array $residual): array
    {
        $sizes = array_column($groups, 'size');
        $bins = array_values(array_filter($order, fn ($id) => $residual[$id] > 0));
        $binResidual = array_map(fn ($id) => $residual[$id], $bins);

        // Başlangıç çözümü: araçları sırayla, alt küme toplamıyla olabildiğince doldur.
        $best = $this->greedyFill($sizes, $binResidual);
        $bestPlaced = 0;

        foreach ($best as $index => $bin) {
            $bestPlaced += $sizes[$index];
        }

        $suffix = $this->suffixSums($sizes);
        $current = [];
        $seen = [];

        $this->searchMost(0, 0, $sizes, $binResidual, $suffix, $current, $best, $bestPlaced, $seen);

        $result = [];

        foreach ($best as $index => $bin) {
            $result[$groups[$index]['id']] = $bins[$bin];
        }

        return $result;
    }

    private function searchMost(int $index, int $placed, array $sizes, array $residual, array $suffix, array &$current, array &$best, int &$bestPlaced, array &$seen): void
    {
        if ($this->exhausted) {
            return;
        }

        // Üst sınır: kalan herkes yerleşse bile (ve koltuk yetse bile) en iyiyi geçemiyorsa bırak.
        if ($placed + min($suffix[$index], array_sum($residual)) <= $bestPlaced) {
            return;
        }

        if ($index === count($sizes)) {
            $bestPlaced = $placed;
            $best = $current;

            return;
        }

        if (++$this->nodes > self::NODE_BUDGET) {
            $this->exhausted = true;

            return;
        }

        // Aynı (sıra, boş yer dağılımı) durumuna daha fazla yolcuyla gelinmişse bu dal geçemez.
        $sorted = $residual;
        sort($sorted);
        $key = $index.':'.implode(',', $sorted);

        if (isset($seen[$key]) && $seen[$key] >= $placed) {
            return;
        }

        $seen[$key] = $placed;

        $size = $sizes[$index];
        $tried = [];

        foreach ($residual as $bin => $left) {
            if ($left < $size || isset($tried[$left])) {
                continue;
            }

            $tried[$left] = true;
            $residual[$bin] -= $size;
            $current[$index] = $bin;

            $this->searchMost($index + 1, $placed + $size, $sizes, $residual, $suffix, $current, $best, $bestPlaced, $seen);

            unset($current[$index]);
            $residual[$bin] += $size;
        }

        // Bu grubu açıkta bırakma seçeneği.
        $this->searchMost($index + 1, $placed, $sizes, $residual, $suffix, $current, $best, $bestPlaced, $seen);
    }

    /**
     * @param  array<int, int>  $sizes
     * @param  array<int, int>  $residual
     * @return array<int, int> boyut sırası => kutu sırası
     */
    private function greedyFill(array $sizes, array $residual): array
    {
        $assigned = [];
        $open = array_keys($sizes);

        foreach ($residual as $bin => $left) {
            if ($open === [] || $left <= 0) {
                continue;
            }

            $openSizes = array_map(fn ($i) => $sizes[$i], $open);
            $table = $this->subsetSumTable($openSizes, $left);

            for ($target = $left; $target > 0; $target--) {
                if ($table[count($openSizes)][$target]) {
                    foreach (array_keys($this->subsetFor($table, $openSizes, $target)) as $position) {
                        $assigned[$open[$position]] = $bin;
                    }

                    break;
                }
            }

            $open = array_values(array_filter($open, fn ($i) => ! isset($assigned[$i])));
        }

        return $assigned;
    }

    /**
     * Alt küme toplamı tablosu: $table[i][s] = ilk i boyuttan bazılarıyla tam s elde edilir mi?
     *
     * @param  array<int, int>  $sizes
     * @return array<int, array<int, bool>>
     */
    private function subsetSumTable(array $sizes, int $limit): array
    {
        $limit = max(0, $limit);
        $table = [array_fill(0, $limit + 1, false)];
        $table[0][0] = true;

        foreach (array_values($sizes) as $i => $size) {
            $row = $table[$i];

            for ($sum = $limit; $sum >= $size; $sum--) {
                if ($table[$i][$sum - $size]) {
                    $row[$sum] = true;
                }
            }

            $table[$i + 1] = $row;
        }

        return $table;
    }

    /**
     * Tablodan $target toplamını veren bir alt küme çıkarır. Sondan geriye yürürken
     * "bu boyutu KULLANMADAN da olur mu" sorusu önce sorulduğu için sondaki (küçük)
     * gruplar mümkünse dışarıda kalır: küçük gruplar sonraki araçlar için esnektir.
     *
     * @return array<int, true> seçilen sıra numaraları
     */
    private function subsetFor(array $table, array $sizes, int $target): array
    {
        $sizes = array_values($sizes);
        $picked = [];

        for ($i = count($sizes); $i > 0 && $target > 0; $i--) {
            if ($table[$i - 1][$target]) {
                continue;
            }

            $picked[$i - 1] = true;
            $target -= $sizes[$i - 1];
        }

        return $picked;
    }

    /** @return array<int, int> $suffix[i] = i. sıradan sona kadar boyutların toplamı */
    private function suffixSums(array $sizes): array
    {
        $suffix = array_fill(0, count($sizes) + 1, 0);

        for ($i = count($sizes) - 1; $i >= 0; $i--) {
            $suffix[$i] = $suffix[$i + 1] + $sizes[$i];
        }

        return $suffix;
    }
}
