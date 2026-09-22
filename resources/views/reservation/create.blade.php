@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => 'Rezervasyon',
        'subtitle' => 'Katılmak istediğiniz turu ve kişi sayınızı bildirin; aynı gün içinde sizi arayıp yerinizi ayıralım.',
        'breadcrumbs' => [
            ['name' => 'Ana Sayfa', 'url' => url('/')],
            ['name' => 'Rezervasyon', 'url' => route('reservation.create')],
        ],
    ])

    <section class="section">
        <div class="container">
            <div class="row g-4 g-lg-5">
                <div class="col-lg-8">
                    @include('partials.reservation-form', [
                        'formTitle' => $selectedTour ? $selectedTour->title.' için rezervasyon talebi' : 'Rezervasyon talebi',
                        'formText' => 'Yıldızlı alanlar zorunludur. Yolcu kimlik bilgilerini bu aşamada istemiyoruz; sizi aradığımızda alacağız.',
                        'selectedTour' => $selectedTour?->getKey(),
                        'selectedDeparture' => $selectedDeparture?->getKey(),
                    ])
                </div>
                <aside class="col-lg-4">
                    <div class="sidebar">
                        <div class="sidebar-widget">
                            <h3>Sonra ne olacak?</h3>
                            <ol class="itinerary mb-0">
                                <li class="itinerary-day"><h3>Sizi arıyoruz</h3><p class="small">Talebiniz bize ulaşınca aynı gün içinde dönüş yapıyoruz.</p></li>
                                <li class="itinerary-day"><h3>Kaydınızı tamamlıyoruz</h3><p class="small">Her yolcunun ad, soyad, T.C. kimlik no, yaş ve cinsiyet bilgisini alıyoruz.</p></li>
                                <li class="itinerary-day"><h3>Alınış saatinizi bildiriyoruz</h3><p class="small">Otelinizden ya da size en yakın noktadan alınış saati netleşir. Grubunuz aynı araçta yolculuk eder.</p></li>
                            </ol>
                        </div>
                        @include('partials.sidebar-cta', ['ctaTitle' => 'Aramayı mı tercih edersiniz?', 'ctaText' => 'Telefon ya da WhatsApp üzerinden de kayıt olabilirsiniz.'])
                    </div>
                </aside>
            </div>
        </div>
    </section>
@endsection
