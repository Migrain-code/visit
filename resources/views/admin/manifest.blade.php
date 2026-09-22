{{--
    Yolcu listesi (manifesto) — yazdırmak için. Kendi başına, sade bir sayfadır:
    site teması ve panel betikleri yüklenmez; kâğıtta temiz çıkar.
    KİŞİSEL VERİ içerir: arama motorlarına kapalıdır ve önbelleğe alınmaz.
--}}
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Yolcu Listesi · {{ $departure->label }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; color: #0c2340; margin: 0; padding: 24px; font-size: 13px; line-height: 1.45; background: #eef6fd; }
        .sheet { max-width: 1000px; margin: 0 auto; background: #fff; padding: 28px 32px; border-radius: 10px; }
        header { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; border-bottom: 2px solid #0a3d91; padding-bottom: 14px; margin-bottom: 18px; }
        h1 { font-size: 20px; margin: 0 0 4px; }
        h2 { font-size: 15px; margin: 0; }
        .muted { color: #4a5d70; }
        .meta { text-align: right; font-size: 12px; }
        .summary { display: flex; flex-wrap: wrap; gap: 8px 24px; margin-bottom: 20px; font-size: 12.5px; }
        .vehicle { margin-bottom: 26px; page-break-inside: avoid; }
        .vehicle-head { display: flex; justify-content: space-between; align-items: baseline; gap: 16px; background: #0a3d91; color: #fff; padding: 9px 12px; border-radius: 6px 6px 0 0; }
        .vehicle-head .muted { color: rgba(255,255,255,.78); }
        .vehicle.is-waiting .vehicle-head { background: #b45309; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cfdcea; padding: 5px 8px; text-align: left; vertical-align: top; }
        th { background: #eef6fd; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        td.num, th.num { text-align: center; width: 34px; }
        tr.group-start td { border-top: 2px solid #0d7de0; }
        .group-cell { background: #f5faff; font-weight: 600; width: 150px; }
        .group-cell small { display: block; font-weight: 400; color: #4a5d70; }
        .sign { width: 70px; }
        .toolbar { max-width: 1000px; margin: 0 auto 14px; display: flex; gap: 8px; justify-content: flex-end; }
        .toolbar button, .toolbar a { border: 0; background: #0d7de0; color: #fff; padding: 9px 18px; border-radius: 999px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 13px; }
        .toolbar a { background: #fff; color: #0c2340; border: 1px solid #cfdcea; }
        .notice { font-size: 11px; color: #4a5d70; margin-top: 18px; border-top: 1px solid #dfe9f3; padding-top: 10px; }
        @media print {
            body { background: #fff; padding: 0; font-size: 11.5px; }
            .sheet { padding: 0; border-radius: 0; max-width: none; }
            .toolbar { display: none; }
            .vehicle-head { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            @page { margin: 12mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ \App\Filament\Resources\TourDepartures\TourDepartureResource::getUrl('allocation', ['record' => $departure]) }}">← Araç dağılımına dön</a>
        <button type="button" onclick="window.print()">Yazdır / PDF kaydet</button>
    </div>

    <div class="sheet">
        <header>
            <div>
                <h1>{{ $departure->tour?->title }}</h1>
                <div class="muted">
                    Yolcu listesi · {{ $departure->starts_at->translatedFormat('j F Y l, H:i') }}
                    @if ($departure->ends_on && ! $departure->ends_on->isSameDay($departure->starts_at))
                        – {{ $departure->ends_on->translatedFormat('j F Y') }}
                    @endif
                </div>
            </div>
            <div class="meta">
                <strong>{{ site_name() }}</strong><br>
                Sefer kodu: {{ $departure->code }}<br>
                @if ($departure->guide) Rehber: {{ $departure->guide->name }} @if ($departure->guide->phone) · {{ $departure->guide->phone }} @endif<br> @endif
                Döküm: {{ now()->format('d.m.Y H:i') }}
            </div>
        </header>

        @php
            $totalPassengers = $vehicles->sum(fn ($v) => $v->groups->sum('passenger_count')) + $waiting->sum('passenger_count');
        @endphp
        <div class="summary">
            <span><strong>{{ $vehicles->count() }}</strong> araç</span>
            <span><strong>{{ $totalPassengers }}</strong> yolcu</span>
            <span><strong>{{ $vehicles->sum(fn ($v) => $v->groups->count()) + $waiting->count() }}</strong> grup</span>
            @if ($departure->meeting_point)<span>Buluşma: <strong>{{ $departure->meeting_point }}</strong></span>@endif
            @if ($waiting->isNotEmpty())<span style="color:#b45309"><strong>{{ $waiting->sum('passenger_count') }}</strong> yolcu henüz araca yerleşmedi</span>@endif
        </div>

        @foreach ($vehicles as $index => $vehicle)
            <section class="vehicle">
                <div class="vehicle-head">
                    <h2>{{ $index + 1 }}. Araç · {{ $vehicle->name }} @if ($vehicle->plate) · {{ $vehicle->plate }} @endif</h2>
                    <span class="muted">
                        {{ $vehicle->groups->sum('passenger_count') }} / {{ $vehicle->usable_seats }} yolcu
                        @if ($vehicle->driver_name) · Şoför: {{ $vehicle->driver_name }} @if ($vehicle->driver_phone) ({{ $vehicle->driver_phone }}) @endif @endif
                    </span>
                </div>
                @include('admin.partials.manifest-table', ['groups' => $vehicle->groups])
            </section>
        @endforeach

        @if ($waiting->isNotEmpty())
            <section class="vehicle is-waiting">
                <div class="vehicle-head">
                    <h2>Henüz araca yerleşmeyen gruplar</h2>
                    <span class="muted">{{ $waiting->sum('passenger_count') }} yolcu</span>
                </div>
                @include('admin.partials.manifest-table', ['groups' => $waiting])
            </section>
        @endif

        <p class="notice">
            Bu liste kişisel veri içerir (6698 sayılı KVKK). Yalnız tur operasyonu için kullanılmalı, tur bitiminde imha edilmeli ve üçüncü kişilerle paylaşılmamalıdır.
            Her grup tek bir araçta, birlikte yolculuk eder.
        </p>
    </div>
</body>
</html>
