<div class="sidebar-widget is-dark">
    <h3>{{ $ctaTitle ?? 'Yerinizi ayırtın' }}</h3>
    <p class="mb-3">{{ $ctaText ?? 'Kişi sayınızı ve katılacağınız bölgeyi yazın; aynı gün içinde sizi arayalım.' }}</p>
    <div class="d-grid gap-2">
        <a class="btn btn-accent" href="{{ $reservationUrl ?? route('reservation.create') }}"><i class="fa-regular fa-calendar-check"></i>Rezervasyon Yap</a>
        <a class="btn btn-whatsapp" href="{{ $whatsappUrl ?? whatsapp_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan Yazın</a>
        @if (site_phone())
            <a class="btn btn-outline-white" href="{{ phone_href() }}"><i class="fa-solid fa-phone"></i>{{ site_phone() }}</a>
        @endif
    </div>
</div>
