@extends('layouts.app', ['metaTitle' => 'Sayfa Bulunamadı', 'robots' => 'noindex, follow'])

@section('content')
    <section class="section">
        <div class="container text-center" style="max-width:720px">
            <div class="error-code">404</div>
            <h1 class="h2 mb-3">Aradığınız sayfa bulunamadı</h1>
            <p class="lead text-muted mb-4">Sayfa taşınmış veya kaldırılmış olabilir. Ana sayfadan devam edebilir veya doğrudan bize ulaşabilirsiniz.</p>
            <div class="d-flex flex-wrap justify-content-center gap-3">
                <a class="btn btn-accent btn-lg" href="{{ route('home') }}">Ana Sayfa</a>
                <a class="btn btn-brand btn-lg" href="{{ route('tours.index') }}">Turlar</a>
                <a class="btn btn-whatsapp btn-lg" href="{{ whatsapp_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan Yazın</a>
            </div>
        </div>
    </section>
@endsection
