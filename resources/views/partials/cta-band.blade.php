<section class="cta-band">
    <x-bg-picture :path="setting('cta_image')" :mobile="[720, 900]" :widths="[1280, 1920]" />
    <div class="container">
        <h2>{{ $ctaTitle ?? setting('cta_title') }}</h2>
        <p>{{ $ctaText ?? setting('cta_text') }}</p>
        <div class="cta-actions">
            <a class="btn btn-accent btn-lg" href="{{ $reservationUrl ?? route('reservation.create') }}"><i class="fa-regular fa-calendar-check"></i>Rezervasyon Yap</a>
            <a class="btn btn-whatsapp btn-lg" href="{{ $whatsappUrl ?? whatsapp_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan Yazın</a>
            @if (site_phone())
                <a class="btn btn-outline-white btn-lg" href="{{ phone_href() }}"><i class="fa-solid fa-phone"></i>{{ site_phone() }}</a>
            @endif
        </div>
    </div>
</section>
