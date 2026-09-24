<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Tek sayfa site: üst görsel, tur kartları, öne çıkanlar, Instagram bandı.
 */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home', [
            'tours' => app('site.tours'),
            'features' => $this->features(),
            'metaTitle' => setting('meta_title', site_name().' | '.setting('site_tagline', 'Keşfet · Tanış · Yaşa')),
            'metaDescription' => setting('meta_description'),
            'canonical' => url('/'),
            'ogImage' => media_url(setting('hero_image')),
        ]);
    }

    /**
     * Tur kartlarının altındaki dört madde. Ayarlardan değiştirilebilir; boşsa varsayılanlar.
     *
     * @return array<int, array{icon: string, title: string, text: string}>
     */
    private function features(): array
    {
        $defaults = [
            ['fa-solid fa-people-group', 'Profesyonel Rehber', 'Uzman kadro'],
            ['fa-solid fa-bus', 'Konforlu Araçlar', 'Rahat ve güvenli'],
            ['fa-solid fa-graduation-cap', 'Öğrenci Dostu Fiyat', 'En iyi deneyim'],
            ['fa-solid fa-shield-halved', 'Güvenli Organizasyon', 'Daima yanındayız'],
        ];

        return collect($defaults)->map(fn (array $item, int $i) => [
            'icon' => $item[0],
            'title' => (string) setting('feature_'.($i + 1).'_title', $item[1]),
            'text' => (string) setting('feature_'.($i + 1).'_text', $item[2]),
        ])->all();
    }
}
