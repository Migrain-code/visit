<?php

namespace Database\Seeders;

use App\Models\GalleryCategory;
use App\Models\GalleryItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GallerySeeder extends Seeder
{
    public function run(): void
    {
        $categories = ['Yaylalar', 'Fırtına Vadisi', 'Trabzon', 'Batum', 'Karadeniz Yaşamı'];

        $ids = [];
        foreach ($categories as $i => $name) {
            $ids[$name] = GalleryCategory::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $i + 1]
            )->id;
        }

        if (GalleryItem::query()->exists()) {
            return;
        }

        // Örnek görseller (CC0 / kamu malı; kaynaklar database/seeders/images/CREDITS.md içinde).
        // Kendi tur fotoğraflarınızı yönetim panelinden yükleyerek bunların yerine koyun.
        $items = [
            ['Yaylalar', 'yayla-vadisi', 'Yayla köyü ve çiçekli çayırlar'],
            ['Fırtına Vadisi', 'kemer-kopru', 'Fırtına Deresi üzerinde taş kemer köprü'],
            ['Fırtına Vadisi', 'zilkale', 'Vadiye hâkim tepede Zilkale'],
            ['Trabzon', 'uzungol', 'Sisli bir günde Uzungöl'],
            ['Trabzon', 'sumela', 'Sümela Manastırı\'nın avlusu'],
            ['Karadeniz Yaşamı', 'cay-yapragi', 'Taze çay yaprakları'],
            ['Yaylalar', 'yayla-evi', 'Ahşap yayla evi'],
            ['Fırtına Vadisi', 'rafting', 'Fırtına Deresi\'nde rafting'],
            ['Trabzon', 'trabzon-ayasofya', 'Trabzon Ayasofyası'],
            ['Batum', 'batum', 'Batum\'da tarihî yazlık tiyatro'],
            ['Karadeniz Yaşamı', 'yayla-kecileri', 'Yaylada keçiler'],
            ['Karadeniz Yaşamı', 'ahsap-pencere', 'Yayla evinin ahşap penceresi'],
        ];

        foreach ($items as $i => [$category, $file, $title]) {
            GalleryItem::query()->create([
                'gallery_category_id' => $ids[$category],
                'title' => $title,
                'image' => 'gallery/'.$file.'.webp',
                'alt_text' => $title,
                'is_active' => true,
                'show_on_home' => $i < 8,
                'sort_order' => $i + 1,
            ]);
        }
    }
}
