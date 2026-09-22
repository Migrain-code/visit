<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\Page;
use App\Models\Province;
use App\Models\Tour;
use App\Support\SchemaOrg;
use Illuminate\View\View;

class PageController extends Controller
{
    /**
     * Kök dizindeki slug'ları çözer: önce tur, sonra il, sonra statik sayfa.
     */
    public function show(string $slug): View
    {
        if ($tour = Tour::query()->active()->where('slug', $slug)->first()) {
            return app(TourController::class)->show($tour);
        }

        if ($province = Province::query()->active()->where('slug', $slug)->first()) {
            return app(RegionController::class)->province($province);
        }

        $page = Page::query()->active()->where('slug', $slug)->firstOrFail();

        return view('pages.show', [
            'page' => $page,
            'metaTitle' => $page->meta_title ?: $page->title,
            'metaDescription' => $page->meta_description,
            'canonical' => $page->url,
            'jsonLd' => [SchemaOrg::breadcrumbs([
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => $page->title, 'url' => $page->url],
            ])],
        ]);
    }

    public function about(): View
    {
        return view('about', [
            'tours' => Tour::query()->active()->where('is_featured', true)->ordered()->limit(6)->get(),
            'metaTitle' => 'Hakkımızda | '.site_name(),
            'metaDescription' => site_name().' hakkında: nasıl tur düzenliyoruz, hangi bölgelerden kalkıyoruz ve misafirlerimiz neden bizimle yola çıkıyor.',
            'canonical' => route('about'),
            'jsonLd' => [SchemaOrg::breadcrumbs([
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Hakkımızda', 'url' => route('about')],
            ])],
        ]);
    }

    public function faq(): View
    {
        $faqs = Faq::query()->active()->ordered()->get();

        $jsonLd = [SchemaOrg::breadcrumbs([
            ['name' => 'Ana Sayfa', 'url' => url('/')],
            ['name' => 'Sık Sorulan Sorular', 'url' => route('faq')],
        ])];

        if ($schema = SchemaOrg::faq($faqs->map(fn ($f) => ['question' => $f->question, 'answer' => $f->answer]))) {
            $jsonLd[] = $schema;
        }

        return view('faq', [
            'faqs' => $faqs,
            'metaTitle' => 'Sık Sorulan Sorular | Turlar ve Rezervasyon',
            'metaDescription' => 'Rezervasyon, ödeme, iptal koşulları, kalkış noktaları ve tur programları hakkında sık sorulan sorular.',
            'canonical' => route('faq'),
            'jsonLd' => $jsonLd,
        ]);
    }
}
