@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => 'Kalkış Noktaları',
        'subtitle' => 'Turlarımıza katılabileceğiniz il ve ilçeler. Size en yakın bölgeyi seçin; biniş noktası ve kalkış bilgilerini görün.',
        'breadcrumbs' => [
            ['name' => 'Ana Sayfa', 'url' => url('/')],
            ['name' => 'Kalkış Noktaları', 'url' => route('regions.index')],
        ],
    ])

    <section class="section">
        <div class="container">
            <div class="row g-4">
                @foreach ($provinces as $province)
                    <div class="col-lg-4 reveal">
                        @include('partials.region-card', ['province' => $province])
                    </div>
                @endforeach
            </div>

            <div class="row g-4 mt-4">
                <div class="col-lg-8">
                    <div class="content-prose">
                        <h2>Otelimden alınıyor muyum?</h2>
                        <p>Evet. Her turun sahil yolu üzerinde bir güzergâhı vardır ve araç, bu güzergâh üzerindeki otellerden ve biniş noktalarından misafir alarak ilerler. Rezervasyon sırasında konakladığınız yeri sorar, alınış noktanızı ve saatinizi bildiririz.</p>
                        <p>Güzergâha uzak ilçelerden ve iç kesimlerden katılım için ulaşımı birlikte planlarız. Kesin alınış saati, tur gününden bir gün önce telefon ya da WhatsApp ile tekrar hatırlatılır.</p>
                    </div>
                </div>
                <div class="col-lg-4">
                    @include('partials.sidebar-cta')
                </div>
            </div>
        </div>
    </section>

    @include('partials.cta-band')
@endsection
