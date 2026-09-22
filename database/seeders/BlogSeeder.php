<?php

namespace Database\Seeders;

use App\Models\BlogCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BlogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['Gezi Rehberi', 'Gezilecek yerler, rotalar ve bir şehirde görülmesi gerekenler.'],
            ['Tur Öncesi Hazırlık', 'Yanınıza ne almalısınız, nelere dikkat etmelisiniz: tura çıkmadan önce bilinmesi gerekenler.'],
            ['Yaylalar', 'Ayder, Pokut, Sal, Huser ve diğer Kaçkar yaylaları: ne zaman gidilir, nasıl çıkılır.'],
            ['Batum ve Gürcistan', 'Sarp\'tan geçiş, kimlikle seyahat, Batum ve Tiflis\'te gezilecek yerler.'],
            ['Karadeniz Mutfağı', 'Muhlama, laz böreği, Hamsiköy sütlacı, Akçaabat köftesi: nerede ne yenir.'],
            ['Kalkış Noktaları', 'Rize ve Trabzon ilçelerinden turlara katılım, otelden alınma ve ulaşım bilgileri.'],
        ];

        foreach ($categories as $i => [$name, $description]) {
            BlogCategory::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $description,
                    'is_active' => true,
                    'auto_generate' => true,
                    'sort_order' => $i + 1,
                ]
            );
        }
    }
}
