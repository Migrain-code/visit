<div class="offcanvas offcanvas-end mobile-nav" tabindex="-1" id="mobileNav" aria-labelledby="mobileNavLabel">
    <div class="offcanvas-header">
        <span class="mobile-nav-title" id="mobileNavLabel">{{ site_name() }}</span>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Kapat"></button>
    </div>
    <div class="offcanvas-body">
        <ul class="mnav">
            <li><a class="mnav-link" href="{{ route('home') }}"><i class="fa-solid fa-house"></i>Ana Sayfa</a></li>
            <li><a class="mnav-link" href="{{ route('home') }}#turlar"><i class="fa-solid fa-map-location-dot"></i>Turlar</a></li>
            <li><a class="mnav-link" href="{{ route('contact') }}"><i class="fa-solid fa-envelope"></i>İletişim</a></li>
            <li><a class="mnav-link" href="{{ route('jobs.create') }}"><i class="fa-solid fa-briefcase"></i>İş Başvurusu</a></li>
        </ul>
        <div class="d-grid gap-2 mt-4">
            @if (site_phone())
                <a class="btn btn-call" href="{{ phone_href() }}"><i class="fa-solid fa-phone"></i>{{ site_phone() }}</a>
            @endif
            @if (whatsapp_number())
                <a class="btn btn-whatsapp" href="{{ whatsapp_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan Yaz</a>
            @endif
            @if (instagram_url())
                <a class="btn btn-instagram" href="{{ instagram_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-instagram"></i>{{ instagram_handle() }}</a>
            @endif
        </div>
    </div>
</div>
