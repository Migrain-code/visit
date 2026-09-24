<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ($cards as [$label, $value, $hint, $color])
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</div>
                <div class="mt-1 text-2xl font-semibold text-gray-950 dark:text-white">{{ $value }}</div>
                <div class="mt-2"><x-filament::badge :color="$color" size="sm">{{ $hint }}</x-filament::badge></div>
            </div>
        @endforeach
    </div>

    {{ $this->table }}
</x-filament-panels::page>
