{{-- Panodaki tek bir grup satırı. Grup bölünmediği için araç bilgisi satırın tamamı içindir. --}}
<li class="flex items-start gap-3 py-3" wire:key="group-{{ $group->getKey() }}">
    <span class="mt-0.5 inline-flex h-8 min-w-8 items-center justify-center rounded-lg bg-primary-50 px-2 text-sm font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-300" title="Yolcu sayısı">
        {{ $group->passenger_count }}
    </span>

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <a href="{{ $groupUrl($group) }}" class="font-medium text-gray-950 hover:underline dark:text-white">{{ $group->name ?: $group->contact_name }}</a>
            @if ($group->status === \App\Enums\GroupStatus::Pending)
                <x-filament::badge color="warning" size="sm">Opsiyon</x-filament::badge>
            @endif
            @if ($group->is_pinned)
                <x-filament::badge color="info" size="sm" icon="heroicon-m-lock-closed">Sabit</x-filament::badge>
            @endif
        </div>
        <div class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
            {{ $group->passengers->map(fn ($p) => $p->full_name)->implode(', ') ?: 'Yolcu bilgisi girilmedi' }}
        </div>
        @if ($group->pickup_point)
            <div class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Biniş: {{ $group->pickup_point }}</div>
        @endif
    </div>

    @if ($canAllocate)
        <div class="flex shrink-0 items-center gap-1">
            @if ($group->departure_vehicle_id)
                <x-filament::icon-button
                    :icon="$group->is_pinned ? 'heroicon-m-lock-closed' : 'heroicon-m-lock-open'"
                    :color="$group->is_pinned ? 'info' : 'gray'"
                    size="sm"
                    :tooltip="$group->is_pinned ? 'Sabitlemeyi kaldır' : 'Bu araca sabitle'"
                    :label="$group->is_pinned ? 'Sabitlemeyi kaldır' : 'Bu araca sabitle'"
                    wire:click="togglePin({{ $group->getKey() }})"
                />
            @endif
            <x-filament::button size="xs" color="gray" outlined wire:click="mountAction('moveGroup', { group: {{ $group->getKey() }} })">
                Taşı
            </x-filament::button>
        </div>
    @endif
</li>
