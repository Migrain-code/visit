<?php

namespace Database\Seeders;

use App\Models\TourDeparture;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Örnek turlar (database/seeders/data/tours.php).
 *
 * Tekrar çalıştırmak güvenlidir: sistemde tek bir tur bile varsa DOKUNMAZ.
 * Tarihler önümüzdeki hafta sonlarına göre hesaplanır (Cumartesi, Pazar, Cumartesi...).
 */
class TourSeeder extends Seeder
{
    public function run(): void
    {
        if (TourDeparture::query()->exists()) {
            return;
        }

        foreach (require database_path('seeders/data/tours.php') as $i => $tour) {
            TourDeparture::query()->create([
                'title' => $tour['title'],
                'badge' => $tour['badge'],
                'image' => $tour['image'],
                'image_alt' => $tour['image_alt'],
                'short_description' => $tour['short'],
                'description' => $tour['description'],
                'starts_at' => static::weekendDay($tour['weekend'])->setTimeFromTimeString($tour['time']),
                'meeting_point' => $tour['meeting_point'],
                'price' => $tour['price'],
                'status' => 'open',
                'is_public' => true,
                'sort_order' => $i + 1,
            ]);
        }
    }

    /** n. hafta sonu günü: 0 → gelecek Cumartesi, 1 → Pazar, 2 → sonraki Cumartesi... */
    public static function weekendDay(int $index): Carbon
    {
        $saturday = now()->next(Carbon::SATURDAY)->startOfDay();

        return $saturday->addWeeks(intdiv($index, 2))->addDays($index % 2);
    }
}
