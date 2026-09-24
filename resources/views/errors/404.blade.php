@extends('layouts.app', ['metaTitle' => 'Sayfa Bulunamadı', 'robots' => 'noindex, follow'])

@section('content')
    <div class="container app-shell">
        <section class="thanks">
            <div class="error-code">404</div>
            <h1>Aradığınız sayfa bulunamadı</h1>
            <p>Sayfa taşınmış veya kaldırılmış olabilir. Ana sayfadan devam edebilir veya doğrudan bize ulaşabilirsiniz.</p>
            <div class="d-grid d-sm-flex justify-content-center gap-2">
                <a class="btn btn-sun" href="{{ route('home') }}"><i class="fa-solid fa-house"></i>Ana Sayfa</a>
                <a class="btn btn-outline-navy" href="{{ route('contact') }}"><i class="fa-regular fa-envelope"></i>İletişim</a>
            </div>
        </section>
    </div>
@endsection
