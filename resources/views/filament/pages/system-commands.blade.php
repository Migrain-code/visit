<x-filament-panels::page>
    <div @if ($hasActive) wire:poll.5s @endif class="space-y-6">

        @unless ($tableReady)
            <div class="rounded-xl border border-warning-300 bg-warning-50 p-4 text-sm text-warning-900 dark:border-warning-800 dark:bg-warning-950/40 dark:text-warning-200">
                <p class="font-semibold">Önce "Veritabanını güncelle" komutunu çalıştırın.</p>
                <p class="mt-1">
                    Yeni yüklenen kodun veritabanı tabloları henüz oluşturulmamış. Aşağıdaki
                    <strong>Kurulum ve güncelleme</strong> bölümünden bu komutu çalıştırdıktan sonra
                    komut geçmişi ve arka plan komutları kullanılabilir hâle gelir.
                </p>
            </div>
        @endunless

        {{-- ================= Sürüm ================= --}}
        <x-filament::section>
            <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-gray-700 dark:text-gray-300">
                <span>
                    Sunucudaki kod:
                    <code class="rounded bg-gray-100 px-1.5 py-0.5 font-mono dark:bg-gray-800">{{ $deployment['commit'] ?? 'bilinmiyor' }}</code>
                    @if ($deployment['code_at'])
                        · {{ $deployment['code_at']->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}'de güncellendi
                    @endif
                </span>
                @if ($deployment['build_at'])
                    <span>Derlenmiş dosyalar (public/build): {{ $deployment['build_at']->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}'de yüklendi</span>
                @endif
            </div>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                Bu kod GitHub'daki son commit'in kısa koduyla aynı olmalı. Kod güncellendikten sonra public/build klasörü de yeniden yüklenmeli.
            </p>

            @if ($deployment['mismatch'])
                <div class="mt-3 rounded-lg border border-danger-300 bg-danger-50 p-3 text-sm text-danger-800 dark:border-danger-800 dark:bg-danger-950/40 dark:text-danger-300">
                    <strong>Derlenmiş dosyalar koddan daha yeni.</strong>
                    public/build yüklenmiş ama kod güncellenmemiş görünüyor. Bu durumda ikonlar kaybolabilir ve teklif formu çalışmayabilir.
                    cPanel → Git Version Control → Manage → Pull or Deploy → "Update from Remote" ile kodu güncelleyin.
                </div>
            @endif
        </x-filament::section>

        {{-- ================= Otomatik görevler ================= --}}
        <x-filament::section>
            <x-slot name="heading">Otomatik görevler</x-slot>
            <x-slot name="description">
                Zamanlayıcı SEO görevlerini, kuyruk işçisi blog yazılarını ve arka plan komutlarını çalıştırır.
                İkisi de hosting panelinizdeki cron ile tetiklenir.
            </x-slot>

            <div class="grid gap-4 md:grid-cols-2">
                @foreach ([
                    ['Zamanlayıcı', $health['scheduler_ok'], $health['scheduler_last'], 'SEO görevleri, site haritası, yazı yayınlama'],
                    ['Kuyruk işçisi', $health['queue_ok'], $health['queue_last'], 'Yapay zeka blog yazıları ve arka plan komutları'],
                ] as [$name, $ok, $last, $what])
                    <div class="rounded-xl border p-4 {{ $ok ? 'border-success-200 bg-success-50 dark:border-success-900 dark:bg-success-950/30' : 'border-danger-200 bg-danger-50 dark:border-danger-900 dark:bg-danger-950/30' }}">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold">{{ $name }}</span>
                            <x-filament::badge :color="$ok ? 'success' : 'danger'" :icon="$ok ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle'">
                                {{ $ok ? 'Çalışıyor' : 'Çalışmıyor' }}
                            </x-filament::badge>
                        </div>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $what }}</p>
                        <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                            @if ($last)
                                Son sinyal: {{ $last->diffForHumans() }} ({{ $last->timezone('Europe/Istanbul')->format('d.m.Y H:i') }})
                            @elseif ($name === 'Kuyruk işçisi' && $health['queue_connection'] === 'sync')
                                Kuyruk "sync" modunda: işler beklemeden hemen çalışır, işçi gerekmez.
                            @else
                                Hiç sinyal gelmedi. Cron henüz kurulmamış ya da çalışmıyor.
                            @endif
                        </p>
                    </div>
                @endforeach
            </div>

            <div class="mt-4 flex flex-wrap gap-x-6 gap-y-1 text-sm text-gray-600 dark:text-gray-400">
                <span>Kuyruk bağlantısı: <strong>{{ $health['queue_connection'] }}</strong></span>
                @if ($health['pending_jobs'] !== null)
                    <span>Bekleyen iş: <strong>{{ $health['pending_jobs'] }}</strong></span>
                @endif
                @if ($health['failed_jobs'] !== null)
                    <span>Başarısız iş: <strong class="{{ $health['failed_jobs'] > 0 ? 'text-danger-600 dark:text-danger-400' : '' }}">{{ $health['failed_jobs'] }}</strong></span>
                @endif
                <span>PHP: <strong>{{ $health['php_version'] }}</strong></span>
            </div>

            @unless ($health['scheduler_ok'] && $health['queue_ok'])
                <div class="mt-6 space-y-4">
                    <div class="text-sm text-gray-700 dark:text-gray-300">
                        <p class="font-semibold">Cron kurulumu</p>
                        <p class="mt-1">
                            Hosting panelinizde (cPanel'de <em>Cron Jobs</em>, Plesk'te <em>Zamanlanmış Görevler</em>)
                            aşağıdaki iki görevi ekleyin. Zamanlama ikisinde de <strong>her dakika</strong> olmalı:
                            <code class="whitespace-nowrap rounded bg-gray-100 px-1.5 py-0.5 font-mono text-xs dark:bg-gray-800">* * * * *</code>
                        </p>
                    </div>

                    @foreach ([
                        ['1. Zamanlayıcı', $cron['schedule']],
                        ['2. Kuyruk işçisi', $cron['queue']],
                    ] as [$title, $line])
                        <div x-data="{ copied: false }">
                            <div class="mb-1 flex items-center justify-between gap-2">
                                <span class="text-sm font-medium">{{ $title }}</span>
                                <x-filament::button size="xs" color="gray" icon="heroicon-m-clipboard"
                                    x-on:click="navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($line) }}); copied = true; setTimeout(() => copied = false, 1500)">
                                    <span x-show="! copied">Kopyala</span>
                                    <span x-show="copied" x-cloak>Kopyalandı</span>
                                </x-filament::button>
                            </div>
                            <pre class="overflow-x-auto whitespace-pre-wrap break-all rounded-lg bg-gray-950 p-3 font-mono text-xs text-gray-100">{{ $line }}</pre>
                        </div>
                    @endforeach

                    <ul class="list-disc space-y-1 pl-5 text-xs text-gray-500 dark:text-gray-400">
                        <li>
                            PHP yolu, bu sitenin kullandığı PHP {{ $health['php_version'] }} sürümüne göre yazıldı:
                            <code class="font-mono">{{ $cron['php'] }}</code>.
                            Hosting paneliniz cron için farklı bir PHP yolu öneriyorsa onu kullanın; sürüm 8.2 veya üstü olmalı.
                            Yalnız <code class="font-mono">php</code> yazmak çoğu hostingde eski bir sürümü çalıştırır.
                        </li>
                        <li>Cron kaydedildikten sonra bir-iki dakika içinde yukarıdaki göstergeler yeşile döner. Sayfayı yenileyin.</li>
                        <li>Kuyruk işçisi her dakika başlar, bekleyen işleri bitirince kapanır; sürekli açık kalmaz.</li>
                    </ul>
                </div>
            @endunless
        </x-filament::section>

        {{-- ================= Komutlar ================= --}}
        @foreach ($groups as $groupKey => $commands)
            <x-filament::section :heading="$groupLabels[$groupKey] ?? $groupKey" collapsible :collapsed="in_array($groupKey, ['diagnostics'], true)">
                @if ($groupKey === 'seo' && ! $health['queue_ok'])
                    <p class="mb-3 text-sm text-warning-700 dark:text-warning-400">
                        Kuyruk işçisi çalışmıyor: "Sıraya al" düğmeli komutlar cron kurulana kadar sırada bekler.
                    </p>
                @endif

                <div class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($commands as $key => $command)
                        <div class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium">{{ $command['label'] }}</span>
                                    @if ($command['mode'] === 'background')
                                        <x-filament::badge color="gray" size="sm">Arka planda</x-filament::badge>
                                    @endif
                                    @if ($command['danger'])
                                        <x-filament::badge color="warning" size="sm">Dikkat</x-filament::badge>
                                    @endif
                                    @if (in_array($key, $activeKeys, true))
                                        <x-filament::badge color="info" size="sm">Çalışıyor</x-filament::badge>
                                    @endif
                                </div>
                                <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">{{ $command['description'] }}</p>
                                <code class="mt-1 inline-block font-mono text-xs text-gray-400">php artisan {{ \App\Support\Console\CommandCatalog::commandLine($command) }}</code>

                                @if ($key === 'storage-link')
                                    <div class="mt-2">
                                        @if ($storageLink['exists'])
                                            <x-filament::badge color="success" size="sm" icon="heroicon-m-check-circle">Kurulu</x-filament::badge>
                                        @else
                                            <x-filament::badge color="danger" size="sm" icon="heroicon-m-x-circle">Kurulu değil: yüklenen görseller sitede görünmüyor</x-filament::badge>

                                            @unless ($storageLink['php'])
                                                <div x-data="{ copied: false }" class="mt-2 space-y-1">
                                                    <p class="text-xs text-gray-600 dark:text-gray-400">
                                                        Hosting bu bağlantıyı PHP'den kurmaya izin vermiyor. Hosting panelinizde
                                                        <strong>her dakika</strong> çalışan yeni bir cron görevi olarak aşağıdaki satırı ekleyin.
                                                        Bir-iki dakika sonra bu sayfayı yenileyin; "Kurulu" görününce cron görevini silin.
                                                    </p>
                                                    <div class="flex items-start gap-2">
                                                        <pre class="min-w-0 flex-1 overflow-x-auto whitespace-pre-wrap break-all rounded-lg bg-gray-950 p-2 font-mono text-xs text-gray-100">{{ $storageLink['cron'] }}</pre>
                                                        <x-filament::button size="xs" color="gray" icon="heroicon-m-clipboard"
                                                            x-on:click="navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($storageLink['cron']) }}); copied = true; setTimeout(() => copied = false, 1500)">
                                                            <span x-show="! copied">Kopyala</span>
                                                            <span x-show="copied" x-cloak>Kopyalandı</span>
                                                        </x-filament::button>
                                                    </div>
                                                </div>
                                            @endunless
                                        @endif
                                    </div>
                                @endif
                            </div>
                            <div class="shrink-0">
                                {{ ($this->runAction)(['key' => $key]) }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endforeach

        {{-- ================= Geçmiş ================= --}}
        <x-filament::section heading="Son çalıştırmalar">
            <x-slot name="description">Son 25 çalıştırma. Kayıtlar 30 gün saklanır.</x-slot>

            @if ($runs->isEmpty())
                <p class="text-sm text-gray-500">Henüz panelden komut çalıştırılmadı.</p>
            @else
                <div class="space-y-2">
                    @foreach ($runs as $run)
                        @php
                            $label = \App\Support\Console\CommandCatalog::find($run->command_key)['label'] ?? $run->command_key;
                        @endphp
                        <details class="group rounded-lg border border-gray-200 dark:border-white/10" @if ($loop->first && $run->status === \App\Models\CommandRun::FAILED) open @endif>
                            <summary class="flex cursor-pointer flex-wrap items-center gap-x-3 gap-y-1 px-3 py-2 text-sm">
                                <x-filament::badge :color="\App\Models\CommandRun::STATUS_COLORS[$run->status] ?? 'gray'" size="sm">
                                    {{ \App\Models\CommandRun::STATUS_LABELS[$run->status] ?? $run->status }}
                                </x-filament::badge>
                                <span class="font-medium">{{ $label }}</span>
                                <code class="font-mono text-xs text-gray-400">{{ $run->command_line }}</code>
                                <span class="ms-auto text-xs text-gray-500">
                                    {{ $run->user?->name ?? 'Sistem' }} ·
                                    {{ $run->created_at->timezone('Europe/Istanbul')->format('d.m.Y H:i') }}
                                    @if ($run->durationLabel()) · {{ $run->durationLabel() }} @endif
                                </span>
                            </summary>
                            <div class="border-t border-gray-200 px-3 py-2 dark:border-white/10">
                                @if ($run->output)
                                    <pre class="max-h-96 overflow-auto whitespace-pre-wrap break-words rounded bg-gray-950 p-3 font-mono text-xs text-gray-100">{{ $run->output }}</pre>
                                @elseif ($run->isActive())
                                    <p class="text-sm text-gray-500">{{ $run->status === \App\Models\CommandRun::QUEUED ? 'Kuyruk işçisinin almasını bekliyor…' : 'Çalışıyor…' }}</p>
                                @else
                                    <p class="text-sm text-gray-500">Komut çıktı üretmedi.</p>
                                @endif
                                @if ($run->exit_code !== null)
                                    <p class="mt-1 text-xs text-gray-400">Çıkış kodu: {{ $run->exit_code }}</p>
                                @endif
                            </div>
                        </details>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
