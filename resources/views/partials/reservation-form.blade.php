{{--
    Rezervasyon talep formu. Koltuk AYIRMAZ: bir ön taleptir, personel arayıp kaydı açar.
    Tur seçilince o turun satıştaki tarihleri listelenir (veri sayfaya JSON olarak gömülür).
--}}
@php
    $districtMap = $formProvinces->mapWithKeys(fn ($p) => [$p->id => $p->activeDistricts->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->values()])->all();

    $departureMap = \App\Models\TourDeparture::query()->bookable()->withSeatStats()->with('tour:id,price,currency')->orderBy('starts_at')->get()
        ->reject(fn ($d) => $d->is_full)
        ->groupBy('tour_id')
        ->map(fn ($list) => $list->map(fn ($d) => [
            'id' => $d->id,
            'label' => $d->date_range_label.($d->price_label ? ' · '.$d->price_label : ''),
        ])->values())
        ->all();

    $selProvince = old('province_id', $selectedProvince ?? '');
    $selDistrict = old('district_id', $selectedDistrict ?? '');
    $selTour = old('tour_id', $selectedTour ?? '');
    $selDeparture = old('tour_departure_id', $selectedDeparture ?? '');
    $formId = $formId ?? 'reservationForm';
@endphp
<div class="form-card" id="{{ $anchor ?? 'rezervasyon' }}">
    @if (! empty($formTitle))
        <h3>{{ $formTitle }}</h3>
    @endif
    @if (! empty($formText))
        <p class="text-muted mb-4">{{ $formText }}</p>
    @endif

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

    <form method="POST" action="{{ route('reservation.store') }}" data-reservation-form data-recaptcha id="{{ $formId }}" novalidate>
        @csrf
        <input type="hidden" name="source" value="{{ $source ?? 'form' }}">
        <input type="hidden" name="page_url" value="{{ url()->current() }}">
        <div class="d-none" aria-hidden="true"><label>Web sitesi<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
        <script type="application/json" data-districts>{!! json_encode($districtMap, JSON_UNESCAPED_UNICODE) !!}</script>
        <script type="application/json" data-departures>{!! json_encode($departureMap, JSON_UNESCAPED_UNICODE) !!}</script>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label required" for="{{ $formId }}-name">Ad Soyad</label>
                <input type="text" class="form-control @error('name') is-invalid @enderror" id="{{ $formId }}-name" name="name" value="{{ old('name') }}" required autocomplete="name">
            </div>
            <div class="col-md-6">
                <label class="form-label required" for="{{ $formId }}-phone">Telefon</label>
                <input type="tel" class="form-control @error('phone') is-invalid @enderror" id="{{ $formId }}-phone" name="phone" value="{{ old('phone') }}" placeholder="05xx xxx xx xx" required autocomplete="tel">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="{{ $formId }}-tour">Tur</label>
                <select class="form-select @error('tour_id') is-invalid @enderror" id="{{ $formId }}-tour" name="tour_id">
                    <option value="">Tur seçin</option>
                    @foreach ($formTours as $tour)
                        <option value="{{ $tour->id }}" @selected((string) $selTour === (string) $tour->id)>{{ $tour->title }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="{{ $formId }}-departure">Tarih</label>
                <select class="form-select @error('tour_departure_id') is-invalid @enderror" id="{{ $formId }}-departure" name="tour_departure_id" data-selected="{{ $selDeparture }}">
                    <option value="">Önce tur seçin</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label required" for="{{ $formId }}-people">Kişi sayısı</label>
                <input type="number" class="form-control @error('people_count') is-invalid @enderror" id="{{ $formId }}-people" name="people_count" value="{{ old('people_count', 2) }}" min="1" max="60" inputmode="numeric" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="{{ $formId }}-province">Konakladığınız il</label>
                <select class="form-select @error('province_id') is-invalid @enderror" id="{{ $formId }}-province" name="province_id">
                    <option value="">İl seçin</option>
                    @foreach ($formProvinces as $province)
                        <option value="{{ $province->id }}" @selected((string) $selProvince === (string) $province->id)>{{ $province->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="{{ $formId }}-district">İlçe</label>
                <select class="form-select @error('district_id') is-invalid @enderror" id="{{ $formId }}-district" name="district_id" data-selected="{{ $selDistrict }}">
                    <option value="">Önce il seçin</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="{{ $formId }}-message">Notunuz</label>
                <textarea class="form-control @error('message') is-invalid @enderror" id="{{ $formId }}-message" name="message" rows="3" placeholder="Örn: 2 yetişkin, 1 çocuk (7 yaş). Ardeşen'de otelde kalıyoruz.">{{ old('message') }}</textarea>
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input @error('kvkk') is-invalid @enderror" type="checkbox" name="kvkk" value="1" id="{{ $formId }}-kvkk" @checked(old('kvkk')) required>
                    <label class="form-check-label small" for="{{ $formId }}-kvkk">
                        @if ($kvkkPage)
                            <a href="{{ $kvkkPage->url }}" target="_blank">{{ $kvkkPage->title }}</a>'ni okudum, kişisel verilerimin talebimin değerlendirilmesi amacıyla işlenmesini kabul ediyorum.
                        @else
                            Kişisel verilerimin talebimin değerlendirilmesi amacıyla işlenmesini kabul ediyorum.
                        @endif
                    </label>
                </div>
            </div>
            <div class="col-12">
                @include('partials.recaptcha')
            </div>
            <div class="col-12 d-grid d-md-flex gap-2 pt-1">
                <button type="submit" class="btn btn-accent btn-lg"><i class="fa-solid fa-paper-plane"></i>Talebi Gönder</button>
                <a class="btn btn-whatsapp btn-lg" href="{{ $whatsappUrl ?? whatsapp_url() }}" target="_blank" rel="noopener"><i class="fa-brands fa-whatsapp"></i>WhatsApp'tan Yazın</a>
            </div>
            <div class="col-12">
                <p class="form-text mb-0">
                    Bu form bir ön taleptir ve tek başına koltuk ayırmaz. Sizi arayıp yolcu bilgilerinizi aldıktan sonra kaydınız kesinleşir.
                </p>
            </div>
        </div>
    </form>
</div>
