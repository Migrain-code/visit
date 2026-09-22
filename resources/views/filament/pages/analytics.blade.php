<x-filament-panels::page>

    {{-- ---------------------------------------------------------------
         Sayı kutuları. Tek başına anlamlı rakamlar grafik istemez;
         hero rakam + bağlam satırı daha hızlı okunur.
    ---------------------------------------------------------------- --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ($tiles as $tile)
            <x-filament::section class="!p-0">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg"
                          style="background: {{ $tile['color'] }}1f; color: {{ $tile['color'] }}">
                        <x-filament::icon :icon="$tile['icon']" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $tile['label'] }}</div>
                        <div class="mt-0.5 text-2xl font-bold tabular-nums">{{ number_format($tile['value'], 0, ',', '.') }}</div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $tile['unit'] }}</div>
                        @if ($tile['delta'])
                            <div @class([
                                'mt-1 inline-flex items-center gap-1 text-xs font-medium',
                                'text-success-600 dark:text-success-400' => $tile['delta']['direction'] === 'up',
                                'text-danger-600 dark:text-danger-400' => $tile['delta']['direction'] === 'down',
                            ])>
                                <x-filament::icon
                                    :icon="$tile['delta']['direction'] === 'up' ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down'"
                                    class="h-3.5 w-3.5" />
                                {{ $tile['delta']['text'] }}
                            </div>
                        @endif
                    </div>
                </div>
            </x-filament::section>
        @endforeach
    </div>

    {{-- ---------------------------------------------------------------
         AI bot ziyaretleri — bot başına TEK KART.
         Ham satır listesi okunmaz; aynı bot onlarca adrese dağılır.
    ---------------------------------------------------------------- --}}
    <x-filament::section>
        <x-slot name="heading">Yapay zeka botları</x-slot>
        <x-slot name="description">
            ChatGPT, Claude, Perplexity gibi ajanların siteyi ne sıklıkla okuduğu.
            Bu botlar <code>llms.txt</code> ve <code>Link</code> başlıklarıyla yönlendirilir.
        </x-slot>

        @include('filament.partials.bot-cards')

        <div class="mt-4">
            <x-filament::link :href="\App\Filament\Resources\AiCrawlerVisits\AiCrawlerVisitResource::getUrl('index')" size="sm">
                Tüm ziyaret kayıtları
            </x-filament::link>
        </div>
    </x-filament::section>
    <div class="grid gap-4 lg:grid-cols-2">

        {{-- ---------------------------------------------------------------
             SEO skoru — yatay çubuk + DOĞRUDAN ETİKET.
             Rakam çubuğun yanında yazılı olduğu için renk tek başına bilgi taşımaz.
        ---------------------------------------------------------------- --}}
        <x-filament::section>
            <x-slot name="heading">İçerik tipine göre SEO skoru</x-slot>
            <x-slot name="description">Yalnız yayındaki sayfalar. Hedef 80/100.</x-slot>

            @if ($scores->isEmpty())
                <p class="text-sm text-gray-500">Henüz tarama yapılmadı. Terminalde: <code>php artisan seo:score</code></p>
            @else
                <div class="space-y-3">
                    @foreach ($scores as $row)
                        <div>
                            <div class="mb-1 flex items-baseline justify-between text-sm">
                                <span class="font-medium">{{ $row['label'] }}</span>
                                <span class="tabular-nums text-gray-500 dark:text-gray-400">
                                    <strong class="text-gray-900 dark:text-white">{{ $row['score'] }}</strong>/100 · {{ $row['count'] }} sayfa
                                </span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                <div class="h-full rounded-full" style="width: {{ $row['score'] }}%; background: {{ $row['color'] }}"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>

        {{-- Yayın takvimi --}}
        <x-filament::section>
            <x-slot name="heading">Yaklaşan blog yazıları</x-slot>
            <x-slot name="description">Taslak olarak bekliyor, zamanı gelince kendiliğinden yayınlanır.</x-slot>

            @if ($upcoming->isEmpty())
                <p class="text-sm text-gray-500">Planlanmış yazı yok. Terminalde: <code>php artisan blog:bulk --days=30</code></p>
            @else
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($upcoming as $post)
                        <div class="flex items-center gap-3 py-2">
                            <span class="shrink-0 rounded-md bg-gray-100 px-2 py-1 text-xs font-medium tabular-nums dark:bg-gray-800">
                                {{ $post->publish_at->format('d.m') }}
                            </span>
                            <span class="min-w-0 flex-1 truncate text-sm">{{ $post->title }}</span>
                            <span class="shrink-0 text-xs text-gray-400">{{ $post->category?->name }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>

    {{-- Google arama --}}
    <x-filament::section>
        <x-slot name="heading">Google araması</x-slot>

        @if (! $googleReady)
            <div class="rounded-lg border border-dashed border-warning-400/60 bg-warning-50/50 p-5 dark:bg-warning-950/20">
                <div class="flex items-start gap-3">
                    <x-filament::icon icon="heroicon-o-link-slash" class="mt-0.5 h-5 w-5 shrink-0 text-warning-500" />
                    <div class="text-sm">
                        <p class="font-medium">Search Console bağlı değil.</p>
                        <p class="mt-1 text-gray-600 dark:text-gray-400">
                            Tıklama, gösterim, sıralama ve indeks verisi bağlantı kurulunca burada görünür.
                        </p>
                        <x-filament::link :href="\App\Filament\Pages\SeoAiSettings::getUrl().'?tab=-search-console-tab'" size="sm" class="mt-2 inline-block">
                            Anahtar dosyasını yükle
                        </x-filament::link>
                    </div>
                </div>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Tıklama</div>
                    <div class="text-2xl font-bold tabular-nums">{{ number_format($search['clicks'], 0, ',', '.') }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Gösterim</div>
                    <div class="text-2xl font-bold tabular-nums">{{ number_format($search['impressions'], 0, ',', '.') }}</div>
                </div>
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Ortalama sıra</div>
                    <div class="text-2xl font-bold tabular-nums">{{ $search['position'] ?? '—' }}</div>
                </div>
            </div>

            @if ($search['top_queries']->isNotEmpty())
                <div class="mt-4 border-t border-gray-100 pt-3 dark:border-gray-800">
                    <div class="mb-2 text-sm font-medium">En çok tıklanan aramalar</div>
                    <div class="space-y-1">
                        @foreach ($search['top_queries'] as $q)
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="truncate">{{ $q->query }}</span>
                                <span class="shrink-0 tabular-nums text-gray-500">{{ $q->clicks }} tıklama · sıra {{ round($q->position, 1) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        @endif
    </x-filament::section>

    {{-- Sorunlu adresler --}}
    @if ($notFound->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">En çok istenen bulunamayan adresler</x-slot>
            <x-slot name="description">Bunlara yönlendirme tanımlamak kaybolan ziyaretçiyi geri kazandırır.</x-slot>

            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($notFound as $log)
                    <div class="flex items-center justify-between gap-3 py-2 text-sm">
                        <span class="min-w-0 flex-1 truncate font-mono text-xs">{{ $log->path }}</span>
                        <span class="shrink-0 tabular-nums text-gray-500">{{ $log->hits }} istek</span>
                    </div>
                @endforeach
            </div>

            <div class="mt-3">
                <x-filament::link :href="\App\Filament\Resources\NotFoundLogs\NotFoundLogResource::getUrl('index')" size="sm">
                    404 kayıtlarını aç
                </x-filament::link>
            </div>
        </x-filament::section>
    @endif

</x-filament-panels::page>
