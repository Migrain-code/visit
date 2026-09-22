@php
    $phone = site_phone();
    $email = setting('email');
    $hours = setting('working_hours');
    $socials = array_filter([
        'facebook' => setting('facebook_url'),
        'instagram' => setting('instagram_url'),
        'youtube' => setting('youtube_url'),
    ]);
@endphp
<div class="topbar d-none d-lg-block">
    <div class="container d-flex align-items-center">
        <ul class="topbar-list">
            @if ($phone)
                <li><i class="fa-solid fa-phone"></i><a href="{{ phone_href() }}">{{ $phone }}</a></li>
            @endif
            @if ($hours)
                <li><i class="fa-regular fa-clock"></i><span>{{ $hours }}</span></li>
            @endif
            @if ($email)
                <li><i class="fa-regular fa-envelope"></i><!--email_off--><a href="mailto:{{ $email }}">{{ $email }}</a><!--/email_off--></li>
            @endif
        </ul>
        <ul class="topbar-social ms-auto">
            @foreach ($socials as $network => $url)
                <li><a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($network) }}"><i class="fa-brands fa-{{ $network }}"></i></a></li>
            @endforeach
            <li><a class="wa-link" href="{{ whatsapp_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan yazın</a></li>
        </ul>
    </div>
</div>

<header class="site-header" id="siteHeader">
    <div class="container">
        <nav class="navbar navbar-expand-lg p-0" aria-label="Ana menü">
            <a class="brand" href="{{ route('home') }}" aria-label="{{ site_name() }} ana sayfa">
                {{-- Panelden logo yüklenmişse o kullanılır; yoksa marka dosyası. --}}
                <img src="{{ setting('logo') ? media_url(setting('logo')) : versioned_asset('images/brand/logo.svg') }}"
                     alt="{{ site_name() }}" width="203" height="52">
            </a>

            <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-controls="mobileNav" aria-label="Menüyü aç">
                <i class="fa-solid fa-bars"></i>
            </button>

            <div class="d-none d-lg-flex align-items-center flex-grow-1">
                <ul class="navbar-nav main-nav mx-auto">
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Ana Sayfa</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ request()->routeIs('tours.index', 'tours.category') ? 'active' : '' }}" href="{{ route('tours.index') }}">Turlar</a>
                        <ul class="dropdown-menu">
                            @foreach ($navCategories as $category)
                                <li><a class="dropdown-item" href="{{ $category->url }}"><i class="{{ $category->icon_class }} me-2 text-accent"></i>{{ $category->name }}</a></li>
                            @endforeach
                            @if ($navTours->isNotEmpty())
                                <li><hr class="dropdown-divider"></li>
                                @foreach ($navTours->take(5) as $tour)
                                    <li><a class="dropdown-item" href="{{ $tour->url }}">{{ $tour->title }} <small>· {{ $tour->duration_label }}</small></a></li>
                                @endforeach
                            @endif
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item fw-bold" href="{{ route('tours.index') }}">Tüm Turlar</a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('tours.calendar') ? 'active' : '' }}" href="{{ route('tours.calendar') }}">Tur Takvimi</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle {{ request()->routeIs('regions.*') ? 'active' : '' }}" href="{{ route('regions.index') }}">Kalkış Noktaları</a>
                        <div class="dropdown-menu dropdown-mega">
                            <div class="row">
                                @foreach ($navProvinces as $province)
                                    <div class="col mega-col">
                                        <h6><a href="{{ $province->url }}">{{ $province->name }} çıkışlı</a></h6>
                                        @foreach ($province->activeDistricts->take(9) as $district)
                                            <a class="district" href="{{ url('/'.$province->slug.'/'.$district->slug) }}">{{ $district->name }}</a>
                                        @endforeach
                                        @if ($province->activeDistricts->count() > 9)
                                            <a class="district fw-semibold text-accent" href="{{ $province->url }}">Tüm ilçeler →</a>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('gallery.*') ? 'active' : '' }}" href="{{ route('gallery.index') }}">Galeri</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('blog.*') ? 'active' : '' }}" href="{{ route('blog.index') }}">Blog</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('contact') ? 'active' : '' }}" href="{{ route('contact') }}">İletişim</a></li>
                </ul>
                <div class="header-cta d-flex align-items-center gap-2">
                    @if ($phone)
                        {{-- Numara düğmede yazmaz (üst barda zaten var); ekran okuyucu ve fare ipucu için etikette durur. --}}
                        <a class="btn btn-call" href="{{ phone_href() }}" aria-label="Bizi arayın: {{ $phone }}" title="{{ $phone }}">
                            <i class="fa-solid fa-phone"></i><span class="label">Ara</span>
                        </a>
                    @endif
                    <a class="btn btn-accent text-nowrap" href="{{ route('reservation.create') }}"><i class="fa-regular fa-calendar-check"></i>Rezervasyon</a>
                </div>
            </div>
        </nav>
    </div>
</header>

@include('partials.mobile-nav')
