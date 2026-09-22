<?php

namespace App\Http\Controllers;

use App\Models\District;
use App\Models\Province;
use App\Models\Tour;
use App\Support\SchemaOrg;
use Illuminate\View\View;

class RegionController extends Controller
{
    public function index(): View
    {
        return view('regions.index', [
            'provinces' => Province::query()->active()->ordered()->with('activeDistricts')->get(),
            'metaTitle' => 'Kalkış Noktaları | Turlarımıza Katılabileceğiniz İl ve İlçeler',
            'metaDescription' => 'Rize ve Trabzon\'da turlarımıza hangi ilçelerden katılabileceğinizi görün. Otelden alınma ve biniş noktası bilgisini öğrenin.',
            'canonical' => route('regions.index'),
            'jsonLd' => [SchemaOrg::breadcrumbs([
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Kalkış Noktaları', 'url' => route('regions.index')],
            ])],
        ]);
    }

    public function province(Province $province): View
    {
        $province->load('activeDistricts');

        $breadcrumbs = [
            ['name' => 'Ana Sayfa', 'url' => url('/')],
            ['name' => 'Kalkış Noktaları', 'url' => route('regions.index')],
            ['name' => $province->name, 'url' => $province->url],
        ];

        $jsonLd = [SchemaOrg::breadcrumbs($breadcrumbs)];

        if ($faq = SchemaOrg::faq($province->faqs ?? [])) {
            $jsonLd[] = $faq;
        }

        return view('regions.province', [
            'province' => $province,
            'tours' => $this->tours(),
            'otherProvinces' => Province::query()->active()->ordered()->whereKeyNot($province->getKey())->with('activeDistricts')->get(),
            'breadcrumbs' => $breadcrumbs,
            'metaTitle' => $province->meta_title ?: $province->name.' Çıkışlı Günübirlik Turlar | Program ve Fiyatlar',
            'metaDescription' => $province->meta_description ?: $province->description,
            'canonical' => $province->url,
            'jsonLd' => $jsonLd,
            'whatsappUrl' => whatsapp_url($province->name),
        ]);
    }

    public function district(Province $province, string $district): View
    {
        abort_unless($province->is_active, 404);

        /** @var District $district */
        $district = $province->activeDistricts()->where('slug', $district)->firstOrFail();
        $district->setRelation('province', $province);

        $breadcrumbs = [
            ['name' => 'Ana Sayfa', 'url' => url('/')],
            ['name' => 'Kalkış Noktaları', 'url' => route('regions.index')],
            ['name' => $province->name, 'url' => $province->url],
            ['name' => $district->name, 'url' => $district->url],
        ];

        $jsonLd = [SchemaOrg::breadcrumbs($breadcrumbs)];

        if ($faq = SchemaOrg::faq($district->faqs ?? [])) {
            $jsonLd[] = $faq;
        }

        return view('regions.district', [
            'province' => $province,
            'district' => $district,
            'tours' => $this->tours(),
            'otherDistricts' => $province->activeDistricts()->whereKeyNot($district->getKey())->get(),
            'breadcrumbs' => $breadcrumbs,
            'metaTitle' => $district->meta_title ?: $district->name.' Çıkışlı Günübirlik Turlar | '.$province->name,
            'metaDescription' => $district->meta_description ?: $district->description,
            'canonical' => $district->url,
            'jsonLd' => $jsonLd,
            'whatsappUrl' => whatsapp_url($district->name.', '.$province->name),
        ]);
    }

    /** Bölge sayfalarında listelenen turlar: en yakın sefer tarihiyle birlikte. */
    private function tours()
    {
        return Tour::query()->active()->ordered()->withPublicCategory()
            ->withMin(['departures as next_departure' => fn ($q) => $q->bookable()], 'starts_at')
            ->get();
    }
}
