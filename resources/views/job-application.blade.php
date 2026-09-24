@extends('layouts.app')

@section('content')
    <div class="container app-shell">
        <section class="page-head">
            <h1>İş Başvurusu</h1>
            <p>Ekibimizde yer almak ister misin? Rehberlik, şoförlük, organizasyon ya da sosyal medya için başvurunu bırak; sana dönelim.</p>
        </section>

        <section class="section-block">
            <div class="form-card">
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

                <form method="POST" action="{{ route('jobs.store') }}" enctype="multipart/form-data" data-recaptcha id="jobForm" novalidate>
                    @csrf
                    <div class="d-none" aria-hidden="true"><label>Web sitesi<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label required" for="job-name">Ad Soyad</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" id="job-name" name="name" value="{{ old('name') }}" required autocomplete="name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label required" for="job-phone">Telefon</label>
                            <input type="tel" class="form-control @error('phone') is-invalid @enderror" id="job-phone" name="phone" value="{{ old('phone') }}" placeholder="05xx xxx xx xx" required autocomplete="tel" inputmode="tel">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="job-email">E-posta</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" id="job-email" name="email" value="{{ old('email') }}" autocomplete="email" inputmode="email">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="job-birth">Doğum tarihi</label>
                            <input type="date" class="form-control @error('birth_date') is-invalid @enderror" id="job-birth" name="birth_date" value="{{ old('birth_date') }}" max="{{ now()->subYears(16)->toDateString() }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="job-position">Başvurulan görev</label>
                            <select class="form-select @error('position') is-invalid @enderror" id="job-position" name="position">
                                <option value="">Seçin</option>
                                @foreach ($positions as $position)
                                    <option value="{{ $position }}" @selected(old('position') === $position)>{{ $position }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="job-message">Kendinden bahset</label>
                            <textarea class="form-control @error('message') is-invalid @enderror" id="job-message" name="message" rows="4" placeholder="Bölümün, deneyimlerin, hangi günler müsait olduğun...">{{ old('message') }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="job-cv">Özgeçmiş (isteğe bağlı)</label>
                            <input type="file" class="form-control @error('cv') is-invalid @enderror" id="job-cv" name="cv" accept=".pdf,.doc,.docx">
                            <div class="form-text">PDF ya da Word, en fazla 5 MB.</div>
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input @error('kvkk') is-invalid @enderror" type="checkbox" name="kvkk" value="1" id="job-kvkk" @checked(old('kvkk')) required>
                                <label class="form-check-label small" for="job-kvkk">
                                    Kişisel verilerimin başvurumun değerlendirilmesi amacıyla işlenmesini kabul ediyorum.
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            @include('partials.recaptcha')
                        </div>
                        <div class="col-12 d-grid pt-1">
                            <button type="submit" class="btn btn-sun btn-lg"><i class="fa-solid fa-paper-plane"></i>Başvuruyu Gönder</button>
                        </div>
                    </div>
                </form>
            </div>
        </section>
    </div>
@endsection
