@extends('layouts.app')

@section('content')
    {{-- ================= Hero ================= --}}
    <section class="hero">
        {{-- LCP öğesi: en önce iner. Mobilde koyu katman ağır olduğu için küçük kırpım yeterli. --}}
        <x-bg-picture :path="setting('hero_image')" :fallback="asset('images/placeholder.svg')" :mobile="[760, 980]" :widths="[1280, 1920]" :priority="true" />
        <div class="container">
            @if (setting('hero_badge'))
                <span class="hero-badge"><i class="fa-solid fa-location-dot"></i>{{ setting('hero_badge') }}</span>
            @endif
            <h1>{{ setting('hero_title', site_name()) }}</h1>
            @if (setting('hero_subtitle'))
                <p class="lead">{{ setting('hero_subtitle') }}</p>
            @endif
            @php $bullets = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', (string) setting('hero_bullets')))); @endphp
            @if ($bullets)
                <ul class="hero-bullets">
                    @foreach ($bullets as $bullet)
                        <li><i class="fa-solid fa-circle-check"></i><span>{{ $bullet }}</span></li>
                    @endforeach
                </ul>
            @endif
            <div class="hero-actions">
                <a class="btn btn-accent btn-lg" href="{{ route('tours.index') }}"><i class="fa-solid fa-compass"></i>Turları İncele</a>
                <a class="btn btn-outline-white btn-lg" href="{{ route('tours.calendar') }}"><i class="fa-regular fa-calendar"></i>Tur Takvimi</a>
            </div>
        </div>
    </section>

    {{-- ================= Tur bulucu ================= --}}
    <div class="tour-finder">
        <div class="container">
            <form class="finder-card" action="{{ route('tours.index') }}" method="GET" data-tour-finder data-base="{{ route('tours.index') }}">
                <div>
                    <label for="finderCategory">Nasıl bir tur?</label>
                    <select class="form-select" id="finderCategory" data-category>
                        <option value="">Tüm turlar</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->slug }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="finderDuration">Ne kadar süre?</label>
                    <select class="form-select" id="finderDuration" name="sure">
                        <option value="">Fark etmez</option>
                        <option value="gunubirlik">Günübirlik</option>
                        <option value="kisa">1-2 gece</option>
                        <option value="uzun">3 gece ve üzeri</option>
                    </select>
                </div>
                <button class="btn btn-deep btn-lg" type="submit"><i class="fa-solid fa-magnifying-glass"></i>Tur Bul</button>
            </form>
        </div>
    </div>

    {{-- ================= Güven öğeleri ================= --}}
    @if ($trustItems->isNotEmpty())
        <section class="section-sm">
            <div class="container">
                <div class="trust-grid">
                    @foreach ($trustItems as $item)
                        <div class="trust-item reveal">
                            <span class="icon"><i class="{{ $item->icon }}"></i></span>
                            <div>
                                <h3>{{ $item->title }}</h3>
                                <p>{{ $item->description }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ================= Öne çıkan turlar ================= --}}
    @if ($tours->isNotEmpty())
        <section class="section bg-sand">
            <div class="container">
                <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
                    <x-section-title class="mb-0" subtitle="Turlarımız" title="Öne Çıkan Turlar" text="Programı gün gün yazılı, fiyatı baştan belli turlar." />
                    <a class="link-arrow" href="{{ route('tours.index') }}">Tüm turları gör <i class="fa-solid fa-arrow-right"></i></a>
                </div>
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

    {{-- ================= Kategoriler ================= --}}
    @if ($categories->isNotEmpty())
        <section class="section">
            <div class="container">
                <x-section-title center subtitle="Kategoriler" title="Nasıl Bir Tatil İstiyorsunuz?" />
                <div class="row g-3 g-lg-4 vivid-cycle">
                    @foreach ($categories as $category)
                        <div class="col-lg-3 col-md-6 reveal">
                            <a class="category-tile" href="{{ $category->url }}">
                                <span class="icon"><i class="{{ $category->icon_class }}"></i></span>
                                <span>
                                    <strong>{{ $category->name }}</strong>
                                    <small>{{ $category->tours_count }} tur</small>
                                </span>
                                <i class="fa-solid fa-arrow-right arrow"></i>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ================= Yaklaşan seferler ================= --}}
    @if ($departures->isNotEmpty())
        <section class="section bg-mist">
            <div class="container">
                <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
                    <x-section-title class="mb-0" subtitle="Tur Takvimi" title="Yaklaşan Turlar" text="Kayıtları devam eden en yakın tarihler." />
                    <a class="link-arrow" href="{{ route('tours.calendar') }}">Tüm takvim <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <div class="departure-list">
                    @foreach ($departures as $departure)
                        @include('partials.departure-row', ['departure' => $departure])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ================= Hakkımızda ================= --}}
    <section class="section">
        <div class="container">
            <div class="row g-4 g-lg-5 align-items-center">
                <div class="col-lg-6 reveal">
                    @php $variants = app(\App\Services\Media\ImageVariants::class); @endphp
                    <div class="about-images">
                        <div class="img-main">
                            <img src="{{ filled(setting('about_image')) ? $variants->url(setting('about_image'), 800, 1000) : asset('images/placeholder.svg') }}" alt="{{ site_name() }} turlarından bir kare" loading="lazy" width="800" height="1000">
                        </div>
                        @if (filled(setting('about_image_2')))
                            <div class="img-float">
                                <img src="{{ $variants->url(setting('about_image_2'), 560, 420) }}" alt="" loading="lazy" width="560" height="420">
                            </div>
                        @endif
                    </div>
                </div>
                <div class="col-lg-6 reveal">
                    <x-section-title :subtitle="setting('about_subtitle', 'Hakkımızda')" :title="setting('about_title', site_name())" />
                    <div class="content-prose mb-4">{!! setting('about_text') !!}</div>
                    @if ($whyUs->isNotEmpty())
                        <div class="row g-3 mb-4">
                            @foreach ($whyUs->take(4) as $item)
                                <div class="col-sm-6">
                                    <div class="feature-row">
                                        <span class="icon"><i class="{{ $item->icon }}"></i></span>
                                        <div>
                                            <h3>{{ $item->title }}</h3>
                                            <p>{{ $item->description }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    <a class="btn btn-brand" href="{{ route('about') }}">Bizi Tanıyın<i class="fa-solid fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </section>

    {{-- ================= Sayaçlar ================= --}}
    @if ($stats->isNotEmpty())
        <section class="stats-band">
            <x-bg-picture :path="setting('banner_image')" :mobile="[720, 720]" :widths="[1280, 1920]" />
            <div class="container">
                <div class="row g-4">
                    @foreach ($stats as $stat)
                        <div class="col-6 col-lg-3">
                            <div class="stat">
                                <div class="value" data-count="{{ $stat['value'] }}">{{ $stat['value'] }}</div>
                                <div class="label">{{ $stat['label'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ================= Süreç ================= --}}
    @if ($processSteps->isNotEmpty())
        <section class="section bg-sand">
            <div class="container">
                <x-section-title center :subtitle="setting('process_subtitle', 'Nasıl Katılırım?')" :title="setting('process_title', 'Dört Adımda Tur Kaydı')" />
                <div class="process-steps">
                    @foreach ($processSteps as $step)
                        <div class="process-step reveal">
                            <span class="icon"><i class="{{ $step->icon }}"></i></span>
                            <h3>{{ $step->title }}</h3>
                            <p>{{ $step->description }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ================= Galeri ================= --}}
    @if ($galleryItems->isNotEmpty())
        <section class="section">
            <div class="container">
                <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
                    <x-section-title class="mb-0" subtitle="Galeri" title="Turlarımızdan Kareler" />
                    <a class="link-arrow" href="{{ route('gallery.index') }}">Tüm galeri <i class="fa-solid fa-arrow-right"></i></a>
                </div>
                <div class="gallery-grid">
                    @foreach ($galleryItems as $item)
                        @include('partials.gallery-item', ['item' => $item])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ================= Yorumlar (yalnız gerçek yorum girildiyse) ================= --}}
    @if ($testimonials->isNotEmpty())
        <section class="section bg-mist">
            <div class="container">
                <x-section-title center subtitle="Misafirlerimiz" title="Bizimle Yola Çıkanlar Ne Diyor?" />
                <div class="row g-4">
                    @foreach ($testimonials->take(6) as $testimonial)
                        <div class="col-lg-4 col-md-6 reveal">
                            <div class="testimonial-card">
                                <div class="stars" aria-label="{{ $testimonial->rating }} / 5">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <i class="fa-{{ $i <= $testimonial->rating ? 'solid' : 'regular' }} fa-star"></i>
                                    @endfor
                                </div>
                                <blockquote>{{ $testimonial->comment }}</blockquote>
                                <div class="author">{{ $testimonial->name }}
                                    @if ($testimonial->location || $testimonial->tour)
                                        <small>{{ collect([$testimonial->tour?->title, $testimonial->location])->filter()->implode(' · ') }}</small>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ================= Kalkış noktaları ================= --}}
    @if ($provinces->isNotEmpty())
        <section class="section bg-sand">
            <div class="container">
                <x-section-title center subtitle="Kalkış Noktaları" title="Nereden Katılabilirsiniz?" text="Rize ve Trabzon'un ilçelerinden, otelinizden ya da size en yakın biniş noktasından alınırsınız." />
                <div class="row g-4">
                    @foreach ($provinces as $province)
                        <div class="col-lg-4 reveal">
                            @include('partials.region-card', ['province' => $province])
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ================= Ekip ================= --}}
    @if ($staff->isNotEmpty())
        <section class="section">
            <div class="container">
                <x-section-title center subtitle="Ekibimiz" title="Doğrudan İlgili Kişiye Ulaşın" />
                @include('partials.staff-cards', ['staff' => $staff, 'compact' => true])
            </div>
        </section>
    @endif

    {{-- ================= SSS ================= --}}
    @if ($faqs->isNotEmpty())
        <section class="section">
            <div class="container">
                <div class="row g-4 g-lg-5">
                    <div class="col-lg-4">
                        <x-section-title subtitle="Merak Edilenler" title="Sık Sorulan Sorular" text="Aradığınız cevabı bulamadıysanız bize yazın." />
                        <a class="btn btn-outline-brand" href="{{ route('faq') }}">Tüm sorular<i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                    <div class="col-lg-8">
                        @include('partials.faq-accordion', ['faqs' => $faqs, 'accordionId' => 'homeFaq', 'openFirst' => true])
                    </div>
                </div>
            </div>
        </section>
    @endif

    @include('partials.cta-band')
@endsection
