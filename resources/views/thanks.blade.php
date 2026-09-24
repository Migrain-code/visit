@extends('layouts.app')

@section('content')
    <div class="container app-shell">
        <section class="thanks">
            <div class="thanks-icon"><i class="fa-solid fa-check"></i></div>
            <h1>{{ $heading }}</h1>
            <p>{{ $text }}</p>
            <div class="d-grid d-sm-flex justify-content-center gap-2">
                <a class="btn btn-sun" href="{{ route('home') }}"><i class="fa-solid fa-house"></i>Ana Sayfa</a>
                @if (whatsapp_number())
                    <a class="btn btn-whatsapp" href="{{ whatsapp_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan Yaz</a>
                @endif
            </div>
        </section>
    </div>
@endsection
