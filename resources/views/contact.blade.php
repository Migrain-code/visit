@extends('layouts.app')

@section('content')
    <div class="container app-shell">
        <section class="page-head">
            <h1>İletişim</h1>
            <p>Turlara katılmak, bilgi almak ya da soru sormak için bize yazın. Aynı gün içinde dönüş yapıyoruz.</p>
        </section>

        <section class="contact-grid">
            @if (site_phone())
                <a class="contact-tile" href="{{ phone_href() }}">
                    <span class="tile-icon"><i class="fa-solid fa-phone"></i></span>
                    <span><strong>Ara</strong><small>{{ site_phone() }}</small></span>
                </a>
            @endif
            @if (whatsapp_number())
                <a class="contact-tile" href="{{ whatsapp_url() }}" target="_blank" rel="noopener">
                    <span class="tile-icon is-wa"><i class="fa-brands fa-whatsapp"></i></span>
                    <span><strong>WhatsApp</strong><small>Hemen yaz</small></span>
                </a>
            @endif
            @if (instagram_url())
                <a class="contact-tile" href="{{ instagram_url() }}" target="_blank" rel="noopener">
                    <span class="tile-icon is-ig"><i class="fa-brands fa-instagram"></i></span>
                    <span><strong>Instagram</strong><small>{{ instagram_handle() }}</small></span>
                </a>
            @endif
            @if (setting('email'))
                <a class="contact-tile" href="mailto:{{ setting('email') }}">
                    <span class="tile-icon"><i class="fa-regular fa-envelope"></i></span>
                    <span><strong>E-posta</strong><!--email_off--><small>{{ setting('email') }}</small><!--/email_off--></span>
                </a>
            @endif
        </section>

        <section class="section-block">
            <div class="section-head">
                <h2><i class="fa-regular fa-paper-plane"></i>Bize Yazın</h2>
            </div>
            @include('partials.contact-form', ['selectedTour' => $selectedTour ?? null])
        </section>

        @if (setting('address') || setting('map_embed'))
            <section class="section-block">
                <div class="section-head">
                    <h2><i class="fa-solid fa-location-dot"></i>Neredeyiz?</h2>
                </div>
                @if (setting('address'))
                    <p class="mb-3">{{ setting('address') }}</p>
                @endif
                @if (setting('map_embed'))
                    <div class="map-embed">{!! setting('map_embed') !!}</div>
                @endif
            </section>
        @endif
    </div>
@endsection
