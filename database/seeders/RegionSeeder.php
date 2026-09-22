<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\Province;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Kalkış bölgeleri: "{bölge} çıkışlı turlar" sayfaları (database/seeders/data/regions.php).
 *
 * Tekrar çalıştırmak güvenlidir: var olan kayıtlara dokunmaz.
 */
class RegionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (require database_path('seeders/data/regions.php') as $pi => $p) {
            $province = Province::query()->firstOrCreate(
                ['slug' => Str::slug($p['name'])],
                [
                    'name' => $p['name'],
                    'description' => $p['description'],
                    'content' => $p['content'],
                    'faqs' => $p['faqs'],
                    'meta_title' => $p['name'].' Çıkışlı Günübirlik Turlar | Yayla ve Batum',
                    'meta_description' => $p['meta_description'],
                    'sort_order' => $pi + 1,
                ]
            );

            foreach ($p['districts'] as $di => [$name, $description, $location, $joining]) {
                District::query()->firstOrCreate(
                    ['province_id' => $province->id, 'slug' => Str::slug($name)],
                    [
                        'name' => $name,
                        'description' => $description,
                        'content' => $this->content($name, $p['name'], $location, $joining),
                        // Biniş noktaları bilerek boş: gerçek durakları panelden siz girin.
                        'pickup_points' => [],
                        'faqs' => $this->faqs($name, $p['name']),
                        'meta_title' => Str::limit($name.' Çıkışlı Günübirlik Turlar | '.$p['name'], 60, ''),
                        'meta_description' => Str::limit($name.' çıkışlı Ayder, Uzungöl, Sümela, yayla ve Batum turları. Otelinizden alınma, güncel tarihler ve kişi başı fiyatlar.', 155, ''),
                        'sort_order' => $di + 1,
                    ]
                );
            }
        }
    }

    private function content(string $district, string $province, string $location, string $joining): string
    {
        return '<p>'.e($location).'</p>'
            .'<p>'.e($joining).' Otelinizden ya da size en yakın biniş noktasından alınırsınız; alınış saati katılacağınız tura göre rezervasyon sırasında bildirilir.</p>'
            .'<h2>'.e($district).'\'dan tura nasıl katılırım?</h2>'
            .'<p>Aşağıdaki turlardan birini ve tur takviminden size uyan tarihi seçin. Rezervasyon formunda ya da telefonda '
            .e($district).' ('.e($province).') bölgesinden katılacağınızı ve konakladığınız oteli belirtmeniz yeterlidir. Aileniz veya arkadaşlarınızla birlikte kaydolursanız grubunuz aynı araçta yolculuk eder.</p>';
    }

    private function faqs(string $district, string $province): array
    {
        return [
            [
                'question' => $district.' çıkışlı hangi turlara katılabilirim?',
                'answer' => 'Tur takvimimizdeki bütün turlara '.$district.' ('.$province.') bölgesinden katılabilirsiniz. Biniş noktanız turun güzergâhına göre belirlenir.',
            ],
            [
                'question' => $district.' için biniş noktası ve saati ne zaman belli olur?',
                'answer' => 'Biniş noktanız kayıt sırasında bildirilir. Kesin alınış saati, tur gününden bir gün önce telefon ya da WhatsApp ile tekrar hatırlatılır.',
            ],
        ];
    }
}
