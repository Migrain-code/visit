<div class="offcanvas offcanvas-end mobile-nav" tabindex="-1" id="mobileNav" aria-labelledby="mobileNavLabel">
    <div class="offcanvas-header">
        <span class="brand" id="mobileNavLabel">
            <img src="{{ setting('logo') ? media_url(setting('logo')) : versioned_asset('images/brand/logo.svg') }}"
                 alt="{{ site_name() }}" width="172" height="44">
        </span>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Kapat"></button>
    </div>
    <div class="offcanvas-body">
        <ul class="mnav">
            <li><a class="mnav-link" href="{{ route('home') }}">Ana Sayfa</a></li>
            <li>
                <a class="mnav-link" data-bs-toggle="collapse" href="#mnavTours" role="button" aria-expanded="false" aria-controls="mnavTours">Turlar <i class="fa-solid fa-chevron-down toggle-icon"></i></a>
                <ul class="collapse mnav-sub" id="mnavTours">
                    @foreach ($navCategories as $category)
                        <li><a href="{{ $category->url }}">{{ $category->name }}</a></li>
                    @endforeach
                    <li><a href="{{ route('tours.index') }}" class="fw-semibold">Tüm Turlar</a></li>
                </ul>
            </li>
            <li><a class="mnav-link" href="{{ route('tours.calendar') }}">Tur Takvimi</a></li>
            <li>
                <a class="mnav-link" data-bs-toggle="collapse" href="#mnavRegions" role="button" aria-expanded="false" aria-controls="mnavRegions">Kalkış Noktaları <i class="fa-solid fa-chevron-down toggle-icon"></i></a>
                <ul class="collapse mnav-sub" id="mnavRegions">
                    @foreach ($navProvinces as $province)
                        <li class="sub-title"><a href="{{ $province->url }}">{{ $province->name }}</a></li>
                        @foreach ($province->activeDistricts->take(6) as $district)
                            <li><a href="{{ url('/'.$province->slug.'/'.$district->slug) }}">{{ $district->name }}</a></li>
                        @endforeach
                    @endforeach
                    <li><a href="{{ route('regions.index') }}" class="fw-semibold">Tüm Kalkış Noktaları</a></li>
                </ul>
            </li>
            <li><a class="mnav-link" href="{{ route('gallery.index') }}">Galeri</a></li>
            <li><a class="mnav-link" href="{{ route('blog.index') }}">Blog</a></li>
            <li><a class="mnav-link" href="{{ route('about') }}">Hakkımızda</a></li>
            <li><a class="mnav-link" href="{{ route('faq') }}">Sık Sorulan Sorular</a></li>
            <li><a class="mnav-link" href="{{ route('contact') }}">İletişim</a></li>
        </ul>
        <div class="d-grid gap-2 mt-4">
            <a class="btn btn-accent" href="{{ route('reservation.create') }}"><i class="fa-regular fa-calendar-check"></i>Rezervasyon Yap</a>
            <a class="btn btn-whatsapp" href="{{ whatsapp_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan Yazın</a>
            @if (site_phone())
                <a class="btn btn-outline-brand" href="{{ phone_href() }}"><i class="fa-solid fa-phone"></i>{{ site_phone() }}</a>
            @endif
        </div>
    </div>
</div>
