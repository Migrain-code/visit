@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => 'Sık Sorulan Sorular',
        'subtitle' => 'Rezervasyon, ödeme, iptal koşulları ve kalkış noktaları hakkında merak edilenler.',
        'breadcrumbs' => [['name' => 'Ana Sayfa', 'url' => url('/')], ['name' => 'Sık Sorulan Sorular', 'url' => route('faq')]],
    ])

    <section class="section">
        <div class="container">
            <div class="row g-4 g-lg-5">
                <div class="col-lg-8">
                    @include('partials.faq-accordion', ['faqs' => $faqs, 'accordionId' => 'faqPage', 'openFirst' => true])
                </div>
                <aside class="col-lg-4">
                    <div class="sidebar">
                        @include('partials.sidebar-cta', ['ctaTitle' => 'Sorunuzun cevabını bulamadınız mı?', 'ctaText' => 'Bize WhatsApp\'tan yazın, hemen yanıtlayalım.'])
                    </div>
                </aside>
            </div>
        </div>
    </section>
@endsection
