<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\District;
use App\Models\Page;
use App\Models\Province;
use App\Models\Tour;
use App\Models\TourCategory;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $xml = Cache::remember('sitemap.xml', now()->addHour(), function () {
            $urls = [
                ['loc' => url('/'), 'priority' => '1.0', 'changefreq' => 'weekly'],
                ['loc' => route('tours.index'), 'priority' => '0.9', 'changefreq' => 'weekly'],
                ['loc' => route('tours.calendar'), 'priority' => '0.8', 'changefreq' => 'daily'],
                ['loc' => route('blog.index'), 'priority' => '0.8', 'changefreq' => 'daily'],
                ['loc' => route('regions.index'), 'priority' => '0.8', 'changefreq' => 'weekly'],
                ['loc' => route('gallery.index'), 'priority' => '0.6', 'changefreq' => 'weekly'],
                ['loc' => route('about'), 'priority' => '0.5', 'changefreq' => 'monthly'],
                ['loc' => route('faq'), 'priority' => '0.5', 'changefreq' => 'monthly'],
                ['loc' => route('contact'), 'priority' => '0.7', 'changefreq' => 'monthly'],
                ['loc' => route('reservation.create'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ];

            foreach (Tour::query()->active()->ordered()->get() as $tour) {
                $urls[] = ['loc' => $tour->url, 'lastmod' => $tour->updated_at, 'priority' => '0.9', 'changefreq' => 'weekly'];
            }

            foreach (TourCategory::query()->active()->ordered()->get() as $category) {
                $urls[] = ['loc' => $category->url, 'lastmod' => $category->updated_at, 'priority' => '0.8', 'changefreq' => 'weekly'];
            }

            foreach (Province::query()->active()->ordered()->get() as $province) {
                $urls[] = ['loc' => $province->url, 'lastmod' => $province->updated_at, 'priority' => '0.8', 'changefreq' => 'monthly'];
            }

            foreach (District::query()->active()->with('province')->get() as $district) {
                if ($district->province?->is_active) {
                    $urls[] = ['loc' => $district->url, 'lastmod' => $district->updated_at, 'priority' => '0.7', 'changefreq' => 'monthly'];
                }
            }

            // Blog yazıları: yayın tarihi gelmiş olanlar.
            foreach (Blog::query()->published()->orderByDesc('publish_at')->get() as $post) {
                $urls[] = ['loc' => $post->url, 'lastmod' => $post->updated_at, 'priority' => '0.6', 'changefreq' => 'monthly'];
            }

            foreach (Page::query()->active()->get() as $page) {
                $urls[] = ['loc' => $page->url, 'lastmod' => $page->updated_at, 'priority' => '0.3', 'changefreq' => 'yearly'];
            }

            return view('sitemap', ['urls' => $urls])->render();
        });

        return response($xml, 200)->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
