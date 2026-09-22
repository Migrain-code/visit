@extends('layouts.app')

@section('content')
    <section class="section">
        <div class="container text-center" style="max-width: 680px;">
            <div class="thanks-icon"><i class="fa-solid fa-check"></i></div>
            <h1 class="h2 mb-3">Talebiniz bize ulaştı</h1>
            <p class="lead">En kısa sürede sizi arayıp yolcu bilgilerinizi alacak, alınış noktanızı ve saatinizi bildireceğiz.</p>
            <p class="text-muted">Bu talep tek başına koltuk ayırmaz; kaydınız bizimle görüştükten sonra kesinleşir. Acele ediyorsanız bize doğrudan ulaşabilirsiniz.</p>
            <div class="d-flex flex-wrap gap-2 justify-content-center mt-4">
                <a class="btn btn-whatsapp btn-lg" href="{{ whatsapp_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan Yazın</a>
                @if (site_phone())
                    <a class="btn btn-outline-brand btn-lg" href="{{ phone_href() }}"><i class="fa-solid fa-phone"></i>{{ site_phone() }}</a>
                @endif
                <a class="btn btn-brand btn-lg" href="{{ route('tours.index') }}">Turlara dön</a>
            </div>
        </div>
    </section>
@endsection
