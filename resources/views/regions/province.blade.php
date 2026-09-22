@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => $province->name.' Çıkışlı Günübirlik Turlar',
        'subtitle' => $province->description,
        'breadcrumbs' => $breadcrumbs,
        'badges' => [['fa-solid fa-location-dot', $province->activeDistricts->count().' ilçeden katılım']],
    ])

    <section class="section">
        <div class="container">
            <div class="row g-4 g-lg-5">
                <div class="col-lg-8">
                    <div class="content-prose mb-5">{!! internal_links($province->content, 'province', '/'.$province->slug) !!}</div>

                    <h2 class="h3 mb-3">{{ $province->name }} ilinde yolcu aldığımız ilçeler</h2>
                    <div class="chips mb-5">
                        @foreach ($province->activeDistricts as $district)
                            <a class="chip" href="{{ url('/'.$province->slug.'/'.$district->slug) }}"><i class="fa-solid fa-location-dot"></i>{{ $district->name }}</a>
                        @endforeach
                    </div>

                    @if (! empty($province->faqs))
                        <h2 class="h3 mb-3">{{ $province->name }} için sık sorulan sorular</h2>
                        @include('partials.faq-accordion', ['faqs' => $province->faqs, 'accordionId' => 'provinceFaq', 'openFirst' => true])
                    @endif
                </div>
                <aside class="col-lg-4">
                    <div class="sidebar">
                        @include('partials.sidebar-cta', ['ctaTitle' => $province->name.'\'dan tura katılın', 'whatsappUrl' => $whatsappUrl])
                        @if ($otherProvinces->isNotEmpty())
                            <div class="sidebar-widget">
                                <h3>Diğer kalkış illeri</h3>
                                <ul class="side-links">
                                    @foreach ($otherProvinces as $other)
                                        <li><a href="{{ $other->url }}">{{ $other->name }} çıkışlı turlar <small>{{ $other->activeDistricts->count() }} ilçe</small></a></li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </aside>
            </div>
        </div>
    </section>

    @if ($tours->isNotEmpty())
        <section class="section bg-sand">
            <div class="container">
                <x-section-title subtitle="Turlar" :title="$province->name.'\'dan Katılabileceğiniz Turlar'" text="Tüm turlarımıza bu ilden katılabilirsiniz." />
                <div class="row g-4">
                    @foreach ($tours as $tour)
                        <div class="col-lg-4 col-md-6 reveal">
                            @include('partials.tour-card', ['tour' => $tour])
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @include('partials.cta-band', ['whatsappUrl' => $whatsappUrl, 'ctaTitle' => $province->name.'\'dan yola çıkmaya hazır mısınız?'])
@endsection
