@extends('layouts.app')

@section('content')
    <div class="container app-shell">
        {{-- ================= Hero ================= --}}
        <section class="hero-card" aria-label="Öne çıkan">
            <x-bg-picture :path="setting('hero_image')" :fallback="asset('images/placeholder.svg')" :mobile="[720, 640]" :widths="[1200, 1800]" :priority="true" />
            <div class="hero-content">
                <h1 class="hero-title">
                    {{ setting('hero_title', 'Bu Haftanın') }}
                    @if (setting('hero_highlight', 'Rotaları'))
                        <em>{{ setting('hero_highlight', 'Rotaları') }}</em>
                    @endif
                </h1>
                @if (setting('hero_subtitle'))
                    <p class="hero-text">{{ setting('hero_subtitle') }}</p>
                @endif
                <a class="btn btn-sun" href="#turlar">{{ setting('hero_cta_text', 'Keşfetmeye Başla') }}<i class="fa-solid fa-arrow-right"></i></a>
            </div>
            @if (setting('hero_note'))
                <span class="hero-note">{{ setting('hero_note') }}</span>
            @endif
        </section>

        {{-- ================= Turlar ================= --}}
        <section class="section-block" id="turlar">
            <div class="section-head">
                <h2><i class="fa-solid fa-location-dot"></i>{{ setting('tours_title', 'Haftalık Turlar') }}</h2>
                <a class="link-arrow" href="{{ route('contact') }}">Bilgi Al <i class="fa-solid fa-arrow-right"></i></a>
            </div>

            @if ($tours->isNotEmpty())
                <div class="tour-grid">
                    @foreach ($tours as $tour)
                        @include('partials.tour-card', ['tour' => $tour, 'index' => $loop->index])
                    @endforeach
                </div>
            @else
                <div class="empty-state">
                    <i class="fa-regular fa-compass"></i>
                    <p>{{ setting('tours_empty_text', 'Yeni rotalar çok yakında. Instagram\'dan takipte kal!') }}</p>
                    @if (instagram_url())
                        <a class="btn btn-instagram" href="{{ instagram_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-instagram"></i>{{ instagram_handle() }}</a>
                    @endif
                </div>
            @endif
        </section>

        {{-- ================= Öne çıkanlar ================= --}}
        <section class="feature-strip" aria-label="Neden biz">
            @foreach ($features as $feature)
                <div class="feature">
                    <span class="feature-icon"><i class="{{ $feature['icon'] }}"></i></span>
                    <span class="feature-body">
                        <strong>{{ $feature['title'] }}</strong>
                        <small>{{ $feature['text'] }}</small>
                    </span>
                </div>
            @endforeach
        </section>

        {{-- ================= Instagram ================= --}}
        @if (instagram_url())
            <a class="insta-band" href="{{ instagram_url() }}" target="_blank" rel="noopener">
                <span class="insta-icon"><i class="fa-brands fa-instagram"></i></span>
                <span class="insta-text"><strong>{{ mb_strtoupper(instagram_handle(), 'UTF-8') }}</strong> · Bizi Instagram'da takip et</span>
                <i class="fa-solid fa-arrow-right insta-arrow"></i>
            </a>
        @endif
    </div>

    {{-- ================= Tur detay pencereleri ================= --}}
    @foreach ($tours as $tour)
        <div class="modal fade tour-modal" id="tour-{{ $tour->id }}" tabindex="-1" aria-labelledby="tour-{{ $tour->id }}-title" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="tour-modal-media">
                        <img src="{{ app(\App\Services\Media\ImageVariants::class)->url($tour->image, 900, 560) ?? $tour->image_url }}" alt="{{ $tour->image_alt ?: $tour->title }}" loading="lazy" width="900" height="560">
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Kapat"></button>
                        @if ($tour->badge)
                            <span class="pill pill-sun">{{ $tour->badge }}</span>
                        @endif
                    </div>
                    <div class="modal-body">
                        <h3 class="modal-title" id="tour-{{ $tour->id }}-title">{{ $tour->title }}</h3>
                        <ul class="tour-facts">
                            <li><i class="fa-regular fa-calendar"></i>{{ $tour->date_range_label }} · {{ $tour->starts_at->format('H:i') }}</li>
                            @if ($tour->meeting_point)
                                <li><i class="fa-solid fa-location-dot"></i>Kalkış: {{ $tour->meeting_point }}</li>
                            @endif
                            @if ($tour->price_label)
                                <li><i class="fa-solid fa-tag"></i>Kişi başı {{ $tour->price_label }}</li>
                            @endif
                            @if ($tour->seats_left !== null)
                                <li><i class="fa-solid fa-chair"></i>{{ $tour->is_full ? 'Kontenjan doldu' : $tour->seats_left.' koltuk kaldı' }}</li>
                            @endif
                        </ul>
                        @if ($tour->short_description)
                            <p class="lead-text">{{ $tour->short_description }}</p>
                        @endif
                        @if ($tour->description)
                            <div class="content-prose">{!! $tour->description !!}</div>
                        @endif
                    </div>
                    <div class="modal-footer tour-modal-actions">
                        @if (whatsapp_number())
                            <a class="btn btn-whatsapp btn-icon" href="{{ whatsapp_url($tour->title) }}" target="_blank" rel="noopener" aria-label="WhatsApp'tan sor" title="WhatsApp'tan sor"><i class="fa-brands fa-whatsapp"></i></a>
                        @endif
                        <a class="btn btn-sun" href="{{ route('contact', ['tur' => $tour->id]) }}">Katılmak İstiyorum<i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
@endsection
