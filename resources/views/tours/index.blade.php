@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => $category?->name ?? 'Turlar',
        'subtitle' => $category?->description ?? 'Yurt içi, yurt dışı ve günübirlik turlarımız. Programı inceleyin, size uyan tarihi seçin.',
        'breadcrumbs' => $breadcrumbs ?? [
            ['name' => 'Ana Sayfa', 'url' => url('/')],
            ['name' => 'Turlar', 'url' => route('tours.index')],
        ],
    ])

    <section class="section">
        <div class="container">
            {{-- Kategori ve süre filtreleri. Bağlantıdır (JS gerektirmez) ve taranabilir. --}}
            @php $base = $category ? $category->url : route('tours.index'); @endphp
            <div class="gallery-filters mb-3">
                <a class="filter-btn {{ $category ? '' : 'active' }}" href="{{ route('tours.index') }}">Tümü</a>
                @foreach ($categories as $item)
                    <a class="filter-btn {{ $category?->is($item) ? 'active' : '' }}" href="{{ $item->url }}">{{ $item->name }} <small class="opacity-75">({{ $item->tours_count }})</small></a>
                @endforeach
            </div>
            <div class="gallery-filters">
                <a class="filter-btn {{ $duration ? '' : 'active' }}" href="{{ $base }}" rel="nofollow"><i class="fa-regular fa-clock"></i>Tüm süreler</a>
                @foreach ($durations as $key => $label)
                    <a class="filter-btn {{ $duration === $key ? 'active' : '' }}" href="{{ $base }}?sure={{ $key }}" rel="nofollow">{{ $label }}</a>
                @endforeach
            </div>

            @if ($tours->isEmpty())
                <div class="empty-state">
                    <i class="fa-regular fa-compass"></i>
                    <h2 class="h4">Bu seçime uyan tur şu anda yok</h2>
                    <p class="mb-3">Filtreyi değiştirin ya da aklınızdaki rotayı bize yazın; grubunuza özel tur planlayalım.</p>
                    <a class="btn btn-brand" href="{{ route('tours.index') }}">Tüm turları gör</a>
                </div>
            @else
                <div class="row g-4">
                    @foreach ($tours as $tour)
                        <div class="col-lg-4 col-md-6 reveal">
                            @include('partials.tour-card', ['tour' => $tour])
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    @if ($category && (filled($category->content) || ! empty($category->faqs)))
        <section class="section bg-sand">
            <div class="container">
                <div class="row g-4 g-lg-5">
                    <div class="col-lg-7">
                        <h2 class="mb-4">{{ $category->name }} hakkında</h2>
                        <div class="content-prose">{!! internal_links($category->content, 'category', $category->path()) !!}</div>
                    </div>
                    @if (! empty($category->faqs))
                        <div class="col-lg-5">
                            <h2 class="h3 mb-3">Sık sorulan sorular</h2>
                            @include('partials.faq-accordion', ['faqs' => $category->faqs, 'accordionId' => 'categoryFaq'])
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endif

    @include('partials.cta-band')
@endsection
