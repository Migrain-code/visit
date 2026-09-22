@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => $post->title,
        'subtitle' => null,
        'breadcrumbs' => $breadcrumbs,
    ])

    <section class="section">
        <div class="container">
            <div class="row g-4 g-lg-5">
                <div class="col-lg-8">
                    <div class="d-flex flex-wrap gap-3 align-items-center mb-4 text-muted small">
                        @if ($post->category)
                            <span><i class="fa-solid fa-folder text-accent me-1"></i>{{ $post->category->name }}</span>
                        @endif
                        <span><i class="fa-regular fa-calendar text-accent me-1"></i>{{ optional($post->publish_at ?? $post->created_at)->translatedFormat('d F Y') }}</span>
                        <span><i class="fa-regular fa-clock text-accent me-1"></i>{{ $post->reading_minutes }} dakika okuma</span>
                    </div>

                    @if ($post->image)
                        <div class="tour-hero-image mb-4">
                            <img src="{{ $post->image_url }}" alt="{{ $post->image_alt ?: $post->title }}" width="1100" height="619">
                        </div>
                    @endif

                    {{-- İç linkler RENDER ANINDA basılır; tavanlar her istekte kontrol edilir (spec §3.6). --}}
                    <div class="content-prose mb-5">
                        {!! internal_links($post->body_html, 'blog', $post->path()) !!}
                    </div>

                    @if (! empty($post->faqs))
                        <h2 class="h3 mb-3">Sık sorulan sorular</h2>
                        <div class="mb-5">
                            @include('partials.faq-accordion', ['faqs' => $post->faqs, 'accordionId' => 'blogFaq', 'openFirst' => true])
                        </div>
                    @endif

                    <div class="d-flex flex-wrap gap-3">
                        <a class="btn btn-accent btn-lg" href="{{ route('tours.calendar') }}"><i class="fa-regular fa-calendar"></i>Tur Takvimine Bak</a>
                        <a class="btn btn-whatsapp btn-lg" href="{{ whatsapp_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan Yazın</a>
                    </div>

                    @if ($related->isNotEmpty())
                        <h2 class="h3 mt-5 mb-3">İlgili yazılar</h2>
                        <ul class="check-list">
                            @foreach ($related as $item)
                                <li><i class="fa-regular fa-newspaper"></i><a href="{{ $item->url }}">{{ $item->title }}</a></li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <aside class="col-lg-4">
                    <div class="sidebar">
                        @include('partials.sidebar-cta')
                        <div class="sidebar-widget">
                            <h3>Öne çıkan turlar</h3>
                            <ul class="side-links">
                                @foreach ($tours as $tour)
                                    <li><a href="{{ $tour->url }}">{{ $tour->title }} <small>{{ $tour->duration_label }}</small></a></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </aside>
            </div>
        </div>
    </section>

    @include('partials.cta-band')
@endsection
