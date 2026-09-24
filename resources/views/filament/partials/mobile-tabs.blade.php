{{--
    Mobil alt sekme çubuğu: telefonda en sık kullanılan ekranlar tek dokunuşla.
    Yalnız giriş yapmış kullanıcıya ve yalnız dar ekranda görünür (theme.css).
--}}
@php
    $user = auth()->user();
@endphp
@if ($user)
    @php
        $tabs = [
            ['Panel', url('/admin'), 'heroicon-o-home', request()->is('admin')],
            ['Turlar', \App\Filament\Resources\TourDepartures\TourDepartureResource::getUrl('index'), 'heroicon-o-map', request()->is('admin/turlar*')],
        ];

        if ($user->registersGroups()) {
            $tabs[] = ['Yolcu Ekle', \App\Filament\Resources\TourGroups\TourGroupResource::getUrl('create'), 'heroicon-o-user-plus', request()->is('admin/gruplar/create*')];
        }

        if ($user->managesOperations()) {
            $tabs[] = ['Sihirbaz', \App\Filament\Pages\VehicleWizard::getUrl(), 'heroicon-o-sparkles', request()->is('admin/arac-sihirbazi*')];
        } elseif ($user->managesRequests()) {
            $tabs[] = ['Talepler', \App\Filament\Resources\ReservationRequests\ReservationRequestResource::getUrl('index'), 'heroicon-o-inbox-arrow-down', request()->is('admin/iletisim-talepleri*')];
        }

        $tabs[] = $user->viewsReports()
            ? ['Kasa', \App\Filament\Pages\CashReport::getUrl(), 'heroicon-o-calculator', request()->is('admin/kasa*')]
            : ['Kazancım', \App\Filament\Pages\MyEarnings::getUrl(), 'heroicon-o-wallet', request()->is('admin/kazanclarim*')];
    @endphp
    <nav class="rg-tabs" aria-label="Hızlı erişim">
        @foreach ($tabs as [$label, $url, $icon, $active])
            <a href="{{ $url }}" class="rg-tab {{ $active ? 'is-active' : '' }}" @if ($active) aria-current="page" @endif>
                <x-filament::icon :icon="$icon" class="rg-tab-icon" />
                <span>{{ $label }}</span>
            </a>
        @endforeach
    </nav>
@endif
