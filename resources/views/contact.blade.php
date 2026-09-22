@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => 'İletişim',
        'subtitle' => 'Turlarımız hakkında bilgi ve rezervasyon için telefon, WhatsApp veya form üzerinden bize ulaşın.',
        'breadcrumbs' => [['name' => 'Ana Sayfa', 'url' => url('/')], ['name' => 'İletişim', 'url' => route('contact')]],
    ])

    <section class="section">
        <div class="container">
            <div class="row g-4 mb-5">
                @if (site_phone())
                    <div class="col-lg-3 col-md-6">
                        <div class="contact-box">
                            <span class="icon"><i class="fa-solid fa-phone"></i></span>
                            <div><h3>Telefon</h3><a href="{{ phone_href() }}">{{ site_phone() }}</a></div>
                        </div>
                    </div>
                @endif
                <div class="col-lg-3 col-md-6">
                    <div class="contact-box">
                        <span class="icon"><i class="fa-brands fa-whatsapp"></i></span>
                        <div><h3>WhatsApp</h3><a href="{{ whatsapp_url() }}" target="_blank" rel="noopener">Hemen yazın, bilgi alın</a></div>
                    </div>
                </div>
                @if (setting('email'))
                    <div class="col-lg-3 col-md-6">
                        <div class="contact-box">
                            <span class="icon"><i class="fa-regular fa-envelope"></i></span>
                            <div><h3>E-posta</h3><!--email_off--><a href="mailto:{{ setting('email') }}">{{ setting('email') }}</a><!--/email_off--></div>
                        </div>
                    </div>
                @endif
                <div class="col-lg-3 col-md-6">
                    <div class="contact-box">
                        <span class="icon"><i class="fa-regular fa-clock"></i></span>
                        <div><h3>Çalışma Saatleri</h3><p>{{ setting('working_hours') }}</p></div>
                    </div>
                </div>
            </div>

            @if ($staff->isNotEmpty())
                <div class="mb-5">
                    <x-section-title subtitle="Ekibimiz" title="Doğrudan Ulaşın"
                        text="Aşağıdaki ekip arkadaşlarımıza doğrudan yazabilir veya arayabilirsiniz." />
                    @include('partials.staff-cards')
                </div>
            @endif

            <div class="row g-4 g-lg-5">
                <div class="col-lg-7">
                    <x-section-title subtitle="Rezervasyon ve Bilgi" title="Bize Yazın" text="Formu doldurun; aynı gün içinde sizi arayalım." class="mb-4" />
                    @include('partials.reservation-form', ['formId' => 'contactReservationForm', 'source' => 'contact'])
                </div>
                <div class="col-lg-5">
                    <x-section-title subtitle="Kalkış Bölgeleri" title="Nereden Katılabilirsiniz?" class="mb-4" />
                    <p>{{ setting('service_area_text') }} il ve ilçelerinden turlarımıza katılabilirsiniz. Ofisimiz: <strong>{{ setting('address') }}</strong></p>
                    @if (setting('tursab_no'))
                        <p class="small text-muted">{{ setting('company_title') }} @if (setting('company_title')) · @endif TÜRSAB Belge No: <strong>{{ setting('tursab_no') }}</strong></p>
                    @endif
                    <a class="link-arrow d-inline-flex mb-4" href="{{ route('regions.index') }}">Tüm kalkış noktaları <i class="fa-solid fa-arrow-right"></i></a>
                    @if (setting('map_embed'))
                        <div class="map-embed">{!! setting('map_embed') !!}</div>
                    @endif
                    <div class="mt-4">
                        @include('partials.sidebar-cta')
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
