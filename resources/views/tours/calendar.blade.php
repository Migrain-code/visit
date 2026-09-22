@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => 'Tur Takvimi',
        'subtitle' => 'Kayıtları devam eden tüm turlar, tarih sırasıyla. Kontenjan ve fiyat bilgisi günceldir.',
        'breadcrumbs' => $breadcrumbs,
    ])

    <section class="section">
        <div class="container">
            @forelse ($months as $month => $departures)
                @php $first = $departures->first()->starts_at; @endphp
                <div class="calendar-month">
                    <h2>{{ $first->translatedFormat('F Y') }} <small>{{ $departures->count() }} tur</small></h2>
                    <div class="departure-list">
                        @foreach ($departures as $departure)
                            @include('partials.departure-row', ['departure' => $departure])
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="empty-state">
                    <i class="fa-regular fa-calendar-xmark"></i>
                    <h2 class="h4">Şu anda kayda açık tarih yok</h2>
                    <p class="mb-3">Yeni tarihler yakında açıklanacak. Dilerseniz bize yazın; tarih açıklandığında size haber verelim.</p>
                    <a class="btn btn-whatsapp" href="{{ whatsapp_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan yazın</a>
                </div>
            @endforelse

            <p class="text-muted small mt-4 mb-0">
                <i class="fa-solid fa-circle-info me-1"></i>
                "Yer ayırt" bağlantısı bir ön talep formu açar. Kaydınız, sizi arayıp yolcu bilgilerinizi aldıktan sonra kesinleşir.
            </p>
        </div>
    </section>

    @include('partials.cta-band')
@endsection
