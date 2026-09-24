@php
    $phone = site_phone();
    $name = site_name();
    // "RTEÜ Geziyor" → "RTEÜ" + "GEZİYOR": son kelime sarı ve büyük harf.
    $words = preg_split('/\s+/u', trim($name)) ?: [$name];
    $accentWord = count($words) > 1 ? array_pop($words) : null;
    $baseWord = implode(' ', $words);
@endphp
<header class="site-header" id="siteHeader">
    <div class="container app-shell">
        <nav class="header-bar" aria-label="Ana menü">
            <a class="brand" href="{{ route('home') }}" aria-label="{{ $name }} ana sayfa">
                @if (setting('logo'))
                    <img class="brand-logo" src="{{ media_url(setting('logo')) }}" alt="{{ $name }}" width="180" height="56">
                @else
                    <img class="brand-mark" src="{{ versioned_asset('images/brand/logo-mark.svg') }}" alt="" width="52" height="52">
                    <span class="brand-text">
                        <span class="brand-name">{{ \App\Support\TurkishText::upper($baseWord) }}@if ($accentWord)<em>{{ \App\Support\TurkishText::upper($accentWord) }}</em>@endif</span>
                        <span class="brand-tagline">{{ setting('site_tagline', 'Keşfet · Tanış · Yaşa') }}</span>
                    </span>
                @endif
            </a>

            <ul class="main-nav d-none d-lg-flex">
                <li><a class="{{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Ana Sayfa</a></li>
                <li><a href="{{ route('home') }}#turlar">Turlar</a></li>
                <li><a class="{{ request()->routeIs('contact*') ? 'active' : '' }}" href="{{ route('contact') }}">İletişim</a></li>
                <li><a class="{{ request()->routeIs('jobs.*') ? 'active' : '' }}" href="{{ route('jobs.create') }}">İş Başvurusu</a></li>
            </ul>

            <div class="header-actions">
                @if ($phone)
                    <a class="btn btn-call d-none d-lg-inline-flex" href="{{ phone_href() }}" aria-label="Bizi arayın: {{ $phone }}" title="{{ $phone }}">
                        <i class="fa-solid fa-phone"></i><span class="label">Ara</span>
                    </a>
                @endif
                @if (instagram_url())
                    <a class="icon-btn d-none d-lg-inline-flex" href="{{ instagram_url() }}" target="_blank" rel="noopener" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                @endif
                <button class="icon-btn menu-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-controls="mobileNav" aria-label="Menüyü aç">
                    <i class="fa-solid fa-bars"></i>
                </button>
            </div>
        </nav>
    </div>
</header>

@include('partials.mobile-nav')
