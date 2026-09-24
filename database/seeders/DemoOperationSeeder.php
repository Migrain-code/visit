<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\GroupStatus;
use App\Models\TourDeparture;
use App\Models\TourGroup;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

/**
 * ÖRNEK operasyon verisi: ilk tura araçlar ve örnek gruplar.
 *
 * Yalnız yerel geliştirme ve testte kendiliğinden çalışır. Canlı sitede uydurma
 * yolcu kaydı İSTENMEZ; bu yüzden DatabaseSeeder onu canlıda çağırmaz.
 * Yolcu adları ve kimlik numaraları kurgusaldır.
 */
class DemoOperationSeeder extends Seeder
{
    public function run(): void
    {
        if (TourGroup::query()->exists()) {
            return;
        }

        $first = TourDeparture::query()->orderBy('starts_at')->first();

        if (! $first) {
            return;
        }

        $fleet = Vehicle::query()->get()->keyBy('seat_count');

        if ($first->vehicles()->doesntExist()) {
            foreach ([19, 24] as $seatCount) {
                if ($vehicle = $fleet->get($seatCount)) {
                    $first->vehicles()->create(['vehicle_id' => $vehicle->getKey()]);
                }
            }
        }

        $this->seedGroups($first);
    }

    /** İlk tura farklı büyüklükte gruplar: dağıtım panosunu denemek için. */
    private function seedGroups(TourDeparture $departure): void
    {
        $groups = [
            [['Ahmet', 'Ayşe', 'Elif', 'Can', 'Zeynep'], 'Yılmaz', 'Yerleşke önü'],
            [['Mehmet', 'Fatma', 'Ali', 'Selin'], 'Demir', 'Rize Otogar'],
            [['Hasan', 'Emine', 'Burak', 'Deniz'], 'Kaya', 'Yerleşke önü'],
            [['Mustafa', 'Hatice', 'Ece', 'Mert'], 'Şahin', 'Çayeli'],
            [['İbrahim', 'Zehra', 'Yusuf', 'Defne', 'Kerem'], 'Çelik', 'Yerleşke önü'],
            [['Osman', 'Meryem', 'Emre'], 'Arslan', 'Ardeşen'],
            [['Hüseyin', 'Sultan', 'Melis'], 'Doğan', 'Yerleşke önü'],
            [['Murat', 'Gül'], 'Aydın', 'Rize Otogar'],
            [['Kemal', 'Nur'], 'Öztürk', 'Yerleşke önü'],
            [['Serkan', 'Derya', 'Arda', 'İpek'], 'Koç', 'Pazar'],
        ];

        $female = ['Ayşe', 'Elif', 'Zeynep', 'Fatma', 'Selin', 'Emine', 'Deniz', 'Hatice', 'Ece', 'Zehra', 'Defne', 'Meryem', 'Sultan', 'Melis', 'Gül', 'Nur', 'Derya', 'İpek'];
        $serial = 0;

        foreach ($groups as $gi => [$names, $surname, $pickup]) {
            $group = TourGroup::query()->create([
                'tour_departure_id' => $departure->getKey(),
                'status' => GroupStatus::Confirmed->value,
                'notes' => 'ÖRNEK KAYIT — silinebilir.',
            ]);

            foreach ($names as $pi => $name) {
                $group->passengers()->create([
                    'first_name' => $name,
                    'last_name' => $surname,
                    'tc_no' => $this->fakeTc(++$serial),
                    'phone' => $pi === 0 ? '0532 000 '.str_pad((string) (10 + $gi), 2, '0', STR_PAD_LEFT).' '.str_pad((string) (20 + $gi), 2, '0', STR_PAD_LEFT) : null,
                    'pickup_point' => $pickup,
                    'age' => 19 + (($gi + $pi) % 6),
                    'gender' => in_array($name, $female, true) ? Gender::Female->value : Gender::Male->value,
                    'sort_order' => $pi + 1,
                ]);
            }
        }
    }

    /** Biçimce geçerli ama KURGUSAL T.C. kimlik numarası (kontrol basamakları hesaplanır). */
    private function fakeTc(int $serial): string
    {
        $digits = array_map('intval', str_split('10000'.str_pad((string) (1000 + $serial), 4, '0', STR_PAD_LEFT)));
        $odd = $digits[0] + $digits[2] + $digits[4] + $digits[6] + $digits[8];
        $even = $digits[1] + $digits[3] + $digits[5] + $digits[7];
        $digits[9] = ((($odd * 7 - $even) % 10) + 10) % 10;
        $digits[10] = array_sum(array_slice($digits, 0, 10)) % 10;

        return implode('', $digits);
    }
}
