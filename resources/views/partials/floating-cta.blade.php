<a class="wa-float" href="{{ $whatsappUrl ?? whatsapp_url() }}" target="_blank" rel="noopener" aria-label="WhatsApp'tan yazın">
    <i class="fa-brands fa-whatsapp"></i>
</a>
<div class="mobile-cta-bar">
    @if (site_phone())
        <a class="is-call" href="{{ phone_href() }}"><i class="fa-solid fa-phone"></i>Ara</a>
    @endif
    <a class="is-wa" href="{{ $whatsappUrl ?? whatsapp_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp</a>
    <a class="is-book" href="{{ $reservationUrl ?? route('reservation.create') }}"><i class="fa-regular fa-calendar-check"></i>Rezervasyon</a>
</div>
<button class="back-to-top" type="button" aria-label="Yukarı çık"><i class="fa-solid fa-arrow-up"></i></button>
