{{--
    Tur kartı. $tour->next_departure (withMin ile gelir) varsa en yakın sefer tarihi gösterilir.
    Görsel iki boyda üretilir; kart genişliğine göre tarayıcı seçer.
--}}
@php
    $variants = app(\App\Services\Media\ImageVariants::class);
    $hasImage = filled($tour->image);
    $next = $tour->next_departure ? \Illuminate\Support\Carbon::parse($tour->next_departure) : null;
@endphp
<article class="tour-card">
    <a class="tour-card-media" href="{{ $tour->url }}" tabindex="-1" aria-hidden="true">
        @if ($hasImage)
            <img src="{{ $variants->url($tour->image, 640, 427) }}"
                 srcset="{{ $variants->url($tour->image, 480, 320) }} 480w, {{ $variants->url($tour->image, 800, 533) }} 800w"
                 sizes="(max-width: 767px) 92vw, (max-width: 1199px) 46vw, 380px"
                 alt="{{ $tour->image_alt ?: $tour->title }}" width="640" height="427" loading="lazy" decoding="async">
        @else
            <img src="{{ asset('images/placeholder.svg') }}" alt="" width="640" height="427" loading="lazy">
        @endif
        <span class="tour-card-badges">
            <span class="pill"><i class="fa-regular fa-clock"></i>{{ $tour->duration_label }}</span>
            @if ($tour->has_discount)
                <span class="pill pill-accent">%{{ $tour->discount_percent }} indirim</span>
            @endif
        </span>
    </a>
    <div class="tour-card-body">
        @if ($tour->category)
            <a class="tour-card-category" href="{{ $tour->category->url }}">{{ $tour->category->name }}</a>
        @endif
        <h3><a href="{{ $tour->url }}">{{ $tour->title }}</a></h3>
        <p>{{ \Illuminate\Support\Str::limit($tour->short_description, 120) }}</p>
        <ul class="tour-card-meta">
            @if ($next)
                <li><i class="fa-regular fa-calendar"></i>İlk tarih: {{ $next->translatedFormat('j F') }}</li>
            @else
                <li><i class="fa-regular fa-calendar"></i>Tarih için sorun</li>
            @endif
            @if ($tour->transport)
                <li><i class="fa-solid fa-bus"></i>{{ \Illuminate\Support\Str::limit($tour->transport, 22) }}</li>
            @endif
        </ul>
        <div class="tour-card-footer">
            <div class="price">
                @if ($tour->price_label)
                    <span class="from">Kişi başı</span>
                    @if ($tour->old_price_label)<span class="old">{{ $tour->old_price_label }}</span>@endif
                    <span class="now">{{ $tour->price_label }}</span>
                @else
                    <span class="from">Fiyat</span>
                    <span class="now fs-5">Sorunuz</span>
                @endif
            </div>
            <a class="btn btn-brand btn-sm" href="{{ $tour->url }}">İncele<i class="fa-solid fa-arrow-right"></i></a>
        </div>
    </div>
</article>
