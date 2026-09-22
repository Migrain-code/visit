{{-- Tur takvimindeki tek bir sefer. $showTour=false ise tur adı yerine tarih aralığı öne çıkar. --}}
@php
    $tour = $departure->tour;
    $left = $departure->seats_left;
    $full = $departure->is_full;
    $reserveUrl = route('reservation.create', ['tur' => $tour->slug, 'sefer' => $departure->getKey()]);
@endphp
<div class="departure-row {{ $full ? 'is-full' : '' }}">
    <div class="date-block">
        <span class="day">{{ $departure->starts_at->format('d') }}</span>
        <span class="month">{{ $departure->starts_at->translatedFormat('M') }}</span>
        <span class="weekday">{{ $departure->starts_at->translatedFormat('l') }}</span>
    </div>
    <div class="dep-info">
        <a class="dep-title" href="{{ $tour->url }}">{{ $tour->title }}</a>
        <div class="dep-sub">
            <span><i class="fa-regular fa-clock"></i>{{ $tour->duration_label }}</span>
            <span><i class="fa-regular fa-calendar"></i>{{ $departure->date_range_label }}</span>
        </div>
    </div>
    <div class="dep-seats">
        @if ($full)
            <span class="seat-badge is-full"><i class="fa-solid fa-ban"></i>Doldu</span>
        @elseif ($left !== null && $left <= 8)
            <span class="seat-badge is-low"><i class="fa-solid fa-fire"></i>Son {{ $left }} koltuk</span>
        @else
            <span class="seat-badge"><i class="fa-solid fa-check"></i>Kayıt açık</span>
        @endif
    </div>
    <div class="dep-price price">
        @if ($departure->price_label)
            <span class="from">Kişi başı</span>
            <span class="now">{{ $departure->price_label }}</span>
        @endif
    </div>
    <div class="dep-action">
        @if ($full)
            <a class="btn btn-outline-brand btn-sm" href="{{ $tour->url }}">Diğer tarihler</a>
        @else
            <a class="btn btn-accent btn-sm" href="{{ $reserveUrl }}">Yer ayırt</a>
        @endif
    </div>
</div>
