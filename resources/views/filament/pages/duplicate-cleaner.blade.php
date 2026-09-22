<x-filament-panels::page>
    <x-filament::section>
        <div class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <p>
                <strong>İki ayrı eşik kullanılır.</strong>
                Benzerliği <strong>%{{ round($scanThreshold * 100) }}</strong> ve üzerindeki çiftler burada listelenir.
                Birleştirme butonu yalnız <strong>%{{ round($mergeThreshold * 100) }}</strong> ve üzerinde çıkar.
            </p>
            <p>
                Aradaki çiftler bilerek elle bırakılır: %67 benzeyen iki yazı farklı konular olabilir ve
                otomatik birleştirmek içerik silmek demektir.
            </p>
        </div>
    </x-filament::section>

    @if ($pairs->isEmpty())
        <x-filament::section>
            <p class="text-sm text-gray-500">Çakışan içerik bulunamadı.</p>
        </x-filament::section>
    @else
        <div class="space-y-3">
            @foreach ($pairs as $pair)
                @php
                    $winner = $pair['a']->getKey() === $pair['winner_id'] ? $pair['a'] : $pair['b'];
                    $loser = $pair['a']->getKey() === $pair['loser_id'] ? $pair['a'] : $pair['b'];
                    $alreadyMerged = $loser->merged_into_id !== null;
                @endphp

                <x-filament::section>
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 flex-1 space-y-3">
                            <div class="flex items-center gap-2">
                                <x-filament::badge :color="$pair['mergeable'] ? 'danger' : 'warning'">
                                    %{{ round($pair['score'] * 100) }} benzer
                                </x-filament::badge>
                                @if ($alreadyMerged)
                                    <x-filament::badge color="gray">Birleştirilmiş</x-filament::badge>
                                @elseif (! $pair['mergeable'])
                                    <span class="text-xs text-gray-500">Eşiğin altında — elle karar verin</span>
                                @endif
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <div class="rounded-lg border border-success-200 bg-success-50 p-3 dark:border-success-900 dark:bg-success-950/30">
                                    <div class="text-xs font-semibold uppercase text-success-700 dark:text-success-400">Kalacak (veriye göre)</div>
                                    <div class="mt-1 font-medium">{{ $winner->title }}</div>
                                    <div class="mt-1 text-xs text-gray-500">
                                        /blog/{{ $winner->slug }} ·
                                        {{ $winner->status === \App\Models\Blog::STATUS_PUBLISHED ? 'yayında' : 'taslak' }} ·
                                        {{ optional($winner->publish_at ?? $winner->created_at)->format('d.m.Y') }}
                                    </div>
                                </div>

                                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                                    <div class="text-xs font-semibold uppercase text-gray-500">Yayından kalkacak</div>
                                    <div class="mt-1 font-medium">{{ $loser->title }}</div>
                                    <div class="mt-1 text-xs text-gray-500">
                                        /blog/{{ $loser->slug }} ·
                                        {{ $loser->status === \App\Models\Blog::STATUS_PUBLISHED ? 'yayında' : 'taslak' }} ·
                                        {{ optional($loser->publish_at ?? $loser->created_at)->format('d.m.Y') }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex shrink-0 flex-col gap-2">
                            @if ($alreadyMerged)
                                {{ ($this->undoAction)(['blog' => $loser->getKey()]) }}
                            @elseif ($pair['mergeable'])
                                {{ ($this->mergeAction)(['winner' => $winner->getKey(), 'loser' => $loser->getKey()]) }}
                            @endif

                            <x-filament::button
                                tag="a"
                                href="{{ \App\Filament\Resources\Blogs\BlogResource::getUrl('edit', ['record' => $loser]) }}"
                                color="gray" size="sm" outlined>
                                Düzenle
                            </x-filament::button>
                        </div>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
