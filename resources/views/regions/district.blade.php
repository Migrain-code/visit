@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => $district->name.' Çıkışlı Günübirlik Turlar',
        'subtitle' => $district->description,
        'breadcrumbs' => $breadcrumbs,
        'badges' => [['fa-solid fa-map', $province->name]],
    ])

    <section class="section">
        <div class="container">
            <div class="row g-4 g-lg-5">
                <div class="col-lg-8">
                    <div class="content-prose mb-5">{!! internal_links($district->content, 'district', '/'.$province->slug.'/'.$district->slug) !!}</div>

                    @if (! empty($district->pickup_points))
                        <h2 class="h3 mb-3">{{ $district->name }} biniş noktaları</h2>
                        <p class="text-muted">Hangi noktadan bineceğiniz, katılacağınız turun güzergâhına göre rezervasyon sırasında kesinleşir.</p>
                        <div class="chips mb-5">
                            @foreach ($district->pickup_points as $point)
                                <span class="chip"><i class="fa-solid fa-bus"></i>{{ $point }}</span>
                            @endforeach
                        </div>
                    @endif

                    @if (! empty($district->faqs))
                        <h2 class="h3 mb-3">{{ $district->name }} için sık sorulan sorular</h2>
                        @include('partials.faq-accordion', ['faqs' => $district->faqs, 'accordionId' => 'districtFaq', 'openFirst' => true])
                    @endif
                </div>
                <aside class="col-lg-4">
                    <div class="sidebar">
                        @include('partials.sidebar-cta', ['ctaTitle' => $district->name.'\'dan tura katılın', 'whatsappUrl' => $whatsappUrl])
                        @if ($otherDistricts->isNotEmpty())
                            <div class="sidebar-widget">
                                <h3>{{ $province->name }} ilindeki diğer ilçeler</h3>
                                <div class="chips">
                                    @foreach ($otherDistricts as $other)
                                        <a class="chip" href="{{ url('/'.$province->slug.'/'.$other->slug) }}">{{ $other->name }}</a>
                                    @endforeach
                                </div>
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
                <x-section-title subtitle="Turlar" :title="$district->name.' Çıkışlı Turlarımız'" text="Aşağıdaki turların tamamına bu ilçeden katılabilirsiniz." />
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

    @include('partials.cta-band', ['whatsappUrl' => $whatsappUrl, 'ctaTitle' => $district->name.'\'dan yola çıkmaya hazır mısınız?'])
@endsection
