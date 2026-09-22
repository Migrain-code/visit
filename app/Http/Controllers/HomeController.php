<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Models\Feature;
use App\Models\GalleryItem;
use App\Models\Province;
use App\Models\Testimonial;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Models\TourDeparture;
use App\Support\SchemaOrg;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $stats = collect(range(1, 4))
            ->map(fn (int $i) => ['value' => setting("stat_{$i}_value"), 'label' => setting("stat_{$i}_label")])
            ->filter(fn (array $s) => filled($s['value']) && filled($s['label']))
            ->values();

        $faqs = Faq::query()->active()->where('show_on_home', true)->ordered()->take(6)->get();

        $jsonLd = [SchemaOrg::travelAgency()];

        if ($faqSchema = SchemaOrg::faq($faqs->map(fn ($f) => ['question' => $f->question, 'answer' => $f->answer]))) {
            $jsonLd[] = $faqSchema;
        }

        return view('home', [
            'tours' => Tour::query()->active()->where('is_featured', true)->ordered()->withPublicCategory()
                ->withMin(['departures as next_departure' => fn ($q) => $q->bookable()], 'starts_at')
                ->take(6)->get(),
            'categories' => TourCategory::query()->active()->where('is_featured', true)->ordered()
                ->withCount(['tours' => fn ($q) => $q->where('is_active', true)])->get(),
            'departures' => TourDeparture::query()->bookable()->whereHas('tour', fn ($q) => $q->where('is_active', true))
                ->withSeatStats()->with('tour')->orderBy('starts_at')->take(6)->get(),
            'trustItems' => Feature::query()->active()->ofType(Feature::TYPE_TRUST)->ordered()->get(),
            'whyUs' => Feature::query()->active()->ofType(Feature::TYPE_WHY_US)->ordered()->get(),
            'processSteps' => Feature::query()->active()->ofType(Feature::TYPE_PROCESS)->ordered()->get(),
            'galleryItems' => GalleryItem::query()->active()->where('show_on_home', true)->with('category')->ordered()->take(8)->get(),
            'testimonials' => Testimonial::query()->active()->ordered()->take(9)->get(),
            'provinces' => Province::query()->active()->ordered()->with('activeDistricts')->get(),
            'faqs' => $faqs,
            'stats' => $stats,
            'metaTitle' => setting('meta_title', site_name().' | '.setting('site_tagline')),
            'metaDescription' => setting('meta_description'),
            'canonical' => url('/'),
            'ogImage' => media_url(setting('hero_image')),
            'jsonLd' => $jsonLd,
        ]);
    }
}
