<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;

/**
 * Hazır araç listesi. Koltuk sayıları YOLCU koltuğudur (şoför hariç).
 * Panelden (Operasyon → Araçlar) kendi araçlarınızla değiştirin.
 */
class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        if (Vehicle::query()->exists()) {
            return;
        }

        $fleet = [
            ['19 Koltuklu Minibüs', 19],
            ['24 Koltuklu Midibüs', 24],
            ['31 Koltuklu Midibüs', 31],
            ['46 Koltuklu Otobüs', 46],
            ['50 Koltuklu Otobüs', 50],
        ];

        foreach ($fleet as $i => [$name, $seats]) {
            Vehicle::query()->create([
                'name' => $name,
                'seat_count' => $seats,
                'is_active' => true,
                'sort_order' => $i + 1,
            ]);
        }
    }
}
