@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => 'Galeri',
        'subtitle' => 'Gezdiğimiz yerlerden ve turlarımızdan fotoğraflar.',
        'breadcrumbs' => [['name' => 'Ana Sayfa', 'url' => url('/')], ['name' => 'Galeri', 'url' => route('gallery.index')]],
    ])

    <section class="section">
        <div class="container">
            @if ($items->isEmpty())
                <p class="text-center text-muted">Galeri görselleri yakında eklenecek.</p>
            @else
                <div data-gallery>
                    @if ($categories->count() > 1)
                        <div class="gallery-filters">
                            <button type="button" class="filter-btn {{ $activeCategory ? '' : 'active' }}" data-filter="all">Tümü</button>
                            @foreach ($categories as $category)
                                <button type="button" class="filter-btn {{ $activeCategory === $category->slug ? 'active' : '' }}" data-filter="{{ $category->slug }}">{{ $category->name }}</button>
                            @endforeach
                        </div>
                    @endif
                    <div class="gallery-grid">
                        @foreach ($items as $item)
                            @include('partials.gallery-item', ['item' => $item])
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </section>

    @include('partials.cta-band')
@endsection
