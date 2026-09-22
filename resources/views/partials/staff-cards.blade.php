@php
    $staff = $staff ?? collect();
    $compact = $compact ?? false;
@endphp

@if ($staff->isNotEmpty())
    <div class="row g-4">
        @foreach ($staff as $person)
            @php
                $wa = $person->whatsapp_number;
                $tel = $person->phone ? phone_href($person->phone) : null;
            @endphp
            <div class="{{ $compact ? 'col-lg-3 col-md-6' : 'col-lg-4 col-md-6' }} reveal">
                <div class="staff-card">
                    <div class="avatar">
                        @if ($person->photo_url)
                            <img src="{{ $person->photo_url }}" alt="{{ $person->name }} — {{ $person->title ?: site_name().' ekibi' }}" loading="lazy" width="92" height="92">
                        @else
                            {{ $person->initials }}
                        @endif
                    </div>
                    <h3>{{ $person->name }}</h3>
                    @if ($person->title)
                        <div class="title">{{ $person->title }}</div>
                    @endif
                    @if ($person->bio && ! $compact)
                        <p class="bio">{{ $person->bio }}</p>
                    @endif
                    <div class="actions">
                        @if ($wa)
                            <a class="btn btn-whatsapp btn-sm" href="https://wa.me/{{ $wa }}?text={{ rawurlencode('Merhaba, turlarınız hakkında bilgi almak istiyorum.') }}" target="_blank" rel="noopener">
                                <i class="fa-brands fa-whatsapp"></i>WhatsApp
                            </a>
                        @endif
                        @if ($tel)
                            <a class="btn btn-outline-brand btn-sm" href="{{ $tel }}"><i class="fa-solid fa-phone"></i>{{ $person->phone }}</a>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
