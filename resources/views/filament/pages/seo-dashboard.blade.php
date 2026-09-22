<x-filament-panels::page>
    @php
        $scoreColor = $average >= $target ? 'success' : ($average >= 60 ? 'warning' : 'danger');
    @endphp

    <div class="grid gap-4 md:grid-cols-4">
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Ortalama SEO skoru</div>
            <div @class([
                'mt-1 text-3xl font-bold',
                'text-success-600' => $scoreColor === 'success',
                'text-warning-600' => $scoreColor === 'warning',
                'text-danger-600' => $scoreColor === 'danger',
            ])>{{ $average }}<span class="text-lg text-gray-400">/100</span></div>
            <div class="mt-1 text-xs text-gray-500">Hedef: {{ $target }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Hedefin altında</div>
            <div class="mt-1 text-3xl font-bold">{{ $below }}<span class="text-lg text-gray-400">/{{ $total }}</span></div>
            <div class="mt-1 text-xs text-gray-500">
                {{ $lastRun ? 'Son tarama: '.$lastRun->diffForHumans() : 'Henüz taranmadı' }}
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Boştaki anahtar kelime</div>
            <div class="mt-1 text-3xl font-bold">{{ $keywordStats['free'] }}</div>
            <div class="mt-1 text-xs text-gray-500">
                {{ $keywordStats['total'] }} toplam · {{ $keywordStats['assigned'] }} sahipli · {{ $keywordStats['covered'] }} içerikte geçiyor
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Dikkat gerektirenler</div>
            <div class="mt-1 flex flex-wrap gap-2">
                <x-filament::badge :color="$duplicatePairs > 0 ? 'warning' : 'gray'">{{ $duplicatePairs }} çakışma</x-filament::badge>
                <x-filament::badge :color="$notFound > 0 ? 'danger' : 'gray'">{{ $notFound }} adet 404</x-filament::badge>
                <x-filament::badge :color="$aiFailures > 0 ? 'danger' : 'gray'">{{ $aiFailures }} AI hatası</x-filament::badge>
            </div>
            <div class="mt-2 text-xs text-gray-500">
                Blog: {{ $blogStats['published'] }} yayında · {{ $blogStats['draft'] }} taslak · {{ $blogStats['merged'] }} birleştirildi
            </div>
        </x-filament::section>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <x-filament::section heading="En sık görülen sorunlar">
            @if ($issues->isEmpty())
                <p class="text-sm text-gray-500">Henüz tarama yapılmadı. Terminalde: <code>php artisan seo:score</code></p>
            @else
                <div class="space-y-2">
                    @foreach ($issues as $issue => $count)
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <span class="text-gray-700 dark:text-gray-300">{{ $issue }}</span>
                            <x-filament::badge :color="$count >= $total / 2 ? 'danger' : 'warning'">{{ $count }}</x-filament::badge>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        <x-filament::section heading="İçerik tipine göre">
            <div class="space-y-3">
                @foreach ($byType as $row)
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium">{{ $row['label'] }}</span>
                            <span class="text-gray-500">{{ $row['average'] }}/100 · {{ $row['count'] }} sayfa</span>
                        </div>
                        <div class="mt-1 h-2 w-full overflow-hidden rounded bg-gray-100 dark:bg-gray-800">
                            <div @class([
                                'h-full rounded',
                                'bg-success-500' => $row['average'] >= $target,
                                'bg-warning-500' => $row['average'] < $target && $row['average'] >= 60,
                                'bg-danger-500' => $row['average'] < 60,
                            ]) style="width: {{ $row['average'] }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($indexStatus->isNotEmpty())
                <div class="mt-4 border-t border-gray-200 pt-3 dark:border-gray-700">
                    <div class="mb-2 text-sm font-medium">Google indeks durumu</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($indexStatus as $status => $count)
                            <x-filament::badge :color="$status === 'PASS' ? 'success' : 'warning'">
                                {{ \App\Models\SeoAnalysis::INDEX_STATUSES[$status] ?? $status }}: {{ $count }}
                            </x-filament::badge>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-filament::section>
    </div>

    <x-filament::section heading="En düşük skorlu sayfalar">
        @if ($worst->isEmpty())
            <p class="text-sm text-gray-500">Veri yok.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="pb-2">Sayfa</th>
                            <th class="pb-2">Tip</th>
                            <th class="pb-2">Skor</th>
                            <th class="pb-2">Sorunlar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($worst as $row)
                            <tr>
                                <td class="py-2">
                                    <a href="{{ $row->url }}" target="_blank" rel="noopener" class="text-primary-600 hover:underline">
                                        {{ \Illuminate\Support\Str::of($row->url)->after(config('app.url')) ?: '/' }}
                                    </a>
                                </td>
                                <td class="py-2 text-gray-500">{{ \App\Services\Seo\ContentRegistry::TYPES[$row->content_type] ?? $row->content_type }}</td>
                                <td class="py-2">
                                    <x-filament::badge :color="$row->score_color">{{ $row->score }}</x-filament::badge>
                                </td>
                                <td class="py-2 text-xs text-gray-500">{{ implode(' · ', array_slice($row->issues ?? [], 0, 3)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-filament::section>
</x-filament-panels::page>
