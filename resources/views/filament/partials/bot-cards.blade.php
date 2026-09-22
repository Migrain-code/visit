@php
    $bots = app(\App\Services\Analytics\BotSummary::class)->cards();
    $totals = app(\App\Services\Analytics\BotSummary::class)->totals();
    $compact = $compact ?? false;
@endphp

@if ($bots->isNotEmpty())
    <div class="mb-4 flex flex-wrap items-center gap-x-6 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
        <span><strong class="text-gray-900 dark:text-white">{{ number_format($totals['hits'], 0, ',', '.') }}</strong> toplam ziyaret</span>
        <span><strong class="text-gray-900 dark:text-white">{{ $totals['bots'] }}</strong> farklı bot</span>
        <span><strong class="text-gray-900 dark:text-white">{{ $totals['paths'] }}</strong> farklı adres</span>
        @if ($totals['errors'] > 0)
            <span class="text-danger-600 dark:text-danger-400">{{ $totals['errors'] }} hatalı istek</span>
        @endif
        @if ($totals['last_seen'])
            <span>son ziyaret {{ \Illuminate\Support\Carbon::parse($totals['last_seen'])->diffForHumans() }}</span>
        @endif
    </div>

    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($bots as $bot)
            <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-2">
                        {{-- Renk kimlik taşır ama tek başına bırakılmaz: ad hemen yanında. --}}
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background: {{ $bot['color'] }}"></span>
                        <span class="truncate font-semibold">{{ $bot['bot'] }}</span>
                    </div>
                    @if ($bot['errors'] > 0)
                        <x-filament::badge color="danger" size="sm">{{ $bot['errors'] }} hata</x-filament::badge>
                    @endif
                </div>

                <div class="mt-3 flex items-baseline gap-2">
                    <span class="text-2xl font-bold tabular-nums">{{ number_format($bot['hits'], 0, ',', '.') }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">ziyaret · %{{ $bot['share'] }}</span>
                </div>

                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                    <div class="h-full rounded-full" style="width: {{ max(2, $bot['share']) }}%; background: {{ $bot['color'] }}"></div>
                </div>

                @unless ($compact)
                    <div class="mt-3 space-y-1">
                        @foreach ($bot['top'] as $row)
                            <div class="flex items-center justify-between gap-2 text-xs">
                                <span class="truncate text-gray-600 dark:text-gray-300">{{ $row['path'] }}</span>
                                <span class="shrink-0 tabular-nums text-gray-400">
                                    {{ $row['hits'] }}@if ($row['status'] && $row['status'] >= 400)<span class="text-danger-500"> · {{ $row['status'] }}</span>@endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endunless

                <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-2 text-xs text-gray-400 dark:border-gray-800">
                    <span>{{ $bot['paths'] }} adres</span>
                    @if ($bot['last_seen'])
                        <span>{{ \Illuminate\Support\Carbon::parse($bot['last_seen'])->diffForHumans() }}</span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center dark:border-gray-700">
        <p class="text-sm text-gray-500 dark:text-gray-400">Henüz yapay zeka botu ziyareti kaydedilmedi.</p>
        <p class="mt-1 text-xs text-gray-400">Site yayına alınıp taranmaya başlayınca burası dolar.</p>
    </div>
@endif
