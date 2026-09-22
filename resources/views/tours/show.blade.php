@extends('layouts.app')

@section('content')
    @php
        $variants = app(\App\Services\Media\ImageVariants::class);
        $reserveUrl = route('reservation.create', ['tur' => $tour->slug]);
        $gallery = collect($tour->gallery ?? [])->filter()->values();
    @endphp

    @include('partials.page-banner', [
        'title' => $tour->title,
        'subtitle' => $tour->short_description,
        'breadcrumbs' => $breadcrumbs,
        'badges' => array_values(array_filter([
            ['fa-regular fa-clock', $tour->duration_label],
            $tour->category ? ['fa-solid fa-tag', $tour->category->name] : null,
            $departures->isNotEmpty() ? ['fa-regular fa-calendar', $departures->count().' tarih açık'] : null,
        ])),
    ])

    <section class="section">
        <div class="container">
            <div class="row g-4 g-lg-5">
                <div class="col-lg-8">
                    {{-- Kapak görseli: sayfanın en büyük öğesi, öncelikli yüklenir. --}}
                    <div class="tour-hero-image">
                        @if (filled($tour->image))
                            <img src="{{ $variants->url($tour->image, 1100, 619) }}"
                                 srcset="{{ $variants->url($tour->image, 720, 405) }} 720w, {{ $variants->url($tour->image, 1100, 619) }} 1100w"
                                 sizes="(max-width: 991px) 94vw, 820px"
                                 alt="{{ $tour->image_alt ?: $tour->title }}" width="1100" height="619" fetchpriority="high" decoding="async">
                        @else
                            <img src="{{ asset('images/placeholder.svg') }}" alt="" width="1100" height="619">
                        @endif
                    </div>
                    @if ($gallery->isNotEmpty())
                        <div class="tour-thumbs">
                            @foreach ($gallery->take(8) as $image)
                                <a class="gallery-item" href="{{ media_url($image) }}" data-full="{{ media_url($image) }}" data-title="{{ $tour->title }}" data-alt="{{ $tour->title }}">
                                    <img src="{{ $variants->url($image, 400, 267) }}" alt="{{ $tour->title }} fotoğrafı {{ $loop->iteration }}" loading="lazy" width="400" height="267">
                                </a>
                            @endforeach
                        </div>
                    @endif

                    <div class="tour-facts">
                        <div class="fact"><i class="fa-regular fa-clock"></i><div><small>Süre</small><strong>{{ $tour->duration_label }}</strong></div></div>
                        <div class="fact"><i class="fa-solid fa-bus"></i><div><small>Ulaşım</small><strong>{{ $tour->transport ?: 'Tur otobüsü' }}</strong></div></div>
                        <div class="fact"><i class="fa-solid fa-bed"></i><div><small>Konaklama</small><strong>{{ $tour->accommodation ?: ($tour->is_day_trip ? 'Konaklamasız' : 'Program içinde') }}</strong></div></div>
                        <div class="fact"><i class="fa-solid fa-location-dot"></i><div><small>Kalkış</small><strong>{{ $tour->departure_point ?: setting('service_area_text') }}</strong></div></div>
                    </div>

                    @if ($tour->destinations)
                        <div class="chips mb-4">
                            @foreach (array_filter(array_map('trim', explode(',', $tour->destinations))) as $place)
                                <span class="chip"><i class="fa-solid fa-location-dot"></i>{{ $place }}</span>
                            @endforeach
                        </div>
                    @endif

                    <div class="content-prose">{!! internal_links($tour->description, 'tour', '/'.$tour->slug) !!}</div>

                    @if (! empty($tour->highlights))
                        <h2 class="h3 mt-5 mb-3">Bu turda öne çıkanlar</h2>
                        <ul class="check-list two-col">
                            @foreach ($tour->highlights as $item)
                                <li><i class="fa-solid fa-star"></i><span>{{ $item }}</span></li>
                            @endforeach
                        </ul>
                    @endif

                    @if (! empty($tour->itinerary))
                        <h2 class="h3 mt-5 mb-4">Tur programı</h2>
                        <ol class="itinerary">
                            @foreach ($tour->itinerary as $day)
                                @continue(blank($day['title'] ?? null))
                                <li class="itinerary-day">
                                    <h3>{{ $day['title'] }}</h3>
                                    @if (filled($day['description'] ?? null))
                                        <p>{{ $day['description'] }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    @if (! empty($tour->included) || ! empty($tour->excluded))
                        <h2 class="h3 mt-5 mb-3">Fiyata neler dahil?</h2>
                        <div class="row g-3">
                            @if (! empty($tour->included))
                                <div class="col-md-6">
                                    <div class="incl-box is-included">
                                        <h3><i class="fa-solid fa-circle-check"></i>Dahil olanlar</h3>
                                        <ul class="check-list">
                                            @foreach ($tour->included as $item)
                                                <li><i class="fa-solid fa-check"></i><span>{{ $item }}</span></li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            @endif
                            @if (! empty($tour->excluded))
                                <div class="col-md-6">
                                    <div class="incl-box is-excluded">
                                        <h3><i class="fa-solid fa-circle-xmark"></i>Dahil olmayanlar</h3>
                                        <ul class="check-list is-excluded">
                                            @foreach ($tour->excluded as $item)
                                                <li><i class="fa-solid fa-xmark"></i><span>{{ $item }}</span></li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if (! empty($tour->faqs))
                        <h2 class="h3 mt-5 mb-3">{{ $tour->title }} hakkında sık sorulanlar</h2>
                        @include('partials.faq-accordion', ['faqs' => $tour->faqs, 'accordionId' => 'tourFaq'])
                    @endif

                    @if ($provinces->isNotEmpty())
                        <h2 class="h3 mt-5 mb-3">Bu tura nereden katılabilirim?</h2>
                        <p>Turun güzergâhı üzerindeki otellerden ve biniş noktalarından misafir alıyoruz. Alınış noktanız ve saatiniz, konakladığınız yere göre rezervasyon sırasında bildirilir.</p>
                        @foreach ($provinces as $province)
                            <h3 class="h6 mt-3 mb-2"><a href="{{ $province->url }}">{{ $province->name }}</a></h3>
                            <div class="chips">
                                @foreach ($province->activeDistricts as $district)
                                    <a class="chip" href="{{ url('/'.$province->slug.'/'.$district->slug) }}">{{ $district->name }}</a>
                                @endforeach
                            </div>
                        @endforeach
                    @endif
                </div>

                {{-- ================= Fiyat ve tarihler ================= --}}
                <div class="col-lg-4">
                    <aside class="sidebar">
                        <div class="booking-card">
                            <div class="booking-head">
                                <div class="price price-lg">
                                    @if ($tour->price_label)
                                        <span class="from">Kişi başı</span>
                                        @if ($tour->old_price_label)<span class="old">{{ $tour->old_price_label }}</span>@endif
                                        <span class="now">{{ $tour->price_label }}</span>
                                    @else
                                        <span class="from">Fiyat</span>
                                        <span class="now">Sorunuz</span>
                                    @endif
                                </div>
                                @if ($tour->price_note)
                                    <p class="note">{{ $tour->price_note }}</p>
                                @endif
                            </div>
                            <div class="booking-body">
                                <h3>Tur tarihleri</h3>
                                @if ($departures->isNotEmpty())
                                    <div class="departure-pick">
                                        @foreach ($departures as $departure)
                                            <a class="departure-option {{ $departure->is_full ? 'is-full' : '' }}"
                                               href="{{ route('reservation.create', ['tur' => $tour->slug, 'sefer' => $departure->getKey()]) }}">
                                                <span>
                                                    <span class="d-date">{{ $departure->date_range_label }}</span>
                                                    <span class="d-sub">
                                                        {{ $departure->starts_at->translatedFormat('l') }} ·
                                                        @if ($departure->is_full) Doldu
                                                        @elseif ($departure->seats_left !== null && $departure->seats_left <= 8) Son {{ $departure->seats_left }} koltuk
                                                        @else Kayıt açık
                                                        @endif
                                                    </span>
                                                </span>
                                                <span class="d-price">{{ $departure->price_label }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                @else
                                    <p class="small text-muted">Bu tur için yeni tarihler yakında açıklanacak. Bize yazın, tarih açıklandığında haber verelim.</p>
                                @endif
                                <div class="d-grid gap-2">
                                    <a class="btn btn-accent btn-lg" href="{{ $reserveUrl }}"><i class="fa-regular fa-calendar-check"></i>Rezervasyon Yap</a>
                                    <a class="btn btn-whatsapp" href="{{ $whatsappUrl }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan Sorun</a>
                                    @if (site_phone())
                                        <a class="btn btn-outline-brand" href="{{ phone_href() }}"><i class="fa-solid fa-phone"></i>{{ site_phone() }}</a>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="sidebar-widget">
                            <h3>Neden {{ site_name() }}?</h3>
                            <ul class="check-list small">
                                <li><i class="fa-solid fa-check"></i><span>Otelinizden alınma, otelinize bırakılma</span></li>
                                <li><i class="fa-solid fa-check"></i><span>Aileniz ve arkadaşlarınızla aynı araçta</span></li>
                                <li><i class="fa-solid fa-check"></i><span>Bölgeyi bilen rehber, yazılı program</span></li>
                                <li><i class="fa-solid fa-check"></i><span>Zorunlu seyahat sigortası dahil</span></li>
                            </ul>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </section>

    @if ($relatedTours->isNotEmpty())
        <section class="section bg-sand">
            <div class="container">
                <x-section-title subtitle="Diğer Turlar" title="Bunlar da İlginizi Çekebilir" />
                <div class="row g-4">
                    @foreach ($relatedTours as $related)
                        <div class="col-lg-4 col-md-6">
                            @include('partials.tour-card', ['tour' => $related])
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @include('partials.cta-band', ['ctaTitle' => $tour->title.' için yerinizi ayırtın', 'whatsappUrl' => $whatsappUrl, 'reservationUrl' => $reserveUrl])
@endsection

