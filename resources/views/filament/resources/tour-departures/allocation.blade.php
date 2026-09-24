<x-filament-panels::page>
    @php
        $waitingCount = (int) $waiting->sum('passenger_count');
        $overbooked = $capacity > 0 && $registered > $capacity;
    @endphp

    <div class="space-y-6">
        {{-- ================= Özet ================= --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                ['Atanan araç', $vehicles->count().' araç', $capacity.' yolcu koltuğu', $vehicles->isEmpty() ? 'danger' : 'gray'],
                ['Kayıtlı yolcu', $registered.' kişi', $departure->seatHoldingGroups()->count().' grup', $overbooked ? 'danger' : 'gray'],
                ['Araçlara yerleşen', ($registered - $waitingCount).' kişi', $capacity > 0 ? '%'.round(($registered - $waitingCount) / max(1, $capacity) * 100).' doluluk' : '—', 'success'],
                ['Yerleşmeyi bekleyen', $waitingCount.' kişi', $waiting->count().' grup', $waitingCount > 0 ? 'warning' : 'success'],
            ] as [$label, $value, $hint, $color])
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <div class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</div>
                    <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $value }}</div>
                    <div class="mt-2"><x-filament::badge :color="$color" size="sm">{{ $hint }}</x-filament::badge></div>
                </div>
            @endforeach
        </div>

        @if ($vehicles->isEmpty())
            <div class="rounded-xl border border-danger-300 bg-danger-50 p-4 text-sm text-danger-800 dark:border-danger-800 dark:bg-danger-950/40 dark:text-danger-300">
                <strong>Bu tura henüz araç atanmadı.</strong>
                <a class="underline" href="{{ \App\Filament\Pages\VehicleWizard::getUrl(['tour' => $departure->getKey()]) }}">Araç Liste Sihirbazı</a>'ndan araç ekleyin; gruplar ancak ondan sonra dağıtılabilir.
            </div>
        @elseif ($overbooked)
            <div class="rounded-xl border border-danger-300 bg-danger-50 p-4 text-sm text-danger-800 dark:border-danger-800 dark:bg-danger-950/40 dark:text-danger-300">
                <strong>Kayıtlı yolcu sayısı ({{ $registered }}) araç kapasitesini ({{ $capacity }}) aşıyor.</strong>
                {{ $registered - $capacity }} yolcu için ek araç gerekiyor. Gruplar bölünmediği için gereken koltuk bundan fazla da olabilir.
            </div>
        @endif

        <p class="text-sm text-gray-500 dark:text-gray-400">
            <x-filament::icon icon="heroicon-m-information-circle" class="inline h-4 w-4 align-text-bottom" />
            Gruplar <strong>bölünmez</strong>: bir grup ya tamamıyla tek bir araca biner ya da yerleşmeyi bekler.
            Araçlar listedeki sırayla doldurulur; ilk araç olabildiğince tam dolar.
        </p>

        {{-- ================= Araçlar ================= --}}
        <div class="grid gap-6 lg:grid-cols-2 2xl:grid-cols-3">
            @foreach ($vehicles as $index => $vehicle)
                @php
                    $used = (int) $vehicle->groups->sum('passenger_count');
                    $seats = $vehicle->usable_seats;
                    $percent = $seats > 0 ? min(100, (int) round($used / $seats * 100)) : 0;
                    $over = $used > $seats;
                    $barColor = $over ? 'bg-danger-500' : ($used === $seats && $seats > 0 ? 'bg-success-500' : 'bg-primary-500');
                @endphp
                <x-filament::section :compact="true">
                    <x-slot name="heading">
                        <span class="inline-flex items-center gap-2">
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $index + 1 }}</span>
                            {{ $vehicle->name }}
                        </span>
                    </x-slot>
                    <x-slot name="description">
                        {{ collect([$vehicle->plate, $vehicle->driver_name, $vehicle->driver_phone])->filter()->implode(' · ') ?: 'Plaka / şoför girilmedi' }}
                        @if ($vehicle->reserved_seats > 0)
                            · {{ $vehicle->reserved_seats }} koltuk görevliye ayrıldı
                        @endif
                    </x-slot>
                    <x-slot name="afterHeader">
                        <x-filament::badge :color="$over ? 'danger' : ($used === $seats && $seats > 0 ? 'success' : 'gray')">
                            {{ $used }} / {{ $seats }}
                        </x-filament::badge>
                    </x-slot>

                    <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800" role="progressbar" aria-valuenow="{{ $used }}" aria-valuemin="0" aria-valuemax="{{ $seats }}" aria-label="{{ $vehicle->name }} doluluk">
                        <div class="h-full rounded-full {{ $barColor }}" style="width: {{ $percent }}%"></div>
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        @if ($over)
                            <span class="font-semibold text-danger-600 dark:text-danger-400">{{ $used - $seats }} koltuk taşıyor — bir grubu başka araca taşıyın.</span>
                        @else
                            {{ $seats - $used }} boş koltuk
                        @endif
                    </div>

                    <ul class="mt-4 divide-y divide-gray-100 dark:divide-white/5">
                        @forelse ($vehicle->groups as $group)
                            @include('filament.resources.tour-departures.partials.group-row', ['group' => $group])
                        @empty
                            <li class="py-6 text-center text-sm text-gray-400 dark:text-gray-500">Bu araçta henüz grup yok.</li>
                        @endforelse
                    </ul>
                </x-filament::section>
            @endforeach
        </div>

        {{-- ================= Bekleyen gruplar ================= --}}
        <x-filament::section>
            <x-slot name="heading">Yerleşmeyi bekleyen gruplar</x-slot>
            <x-slot name="description">
                Henüz bir araca konmamış gruplar. "Bekleyenleri yerleştir" ile otomatik, "Taşı" ile elle yerleştirebilirsiniz.
            </x-slot>
            <x-slot name="afterHeader">
                <x-filament::badge :color="$waitingCount > 0 ? 'warning' : 'success'">
                    {{ $waiting->count() }} grup · {{ $waitingCount }} yolcu
                </x-filament::badge>
            </x-slot>

            <ul class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse ($waiting as $group)
                    @include('filament.resources.tour-departures.partials.group-row', ['group' => $group])
                @empty
                    <li class="py-6 text-center text-sm text-gray-400 dark:text-gray-500">
                        {{ $registered > 0 ? 'Bekleyen grup yok: herkes bir araca yerleşti.' : 'Bu tura henüz yolcu eklenmedi.' }}
                    </li>
                @endforelse
            </ul>
        </x-filament::section>
    </div>
</x-filament-panels::page>
