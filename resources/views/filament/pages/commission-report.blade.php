<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ([
            ['Ödenen komisyon', money_label($total), 'filtreye göre', 'success'],
            ['Tur', $tours, 'komisyon yazılan tur', 'info'],
            ['Personel', $staff, 'komisyon alan kişi', 'gray'],
        ] as [$label, $value, $hint, $color])
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</div>
                <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $value }}</div>
                <div class="mt-2"><x-filament::badge :color="$color" size="sm">{{ $hint }}</x-filament::badge></div>
            </div>
        @endforeach
    </div>

    @if ($byStaff->isNotEmpty())
        <div class="grid gap-6 lg:grid-cols-2">
            <x-filament::section :compact="true">
                <x-slot name="heading">Personele göre</x-slot>
                <ul class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($byStaff as $row)
                        <li class="flex items-center justify-between gap-3 py-2 text-sm">
                            <span class="font-medium text-gray-950 dark:text-white">{{ $row['name'] }}</span>
                            <span class="text-gray-500">{{ $row['count'] }} tur</span>
                            <span class="font-semibold">{{ money_label($row['total']) }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
            <x-filament::section :compact="true">
                <x-slot name="heading">Tura göre</x-slot>
                <ul class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($byTour->take(12) as $row)
                        <li class="flex items-center justify-between gap-3 py-2 text-sm">
                            <span class="min-w-0 flex-1 truncate font-medium text-gray-950 dark:text-white">{{ $row['label'] }}</span>
                            <span class="text-gray-500">{{ $row['count'] }} kişi</span>
                            <span class="font-semibold">{{ money_label($row['total']) }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>
        </div>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
