{{-- Ana sayfadaki tur kartı. Tıklanınca detay penceresi açılır; fiyat düğmesi de aynı pencereye gider. --}}
@php
    $variants = app(\App\Services\Media\ImageVariants::class);
    $wide = ($index ?? 0) % 5 === 4; // her beşinci kart geniş (yatay) düzen
@endphp
<article class="tour-card {{ $wide ? 'is-wide' : '' }}">
    <a class="tour-card-media" href="#tour-{{ $tour->id }}" data-bs-toggle="modal" data-bs-target="#tour-{{ $tour->id }}" aria-label="{{ $tour->title }} detayları">
        <img src="{{ $variants->url($tour->image, 640, 480) ?? $tour->image_url }}" alt="{{ $tour->image_alt ?: $tour->title }}" loading="lazy" decoding="async" width="640" height="480">
        @if ($tour->badge)
            <span class="pill pill-badge"><i class="fa-solid fa-fire"></i>{{ $tour->badge }}</span>
        @endif
        @if ($tour->is_full)
            <span class="pill pill-full">Doldu</span>
        @elseif ($tour->seats_left !== null && $tour->seats_left <= 5)
            <span class="pill pill-full">Son {{ $tour->seats_left }} koltuk</span>
        @endif
    </a>
    <div class="tour-card-body">
        <h3><a href="#tour-{{ $tour->id }}" data-bs-toggle="modal" data-bs-target="#tour-{{ $tour->id }}">{{ $tour->title }}</a></h3>
        @if ($tour->short_description)
            <p>{{ \Illuminate\Support\Str::limit($tour->short_description, 90) }}</p>
        @endif
        <div class="tour-card-footer">
            <span class="date-chip"><i class="fa-regular fa-calendar"></i>{{ $tour->short_date_label }}</span>
            <a class="price-btn" href="#tour-{{ $tour->id }}" data-bs-toggle="modal" data-bs-target="#tour-{{ $tour->id }}">
                {{ $tour->price_label ?? 'Detay' }}<i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>
    </div>
</article>
