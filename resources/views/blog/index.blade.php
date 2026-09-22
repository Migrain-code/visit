@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => $activeCategory?->name ?? 'Blog',
        'subtitle' => $activeCategory?->description ?? 'Gezilecek yerler, tur öncesi hazırlık ve yolculuk ipuçları.',
        'breadcrumbs' => array_filter([
            ['name' => 'Ana Sayfa', 'url' => url('/')],
            ['name' => 'Blog', 'url' => route('blog.index')],
            $activeCategory ? ['name' => $activeCategory->name, 'url' => $activeCategory->url] : null,
        ]),
    ])

    <section class="section">
        <div class="container">
            @if ($categories->count() > 1)
                <div class="gallery-filters">
                    <a class="filter-btn {{ $activeCategory ? '' : 'active' }}" href="{{ route('blog.index') }}">Tümü</a>
                    @foreach ($categories as $category)
                        <a class="filter-btn {{ $activeCategory?->is($category) ? 'active' : '' }}" href="{{ $category->url }}">
                            {{ $category->name }} ({{ $category->published_posts_count }})
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($posts->isEmpty())
                <p class="text-center text-muted py-5">Bu bölümde henüz yazı yok.</p>
            @else
                <div class="row g-4">
                    @foreach ($posts as $post)
                        <div class="col-lg-4 col-md-6 reveal">
                            <article class="post-card">
                                <a class="media" href="{{ $post->url }}" tabindex="-1" aria-hidden="true">
                                    @if ($post->image)
                                        <img src="{{ $post->image_url }}" alt="{{ $post->image_alt ?: $post->title }}" loading="lazy" width="600" height="375">
                                    @else
                                        <i class="fa-regular fa-compass"></i>
                                    @endif
                                </a>
                                <div class="body">
                                    <div class="meta">
                                        @if ($post->category)<a href="{{ $post->category->url }}">{{ $post->category->name }}</a> · @endif
                                        {{ $post->reading_minutes }} dk okuma
                                    </div>
                                    <h2><a href="{{ $post->url }}">{{ $post->title }}</a></h2>
                                    <p>{{ $post->summary }}</p>
                                    <a class="link-arrow" href="{{ $post->url }}">Devamını oku <i class="fa-solid fa-arrow-right"></i></a>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>

                <div class="mt-5 d-flex justify-content-center">
                    {{ $posts->links() }}
                </div>
            @endif
        </div>
    </section>

    @include('partials.cta-band')
@endsection
