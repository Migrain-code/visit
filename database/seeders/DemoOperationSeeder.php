<?php

namespace Database\Seeders;

use App\Enums\Gender;
use App\Enums\GroupStatus;
use App\Models\Tour;
use App\Models\TourDeparture;
use App\Models\TourGroup;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

/**
 * ÖRNEK operasyon verisi: yaklaşan seferler ve BİR seferde örnek gruplar.
 *
 * Yalnız yerel geliştirme ve testte kendiliğinden çalışır. Canlı sitede uydurma
 * tarih, fiyat ve yolcu kaydı İSTENMEZ; bu yüzden DatabaseSeeder onu canlıda çağırmaz.
 * Yolcu adları ve kimlik numaraları kurgusaldır.
 */
class DemoOperationSeeder extends Seeder
{
    public function run(): void
    {
        if (TourDeparture::query()->exists()) {
            return;
        }

        $fleet = Vehicle::query()->get()->keyBy('seat_count');

        // [tur slug, kaç gün sonra, kalkış saati, araç koltukları]
        $plan = [
            ['ayder-yaylasi-turu', 3, '08:30', [19, 24]],
            ['uzungol-turu', 4, '09:00', [24]],
            ['gunubirlik-batum-turu', 5, '07:30', [31]],
            ['sumela-karaca-magarasi-hamsikoy-turu', 6, '09:00', [24]],
            ['pokut-ve-sal-yaylasi-turu', 7, '08:00', [19]],
            ['ayder-yaylasi-turu', 8, '08:30', [31]],
            ['huser-yaylasi-gun-batimi-turu', 9, '13:00', [19]],
            ['zilkale-ve-palovit-selalesi-turu', 10, '08:30', [19]],
            ['uzungol-turu', 11, '09:00', [24]],
            ['trabzon-sehir-turu', 12, '09:30', [24]],
            ['batum-tiflis-turu', 24, '06:30', [46]],
        ];

        $first = null;

        foreach ($plan as [$slug, $days, $time, $seats]) {
            if (! $tour = Tour::query()->where('slug', $slug)->first()) {
                continue;
            }

            $departure = TourDeparture::query()->create([
                'tour_id' => $tour->getKey(),
                'starts_at' => now()->addDays($days)->setTimeFromTimeString($time),
                'meeting_point' => 'Otelinizden / biniş noktanızdan alınış; saat kayıt sırasında bildirilir',
                'status' => 'open',
                'is_public' => true,
            ]);

            foreach ($seats as $seatCount) {
                if ($vehicle = $fleet->get($seatCount)) {
                    $departure->vehicles()->create(['vehicle_id' => $vehicle->getKey()]);
                }
            }

            $first ??= $departure;
        }

        if ($first) {
            $this->seedGroups($first);
        }
    }

    /** İlk sefere farklı büyüklükte gruplar: dağıtım panosunu denemek için. */
    private function seedGroups(TourDeparture $departure): void
    {
        $families = [
            ['Yılmaz', ['Ahmet', 'Ayşe', 'Elif', 'Can', 'Zeynep'], 'Rize Merkez'],
            ['Demir', ['Mehmet', 'Fatma', 'Ali', 'Selin'], 'Ardeşen'],
            ['Kaya', ['Hasan', 'Emine', 'Burak', 'Deniz'], 'Pazar'],
            ['Şahin', ['Mustafa', 'Hatice', 'Ece', 'Mert'], 'Trabzon Ortahisar'],
            ['Çelik', ['İbrahim', 'Zehra', 'Yusuf', 'Defne', 'Kerem'], 'Çayeli'],
            ['Arslan', ['Osman', 'Meryem', 'Emre'], 'Yomra'],
            ['Doğan', ['Hüseyin', 'Sultan', 'Melis'], 'Fındıklı'],
            ['Aydın', ['Murat', 'Gül'], 'Rize Merkez'],
            ['Öztürk', ['Kemal', 'Nur'], 'Of'],
            ['Koç', ['Serkan', 'Derya', 'Arda', 'İpek'], 'Akçaabat'],
        ];

        $female = ['Ayşe', 'Elif', 'Zeynep', 'Fatma', 'Selin', 'Emine', 'Deniz', 'Hatice', 'Ece', 'Zehra', 'Defne', 'Meryem', 'Sultan', 'Melis', 'Gül', 'Nur', 'Derya', 'İpek'];
        $serial = 0;

        foreach ($families as $gi => [$surname, $names, $pickup]) {
            $group = TourGroup::query()->create([
                'tour_departure_id' => $departure->getKey(),
                'name' => $surname.' ailesi',
                'contact_name' => $names[0].' '.$surname,
                'contact_phone' => '0532 000 '.str_pad((string) (10 + $gi), 2, '0', STR_PAD_LEFT).' '.str_pad((string) (20 + $gi), 2, '0', STR_PAD_LEFT),
                'pickup_point' => $pickup,
                'status' => GroupStatus::Confirmed->value,
                'notes' => 'ÖRNEK KAYIT — silinebilir.',
            ]);

            foreach ($names as $pi => $name) {
                $group->passengers()->create([
                    'first_name' => $name,
                    'last_name' => $surname,
                    'tc_no' => $this->fakeTc(++$serial),
                    'phone' => $pi === 0 ? $group->contact_phone : null,
                    'age' => $pi < 2 ? 38 + $gi : 8 + $pi * 3,
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
