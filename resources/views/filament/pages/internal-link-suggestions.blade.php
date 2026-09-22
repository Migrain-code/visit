<x-filament-panels::page>
    @unless ($enabled)
        <x-filament::section>
            <div class="flex items-start gap-3">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5 shrink-0 text-warning-500" />
                <div class="text-sm">
                    <strong>İç link motoru kapalı.</strong>
                    Kural oluşturabilirsiniz ama sitede link basılmaz.
                    <a href="{{ \App\Filament\Pages\SeoAiSettings::getUrl() }}" class="text-primary-600 hover:underline">
                        SEO &amp; AI Ayarları → İç link motoru
                    </a>
                    bölümünden açabilirsiniz.
                </div>
            </div>
        </x-filament::section>
    @endunless

    <x-filament::section>
        <div class="text-sm text-gray-600 dark:text-gray-400">
            Öneriler deterministik üretilir: anchor metinleri yalnız gerçek hedef sayfalardan ve o hedeflere
            atanmış anahtar kelimelerden gelir, uydurulmaz. Makale başına tavan <strong>{{ $maxPerArticle }}</strong>;
            dolu makalelere öneri çıkmaz. Reddedilen öneri bir daha gösterilmez.
            Kayıtlı kural sayısı: <strong>{{ $rules }}</strong>.
        </div>
    </x-filament::section>

    @if ($suggestions->isEmpty())
        <x-filament::section>
            <p class="text-sm text-gray-500">
                Öneri yok. Yayında blog yazısı olduğunda ve hedef sayfalara anahtar kelime atandığında öneriler burada görünür.
            </p>
        </x-filament::section>
    @else
        <x-filament::section heading="{{ $suggestions->count() }} öneri">
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($suggestions as $s)
                    <div class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <x-filament::badge :color="$s['score'] >= 80 ? 'success' : 'warning'">{{ $s['score'] }}</x-filament::badge>
                                <span class="truncate text-sm font-medium">{{ $s['blog_title'] }}</span>
                            </div>
                            <div class="mt-1 text-xs text-gray-500">
                                <code class="rounded bg-gray-100 px-1 dark:bg-gray-800">{{ $s['anchor'] }}</code>
                                &rarr; {{ $s['target_url'] }}
                                · yazıda {{ $s['occurrences'] }} kez geçiyor
                            </div>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            {{ ($this->approveAction)(['anchor' => $s['anchor'], 'target' => $s['target_url'], 'score' => $s['score']]) }}
                            {{ ($this->rejectAction)(['blog' => $s['blog_id'], 'anchor' => $s['anchor'], 'target' => $s['target_url']]) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
