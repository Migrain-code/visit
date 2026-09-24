<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap items-center justify-end gap-3">
            <x-filament::button type="submit" size="lg" icon="heroicon-o-check">
                Araçları kaydet
            </x-filament::button>
            {{ $this->allocateAction }}
        </div>
    </form>

    @if ($tour)
        <x-filament::section>
            <x-slot name="heading">3. Özet · {{ $tour->label }}</x-slot>
            <x-slot name="description">Kaydedilmiş araçlara göre. "Grupları yerleştir" sonrası burası güncellenir.</x-slot>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($summary as [$label, $value, $hint, $color])
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</div>
                        <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $value }}</div>
                        <div class="mt-2"><x-filament::badge :color="$color" size="sm">{{ $hint }}</x-filament::badge></div>
                    </div>
                @endforeach
            </div>

            @if ($vehicles->isNotEmpty())
                <ul class="mt-6 divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($vehicles as $index => $vehicle)
                        @php $used = (int) $vehicle->groups->sum('passenger_count'); @endphp
                        <li class="flex flex-wrap items-center gap-x-4 gap-y-1 py-3 text-sm">
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $index + 1 }}</span>
                            <span class="font-medium text-gray-950 dark:text-white">{{ $vehicle->name }}</span>
                            <span class="text-gray-500">{{ collect([$vehicle->plate, $vehicle->driver_name, $vehicle->guide?->name ? 'Rehber: '.$vehicle->guide->name : null])->filter()->implode(' · ') ?: 'Plaka / şoför girilmedi' }}</span>
                            <span class="ms-auto flex items-center gap-2">
                                @if ($vehicle->cost !== null)
                                    <x-filament::badge color="gray" size="sm">{{ money_label($vehicle->cost) }}</x-filament::badge>
                                @endif
                                <x-filament::badge :color="$used > $vehicle->usable_seats ? 'danger' : ($used === $vehicle->usable_seats ? 'success' : 'gray')" size="sm">
                                    {{ $used }} / {{ $vehicle->usable_seats }} · {{ max(0, $vehicle->usable_seats - $used) }} boş
                                </x-filament::badge>
                            </span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
