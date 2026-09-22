<?php

namespace App\Services\Discovery;

use App\Models\Blog;
use App\Models\District;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Province;
use App\Models\Tour;
use App\Models\TourCategory;
use App\Models\TourDeparture;
use App\Support\AutomationLog;
use Illuminate\Support\Str;

/**
 * llms.txt / llms-full.txt üreticisi (spec §3.9).
 *
 * İçeriğin TAMAMI veritabanından gelir. Hiçbir tur, tarih, fiyat veya bölge uydurulmaz
 * (spec §10.1). Dosyalar cron'da üretilir, istek anında değil (spec §10.7).
 */
class LlmsTxtGenerator
{
    /** @return array{index_bytes: int, full_bytes: int} */
    public function generate(): array
    {
        $index = $this->buildIndex();
        $full = $this->buildFull();

        file_put_contents(public_path('llms.txt'), $index);
        file_put_contents(public_path('llms-full.txt'), $full);

        AutomationLog::summary('discovery.llms', [
            'index_bytes' => strlen($index),
            'full_bytes' => strlen($full),
        ], changed: true);

        return ['index_bytes' => strlen($index), 'full_bytes' => strlen($full)];
    }

    private function buildIndex(): string
    {
        $name = site_name();
        $lines = [];

        $lines[] = '# '.$name;
        $lines[] = '';
        $lines[] = '> '.$this->oneLiner();
        $lines[] = '';
        $lines[] = 'Bu dosya yapay zeka ajanları içindir. Tüm bilgiler sitenin veritabanından üretilir.';
        $lines[] = 'Son güncelleme: '.now()->toDateString();
        $lines[] = '';

        if ($phone = site_phone()) {
            $lines[] = '## İletişim';
            $lines[] = '';
            $lines[] = '- Telefon: '.$phone;

            if ($wa = whatsapp_number()) {
                $lines[] = '- WhatsApp: +'.$wa;
            }

            if ($email = setting('email')) {
                $lines[] = '- E-posta: '.$email;
            }

            if ($hours = setting('working_hours')) {
                $lines[] = '- Çalışma saatleri: '.$hours;
            }

            $lines[] = '';
        }

        if ($license = setting('tursab_no')) {
            $lines[] = '- TÜRSAB belge no: '.$license;
            $lines[] = '';
        }

        $tours = Tour::query()->where('is_active', true)->orderBy('sort_order')->with('category:id,name')->get();

        if ($tours->isNotEmpty()) {
            $lines[] = '## Turlar';
            $lines[] = '';

            foreach ($tours as $tour) {
                $facts = collect([$tour->duration_label, $tour->price_label ? 'kişi başı '.$tour->price_label : null])->filter()->implode(', ');
                $lines[] = '- ['.$tour->title.']('.url('/'.$tour->slug).')'.($facts ? ' ('.$facts.')' : '').': '.$this->clean($tour->short_description);
            }

            $lines[] = '';
        }

        $categories = TourCategory::query()->where('is_active', true)->orderBy('sort_order')->get();

        if ($categories->isNotEmpty()) {
            $lines[] = '## Tur kategorileri';
            $lines[] = '';

            foreach ($categories as $category) {
                $lines[] = '- ['.$category->name.']('.url($category->path()).'): '.$this->clean($category->description);
            }

            $lines[] = '';
        }

        // Yalnız gerçekten satışta olan seferler: tarih ve fiyat uydurulmaz.
        $departures = TourDeparture::query()->bookable()
            ->whereHas('tour', fn ($q) => $q->where('is_active', true))
            ->with('tour')->orderBy('starts_at')->limit(40)->get();

        if ($departures->isNotEmpty()) {
            $lines[] = '## Yaklaşan tur tarihleri';
            $lines[] = '';

            foreach ($departures as $departure) {
                $lines[] = '- '.$departure->date_range_label.': ['.$departure->tour->title.']('.url('/'.$departure->tour->slug).')'
                    .($departure->price_label ? ' — kişi başı '.$departure->price_label : '');
            }

            $lines[] = '';
            $lines[] = 'Güncel takvim: '.url('/tur-takvimi');
            $lines[] = '';
        }

        $provinces = Province::query()->where('is_active', true)->orderBy('sort_order')
            ->with(['districts' => fn ($q) => $q->where('is_active', true)])->get();

        if ($provinces->isNotEmpty()) {
            $lines[] = '## Kalkış noktaları (turlara katılabileceğiniz il ve ilçeler)';
            $lines[] = '';

            foreach ($provinces as $province) {
                $lines[] = '- ['.$province->name.']('.url('/'.$province->slug).'): '.$this->clean($province->description);

                foreach ($province->districts as $district) {
                    $lines[] = '  - ['.$district->name.']('.url('/'.$province->slug.'/'.$district->slug).'): '.$this->clean($district->description);
                }
            }

            $lines[] = '';
        }

        if (class_exists(Blog::class)) {
            $posts = Blog::query()->where('status', 1)->orderByDesc('publish_at')->limit(50)->get();

            if ($posts->isNotEmpty()) {
                $lines[] = '## Blog';
                $lines[] = '';

                foreach ($posts as $post) {
                    $lines[] = '- ['.$post->title.']('.url('/blog/'.$post->slug).'): '.$this->clean($post->meta_description);
                }

                $lines[] = '';
            }
        }

        $pages = Page::query()->where('is_active', true)->orderBy('sort_order')->get();

        if ($pages->isNotEmpty()) {
            $lines[] = '## Diğer sayfalar';
            $lines[] = '';

            foreach ($pages as $page) {
                $lines[] = '- ['.$page->title.']('.url('/'.$page->slug).')';
            }

            $lines[] = '';
        }

        $lines[] = '## Optional';
        $lines[] = '';
        $lines[] = '- [Tam metin sürüm]('.url('/llms-full.txt').')';
        $lines[] = '- [Site haritası]('.url('/sitemap.xml').')';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function buildFull(): string
    {
        $lines = ['# '.site_name().' — tam metin', '', '> '.$this->oneLiner(), ''];

        foreach (Tour::query()->where('is_active', true)->orderBy('sort_order')->get() as $tour) {
            $lines[] = '## '.$tour->title;
            $lines[] = '';
            $lines[] = 'URL: '.url('/'.$tour->slug);
            $lines[] = 'Süre: '.$tour->duration_label;

            if ($tour->price_label) {
                $lines[] = 'Kişi başı fiyat: '.$tour->price_label.($tour->price_note ? ' ('.$this->clean($tour->price_note).')' : '');
            }

            foreach (['Gezilecek yerler' => $tour->destinations, 'Kalkış' => $tour->departure_point, 'Ulaşım' => $tour->transport, 'Konaklama' => $tour->accommodation] as $label => $value) {
                if (filled($value)) {
                    $lines[] = $label.': '.$this->clean($value);
                }
            }

            $lines[] = '';
            $lines[] = $this->clean($tour->description, 4000);
            $lines[] = '';

            foreach ((array) $tour->itinerary as $day) {
                if (filled($day['title'] ?? null)) {
                    $lines[] = '- '.$this->clean($day['title']).': '.$this->clean($day['description'] ?? '', 600);
                }
            }

            $lines[] = '';

            if ($included = (array) $tour->included) {
                $lines[] = 'Fiyata dahil: '.implode('; ', array_map(fn ($i) => $this->clean($i), $included));
            }

            if ($excluded = (array) $tour->excluded) {
                $lines[] = 'Fiyata dahil değil: '.implode('; ', array_map(fn ($i) => $this->clean($i), $excluded));
            }

            $lines[] = '';

            foreach ((array) $tour->faqs as $faq) {
                if (filled($faq['question'] ?? null)) {
                    $lines[] = '**'.$faq['question'].'** '.$this->clean($faq['answer'] ?? '');
                }
            }

            $lines[] = '';
        }

        foreach (TourCategory::query()->where('is_active', true)->orderBy('sort_order')->get() as $category) {
            $lines[] = '## '.$category->name;
            $lines[] = '';
            $lines[] = 'URL: '.url($category->path());
            $lines[] = '';
            $lines[] = $this->clean($category->content ?: $category->description, 3000);
            $lines[] = '';

            foreach ((array) $category->faqs as $faq) {
                if (filled($faq['question'] ?? null)) {
                    $lines[] = '**'.$faq['question'].'** '.$this->clean($faq['answer'] ?? '');
                }
            }

            $lines[] = '';
        }

        foreach (District::query()->where('is_active', true)->with('province')->get() as $district) {
            if (! $district->province?->is_active) {
                continue;
            }

            $lines[] = '## '.$district->name.' ('.$district->province->name.')';
            $lines[] = '';
            $lines[] = 'URL: '.url('/'.$district->province->slug.'/'.$district->slug);
            $lines[] = '';
            $lines[] = $this->clean($district->content, 3000);

            if ($points = (array) $district->pickup_points) {
                $lines[] = '';
                $lines[] = 'Biniş noktaları: '.implode(', ', $points);
            }

            $lines[] = '';
        }

        $faqs = Faq::query()->where('is_active', true)->orderBy('sort_order')->get();

        if ($faqs->isNotEmpty()) {
            $lines[] = '## Sık sorulan sorular';
            $lines[] = '';

            foreach ($faqs as $faq) {
                $lines[] = '**'.$faq->question.'**';
                $lines[] = '';
                $lines[] = $this->clean($faq->answer);
                $lines[] = '';
            }
        }

        return implode("\n", $lines);
    }

    private function oneLiner(): string
    {
        return $this->clean(setting('meta_description') ?: setting('site_tagline'), 300);
    }

    private function clean(?string $html, int $limit = 200): string
    {
        $text = trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return Str::limit($text, $limit, '');
    }
}
