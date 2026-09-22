@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => 'Hakkımızda',
        'subtitle' => setting('site_tagline'),
        'breadcrumbs' => [['name' => 'Ana Sayfa', 'url' => url('/')], ['name' => 'Hakkımızda', 'url' => route('about')]],
    ])

    <section class="section">
        <div class="container">
            <div class="row g-4 g-lg-5">
                <div class="col-lg-8">
                    <div class="tour-hero-image mb-4">
                        <img src="{{ filled(setting('about_image')) ? app(\App\Services\Media\ImageVariants::class)->url(setting('about_image'), 1100, 619) : asset('images/placeholder.svg') }}" alt="{{ site_name() }} turlarından bir kare" width="1100" height="619">
                    </div>
                    <div class="content-prose">{!! setting('about_page_content') ?: setting('about_text') !!}</div>
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
