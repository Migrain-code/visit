<?php

namespace Database\Seeders;

use App\Models\Tour;
use App\Models\TourCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Örnek tur kataloğu (database/seeders/data/tours.php).
 *
 * Tekrar çalıştırmak güvenlidir: var olan kayıtlara DOKUNMAZ, yalnız eksikleri ekler.
 * Panelden değiştirdiğiniz fiyat ve programlar korunur.
 */
class TourSeeder extends Seeder
{
    public function run(): void
    {
        $data = require database_path('seeders/data/tours.php');
        $categoryIds = [];

        foreach ($data['categories'] as $i => [$name, $icon, $description, $content, $faqs]) {
            $categoryIds[$name] = TourCategory::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'icon' => $icon,
                    'description' => $description,
                    'content' => $content,
                    'faqs' => $faqs,
                    'meta_title' => $name.' | Program, Tarih ve Fiyatlar',
                    'meta_description' => Str::limit($description.' Güncel tarihler ve kişi başı fiyatlar için inceleyin.', 155, ''),
                    'is_active' => true,
                    'is_featured' => true,
                    'sort_order' => $i + 1,
                ]
            )->getKey();
        }

        foreach ($data['tours'] as $i => $tour) {
            Tour::query()->firstOrCreate(
                ['slug' => Str::slug($tour['title'])],
                [
                    'tour_category_id' => $categoryIds[$tour['category']] ?? null,
                    'title' => $tour['title'],
                    'image' => $tour['image'],
                    'image_alt' => $tour['image_alt'],
                    'short_description' => $tour['short'],
                    'description' => $tour['description'],
                    'duration_days' => $tour['days'],
                    'duration_nights' => $tour['nights'],
                    'price' => $tour['price'],
                    'old_price' => $tour['old_price'],
                    'currency' => $tour['currency'],
                    'price_note' => $tour['price_note'],
                    'departure_point' => 'Rize ve Trabzon\'daki otelinizden ya da biniş noktanızdan',
                    'destinations' => $tour['destinations'],
                    'transport' => $tour['transport'],
                    'accommodation' => $tour['accommodation'],
                    'highlights' => $tour['highlights'],
                    'included' => $tour['included'],
                    'excluded' => $tour['excluded'],
                    'itinerary' => $tour['itinerary'],
                    'faqs' => $tour['faqs'],
                    'meta_title' => Str::limit($tour['title'].' | '.($tour['nights'] > 0 ? $tour['nights'].' Gece '.$tour['days'].' Gün' : 'Günübirlik').' Program', 60, ''),
                    'meta_description' => Str::limit($tour['short'].' Tarihler, fiyat ve rezervasyon.', 155, ''),
                    'is_active' => true,
                    'is_featured' => true,
                    'sort_order' => $i + 1,
                ]
            );
        }
    }
}
