{{--
    İletişim formu. Koltuk AYIRMAZ: panele "İletişim Talebi" olarak düşer, personel arayıp kaydı açar.
--}}
@php
    $selTour = old('tour_departure_id', $selectedTour?->id ?? '');
    $formId = $formId ?? 'contactForm';
@endphp
<div class="form-card" id="{{ $anchor ?? 'form' }}">
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>Formda eksik veya hatalı alanlar var:</strong>
            <ul class="mb-0 mt-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('contact.store') }}" data-contact-form data-recaptcha id="{{ $formId }}" novalidate>
        @csrf
        <input type="hidden" name="page_url" value="{{ url()->current() }}">
        <div class="d-none" aria-hidden="true"><label>Web sitesi<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label required" for="{{ $formId }}-name">Ad Soyad</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="{{ $formId }}-name" name="name" value="{{ old('name') }}" required autocomplete="name">
            </div>
            <div class="col-md-6">
                <label class="form-label required" for="{{ $formId }}-phone">Telefon</label>
                <input type="tel" class="form-control @error('phone') is-invalid @enderror" id="{{ $formId }}-phone" name="phone" value="{{ old('phone') }}" placeholder="05xx xxx xx xx" required autocomplete="tel" inputmode="tel">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="{{ $formId }}-email">E-posta</label>
                <input type="email" class="form-control @error('email') is-invalid @enderror" id="{{ $formId }}-email" name="email" value="{{ old('email') }}" autocomplete="email" inputmode="email">
            </div>
            <div class="col-md-8">
                <label class="form-label" for="{{ $formId }}-tour">Tur</label>
                <select class="form-select @error('tour_departure_id') is-invalid @enderror" id="{{ $formId }}-tour" name="tour_departure_id">
                    <option value="">Genel bilgi almak istiyorum</option>
                    @foreach ($formTours as $tour)
                        @continue($tour->is_full)
                        <option value="{{ $tour->id }}" @selected((string) $selTour === (string) $tour->id)>{{ $tour->title }} · {{ $tour->short_date_label }}@if ($tour->price_label) · {{ $tour->price_label }}@endif</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label required" for="{{ $formId }}-people">Kişi sayısı</label>
                <input type="number" class="form-control @error('people_count') is-invalid @enderror" id="{{ $formId }}-people" name="people_count" value="{{ old('people_count', 1) }}" min="1" max="60" inputmode="numeric" required>
            </div>
            <div class="col-12">
                <label class="form-label" for="{{ $formId }}-message">Mesajınız</label>
                <textarea class="form-control @error('message') is-invalid @enderror" id="{{ $formId }}-message" name="message" rows="3" placeholder="Örn: 3 arkadaş katılmak istiyoruz, nereden bineceğiz?">{{ old('message') }}</textarea>
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input @error('kvkk') is-invalid @enderror" type="checkbox" name="kvkk" value="1" id="{{ $formId }}-kvkk" @checked(old('kvkk')) required>
                    <label class="form-check-label small" for="{{ $formId }}-kvkk">
                        Kişisel verilerimin talebime dönüş yapılması amacıyla işlenmesini kabul ediyorum.
                    </label>
                </div>
            </div>
            <div class="col-12">
                @include('partials.recaptcha')
            </div>
            <div class="col-12 d-grid d-md-flex gap-2 pt-1">
                <button type="submit" class="btn btn-sun btn-lg"><i class="fa-solid fa-paper-plane"></i>Gönder</button>
                @if (whatsapp_number())
                    <a class="btn btn-whatsapp btn-lg" href="{{ whatsapp_url($selectedTour?->title) }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan Yaz</a>
                @endif
            </div>
        </div>
    </form>
</div>
