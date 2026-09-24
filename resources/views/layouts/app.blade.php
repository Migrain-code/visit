<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @php
        $pageTitle = seo_title($metaTitle ?? null);
        $pageDescription = $metaDescription ?? setting('meta_description');
        $pageCanonical = $canonical ?? url()->current();
        $pageImage = $ogImage ?? media_url(setting('hero_image'), asset('images/placeholder.svg'));
    @endphp
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(strip_tags((string) $pageDescription), 300, '') }}">
    <meta name="robots" content="{{ $robots ?? 'index, follow' }}">
    <link rel="canonical" href="{{ $pageCanonical }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ site_name() }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags((string) $pageDescription), 300, '') }}">
    <meta property="og:url" content="{{ $pageCanonical }}">
    <meta property="og:image" content="{{ $pageImage }}">
    <meta property="og:locale" content="tr_TR">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="#0d2544">
    @if (setting('google_site_verification'))
        <meta name="google-site-verification" content="{{ setting('google_site_verification') }}">
    @endif
    <link rel="icon" href="{{ versioned_asset('images/brand/logo-mark.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    {{-- Yazı tipleri app.scss içinde, kendi sunucumuzdan: Google Fonts bağlantısı yok. --}}
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    {{--
        İkonlar (Font Awesome) sayfanın ilk çizimini BEKLETMEZ: dosya "print" olarak iner
        (tarayıcı bunu beklemeden sayfayı çizer), yüklenince tüm ekranlara uygulanır.
    --}}
    <link rel="stylesheet" href="{{ \Illuminate\Support\Facades\Vite::asset('resources/scss/icons.scss') }}" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="{{ \Illuminate\Support\Facades\Vite::asset('resources/scss/icons.scss') }}"></noscript>
    <script type="application/ld+json">{!! json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => site_name(),
        'url' => url('/'),
        'telephone' => site_phone() ?: null,
        'sameAs' => array_values(array_filter([instagram_url()])),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @if ($gaId = setting('google_analytics_id'))
        {{--
            Google Analytics ERTELENİR. gtag.js ~167 KB'tır ve açılışta ~230 ms işlemci harcar
            (PageSpeed: TBT). Komutlar hemen kuyruğa yazılır; betik ziyaretçinin ilk
            etkileşiminde (kaydırma, dokunma, tuş) ya da sayfa yüklendikten 3,5 sn sonra iner
            ve kuyruktaki sayfa görüntülemeyi gönderir.
        --}}
        <script>
            window.dataLayer = window.dataLayer || [];function gtag(){dataLayer.push(arguments);}gtag('js', new Date());gtag('config', @json($gaId));
            (function () {
                var loaded = false;
                function load() {
                    if (loaded) return; loaded = true;
                    var s = document.createElement('script'); s.async = true;
                    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(@json($gaId));
                    document.head.appendChild(s);
                }
                ['pointerdown', 'keydown', 'touchstart', 'scroll'].forEach(function (e) { window.addEventListener(e, load, { once: true, passive: true }); });
                window.addEventListener('load', function () { setTimeout(load, 3500); });
            })();
        </script>
    @endif
    {{--
        E-postalar <!--email_off--> ile sarılır: Cloudflare aksi hâlde onları gizleyip
        sayfaya kritik yolda duran ek bir betik (email-decode.min.js) ekler.
    --}}
    @stack('head')
</head>
<body class="{{ $bodyClass ?? '' }}">
    @include('partials.header')

    <main id="main" class="app-main">
        @yield('content')
    </main>

    @include('partials.footer')
    @include('partials.floating-cta')
    @stack('scripts')
</body>
</html>
