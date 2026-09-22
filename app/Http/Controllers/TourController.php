<?php

namespace App\Http\Controllers;

use App\Models\Province;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Models\TourDeparture;
use App\Support\SchemaOrg;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TourController extends Controller
{
    /** Süre filtresi: adres çubuğundaki değer => [etiket, sorgu]. */
    private const DURATIONS = [
        'gunubirlik' => 'Günübirlik',
        'kisa' => '1-2 gece',
        'uzun' => '3 gece ve üzeri',
    ];

    public function index(Request $request): View
    {
        $duration = array_key_exists((string) $request->query('sure'), self::DURATIONS) ? (string) $request->query('sure') : null;

        return view('tours.index', [
            'tours' => $this->listing()->tap(fn (Builder $q) => $this->applyDuration($q, $duration))->get(),
            'categories' => $this->categories(),
            'category' => null,
            'durations' => self::DURATIONS,
            'duration' => $duration,
            'metaTitle' => 'Turlar | Günübirlik Yayla, Göl, Kültür ve Batum Turları',
            'metaDescription' => 'Ayder, Uzungöl, Sümela, Pokut, Huser, Zilkale ve Batum: Rize ve Trabzon çıkışlı günübirlik turların programları, tarihleri ve kişi başı fiyatları.',
            'canonical' => route('tours.index'),
            // Filtreli liste aynı turların alt kümesidir; dizine ana liste girer.
            'robots' => $duration ? 'noindex, follow' : null,
            'jsonLd' => [SchemaOrg::breadcrumbs($this->crumbs())],
        ]);
    }

    public function category(Request $request, TourCategory $category): View
    {
        // Yayından kaldırılan kategori gizlenir. Rota bağlaması yayın durumuna bakmaz.
        abort_unless($category->is_active, 404);

        $duration = array_key_exists((string) $request->query('sure'), self::DURATIONS) ? (string) $request->query('sure') : null;
        $breadcrumbs = $this->crumbs([['name' => $category->name, 'url' => $category->url]]);

        $tours = $this->listing()->where('tour_category_id', $category->getKey())
            ->tap(fn (Builder $q) => $this->applyDuration($q, $duration))->get();

        $jsonLd = [SchemaOrg::breadcrumbs($breadcrumbs)];

        if ($list = SchemaOrg::tourList($tours, $category->name)) {
            $jsonLd[] = $list;
        }

        if ($faq = SchemaOrg::faq($category->faqs ?? [])) {
            $jsonLd[] = $faq;
        }

        return view('tours.index', [
            'tours' => $tours,
            'categories' => $this->categories(),
            'category' => $category,
            'durations' => self::DURATIONS,
            'duration' => $duration,
            'breadcrumbs' => $breadcrumbs,
            'metaTitle' => $category->meta_title ?: $category->name.' | Program ve Fiyatlar',
            'metaDescription' => $category->meta_description ?: $category->description,
            'canonical' => $category->url,
            'robots' => $duration ? 'noindex, follow' : null,
            'ogImage' => $category->image_url,
            'jsonLd' => $jsonLd,
        ]);
    }

    public function show(Tour $tour): View
    {
        // Yayından kaldırılan kategori yüklenmez: sayfası 404 döndüğü için bağlantı verilmemeli.
        $tour->load(['category' => fn ($q) => $q->where('is_active', true)->select(['id', 'name', 'slug'])]);

        // Seferin fiyat etiketi turun fiyatına ve para birimine bakar. Tur zaten elimizde:
        // ilişki elle bağlanmazsa her sefer turu ayrı bir sorguyla yeniden yükler (N+1).
        $departures = $tour->upcomingDepartures()->withSeatStats()->get()
            ->each(fn (TourDeparture $departure) => $departure->setRelation('tour', $tour));

        $breadcrumbs = $this->crumbs(array_filter([
            $tour->category ? ['name' => $tour->category->name, 'url' => $tour->category->url] : null,
            ['name' => $tour->title, 'url' => $tour->url],
        ]));

        $jsonLd = [
            SchemaOrg::breadcrumbs($breadcrumbs),
            SchemaOrg::tour($tour, $departures),
        ];

        if ($faq = SchemaOrg::faq($tour->faqs ?? [])) {
            $jsonLd[] = $faq;
        }

        return view('tours.show', [
            'tour' => $tour,
            'departures' => $departures,
            'relatedTours' => $this->listing()->whereKeyNot($tour->getKey())
                // Önce aynı kategorideki turlar; sonra sıradaki diğerleri.
                ->when($tour->tour_category_id, fn (Builder $q, $id) => $q->reorder()
                    ->orderByRaw('case when tour_category_id = ? then 0 else 1 end', [$id])
                    ->orderBy('sort_order')->orderBy('title'))
                ->take(3)->get(),
            'provinces' => Province::query()->active()->ordered()->with('activeDistricts')->get(),
            'breadcrumbs' => $breadcrumbs,
            'metaTitle' => $tour->meta_title ?: $tour->title.' | '.$tour->duration_label.' Tur Programı',
            'metaDescription' => $tour->meta_description ?: $tour->short_description,
            'canonical' => $tour->url,
            'ogImage' => $tour->image_url,
            'jsonLd' => $jsonLd,
            'whatsappUrl' => whatsapp_url(null, $tour->title),
            'reservationUrl' => route('reservation.create', ['tur' => $tour->slug]),
        ]);
    }

    /** Tur takvimi: satışa açık tüm seferler, tarihe göre. */
    public function calendar(): View
    {
        $departures = TourDeparture::query()
            ->bookable()
            ->whereHas('tour', fn (Builder $q) => $q->where('is_active', true))
            ->withSeatStats()
            ->with('tour.category:id,name,slug')
            ->orderBy('starts_at')
            ->get();

        $breadcrumbs = [
            ['name' => 'Ana Sayfa', 'url' => url('/')],
            ['name' => 'Tur Takvimi', 'url' => route('tours.calendar')],
        ];

        return view('tours.calendar', [
            'months' => $departures->groupBy(fn (TourDeparture $d) => $d->starts_at->format('Y-m')),
            'breadcrumbs' => $breadcrumbs,
            'metaTitle' => 'Tur Takvimi | Yaklaşan Tur Tarihleri ve Fiyatlar',
            'metaDescription' => 'Önümüzdeki aylarda düzenlenecek turların kalkış tarihleri, kişi başı fiyatları ve kalan kontenjan bilgisi.',
            'canonical' => route('tours.calendar'),
            'jsonLd' => [SchemaOrg::breadcrumbs($breadcrumbs)],
        ]);
    }

    /** Liste sorgusu: kartta gösterilecek en yakın sefer tarihiyle birlikte. */
    private function listing(): Builder
    {
        return Tour::query()->active()->ordered()->withPublicCategory()
            ->withMin(['departures as next_departure' => fn ($q) => $q->bookable()], 'starts_at');
    }

    private function applyDuration(Builder $query, ?string $duration): void
    {
        match ($duration) {
            'gunubirlik' => $query->where('duration_nights', 0)->where('duration_days', '<=', 1),
            'kisa' => $query->whereBetween('duration_nights', [1, 2]),
            'uzun' => $query->where('duration_nights', '>=', 3),
            default => null,
        };
    }

    private function categories()
    {
        return TourCategory::query()->active()->ordered()
            ->withCount(['tours' => fn ($q) => $q->where('is_active', true)])->get();
    }

    private function crumbs(array $extra = []): array
    {
        return array_merge([
            ['name' => 'Ana Sayfa', 'url' => url('/')],
            ['name' => 'Turlar', 'url' => route('tours.index')],
        ], array_values($extra));
    }
}
