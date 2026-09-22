<?php

namespace App\Support;

use App\Models\Blog;
use App\Models\Province;
use App\Models\Tour;
use App\Models\TourDeparture;

/**
 * JSON-LD (schema.org) yapılandırılmış veri üreticileri.
 */
class SchemaOrg
{
    public static function travelAgency(): array
    {
        $sameAs = collect([setting('facebook_url'), setting('instagram_url'), setting('youtube_url')])->filter()->values()->all();

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'TravelAgency',
            '@id' => url('/').'#business',
            'name' => site_name(),
            'description' => (string) setting('meta_description'),
            'url' => url('/'),
            'telephone' => phone_digits(site_phone()),
            'image' => media_url(setting('hero_image'), asset('images/placeholder.svg')),
            'priceRange' => '₺₺',
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => (string) setting('address'),
                'addressCountry' => 'TR',
            ],
            'areaServed' => Province::query()->active()->ordered()->pluck('name')->map(fn ($name) => [
                '@type' => 'State',
                'name' => $name,
            ])->values()->all(),
        ];

        if ($email = setting('email')) {
            $data['email'] = $email;
        }

        if ($hours = setting('working_hours')) {
            $data['openingHours'] = $hours;
        }

        if ($sameAs !== []) {
            $data['sameAs'] = $sameAs;
        }

        return $data;
    }

    /**
     * @param  array<int, array{name: string, url: string}>  $items
     */
    public static function breadcrumbs(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn (array $item, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ])->all(),
        ];
    }

    /**
     * @param  iterable<int, array{question: string, answer: string}>  $faqs
     */
    public static function faq(iterable $faqs): ?array
    {
        $entities = collect($faqs)
            ->filter(fn ($faq) => filled($faq['question'] ?? null) && filled($faq['answer'] ?? null))
            ->map(fn ($faq) => [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => strip_tags($faq['answer'])],
            ])->values()->all();

        if ($entities === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }

    /**
     * Blog yazısı için Article şeması.
     *
     * DİKKAT: aggregateRating / reviewCount BASILMAZ. Gerçek, doğrulanabilir veri
     * olmadan puan basmak manuel ceza riskidir (spec §3.9, §10.1).
     */
    public static function article(Blog $post): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Article',
            'headline' => $post->title,
            'description' => $post->meta_description ?: $post->summary,
            'url' => $post->url,
            'inLanguage' => 'tr-TR',
            'datePublished' => optional($post->publish_at ?? $post->created_at)->toAtomString(),
            'dateModified' => optional($post->updated_at)->toAtomString(),
            'publisher' => ['@id' => url('/').'#business'],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $post->url],
        ];

        if (filled($post->image)) {
            $data['image'] = $post->image_url;
        }

        if ($post->category) {
            $data['articleSection'] = $post->category->name;
        }

        return $data;
    }

    /**
     * Tur sayfası: TouristTrip + satışa açık seferlerden Offer listesi.
     *
     * DİKKAT: fiyat ve tarih YALNIZ gerçekten satışta olan seferlerden basılır; dolu
     * sefer "SoldOut" işaretlenir. Uydurma "başlayan fiyat" ya da puan basılmaz.
     *
     * @param  iterable<int, TourDeparture>  $departures
     */
    public static function tour(Tour $tour, iterable $departures = []): array
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'TouristTrip',
            'name' => $tour->title,
            'description' => $tour->meta_description ?: $tour->short_description,
            'url' => $tour->url,
            'image' => $tour->image_url,
            'provider' => ['@id' => url('/').'#business'],
            'touristType' => $tour->category?->name,
        ];

        if ($itinerary = collect($tour->itinerary ?? [])->filter(fn ($day) => filled($day['title'] ?? null))->values()) {
            if ($itinerary->isNotEmpty()) {
                $data['itinerary'] = [
                    '@type' => 'ItemList',
                    'itemListElement' => $itinerary->map(fn ($day, $i) => [
                        '@type' => 'ListItem',
                        'position' => $i + 1,
                        'name' => $day['title'],
                        'description' => (string) ($day['description'] ?? ''),
                    ])->all(),
                ];
            }
        }

        $offers = collect($departures)
            ->filter(fn (TourDeparture $d) => $d->effective_price !== null)
            ->map(fn (TourDeparture $d) => [
                '@type' => 'Offer',
                'name' => $tour->title.' · '.$d->date_range_label,
                'price' => number_format($d->effective_price, 2, '.', ''),
                'priceCurrency' => $tour->currency ?: 'TRY',
                'availability' => 'https://schema.org/'.($d->is_full ? 'SoldOut' : 'InStock'),
                'validFrom' => now()->toAtomString(),
                'availabilityEnds' => $d->starts_at->toAtomString(),
                'url' => $tour->url,
            ])->values();

        if ($offers->isEmpty() && $tour->price !== null) {
            $offers = collect([[
                '@type' => 'Offer',
                'price' => number_format((float) $tour->price, 2, '.', ''),
                'priceCurrency' => $tour->currency ?: 'TRY',
                'url' => $tour->url,
            ]]);
        }

        if ($offers->isNotEmpty()) {
            $data['offers'] = $offers->all();
        }

        return array_filter($data, fn ($value) => $value !== null);
    }

    /**
     * Kategori / liste sayfası için ItemList.
     *
     * @param  iterable<int, Tour>  $tours
     */
    public static function tourList(iterable $tours, string $name): ?array
    {
        $items = collect($tours)->values()->map(fn (Tour $tour, int $i) => [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $tour->title,
            'url' => $tour->url,
        ])->all();

        if ($items === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'name' => $name,
            'itemListElement' => $items,
        ];
    }
}
