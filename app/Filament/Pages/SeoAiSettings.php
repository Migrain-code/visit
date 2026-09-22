<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\Ai\AiClient;
use App\Services\Google\CredentialFile;
use App\Services\Google\GoogleClient;
use App\Support\SeoConfig;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Throwable;
use UnitEnum;

class SeoAiSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'SEO & AI';

    protected static ?string $navigationLabel = 'SEO & AI Ayarları';

    protected static ?string $title = 'SEO & AI Ayarları';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.seo-ai-settings';

    /** AI anahtarı ve Google kimliği buradan yönetilir: yalnız süper yönetici. */
    public static function canAccess(): bool
    {
        return auth()->user()?->isSuperAdmin() ?? false;
    }

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $keys = Setting::query()
            ->where('key', 'like', SeoConfig::PREFIX.'%')
            ->pluck('value', 'key')
            ->mapWithKeys(fn ($value, $key) => [substr($key, strlen(SeoConfig::PREFIX)) => $value])
            ->all();

        $this->form->fill($keys + $this->defaults());
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        return [
            'duplicate_scan_threshold' => config('seo.duplicate.scan_threshold'),
            'duplicate_merge_threshold' => config('seo.duplicate.merge_threshold'),
            'duplicate_max_owners_per_keyword' => config('seo.duplicate.max_owners_per_keyword'),
            'blog_daily_enabled' => config('seo.blog.daily_enabled'),
            'blog_daily_count' => config('seo.blog.daily_count'),
            'blog_topic_candidates' => config('seo.blog.topic_candidates'),
            'blog_publish_hour_start' => config('seo.blog.publish_hour_start'),
            'blog_publish_hour_end' => config('seo.blog.publish_hour_end'),
            'blog_auto_publish' => config('seo.blog.auto_publish'),
            'blog_min_words' => config('seo.blog.min_words'),
            'blog_max_words' => config('seo.blog.max_words'),
            'internal_links_enabled' => config('seo.internal_links.enabled'),
            'internal_links_auto_apply' => config('seo.internal_links.auto_apply'),
            'internal_links_max_per_article' => config('seo.internal_links.max_per_article'),
            'internal_links_max_total' => config('seo.internal_links.max_total'),
            'internal_links_max_home' => config('seo.internal_links.max_home'),
            'internal_links_min_score' => config('seo.internal_links.min_score'),
            'internal_links_batch_size' => config('seo.internal_links.batch_size'),
            'score_target' => config('seo.score.target'),
            'redirects_auto_apply' => config('seo.redirects.auto_apply'),
            'ai_refresh_meta' => false,
            'ai_refresh_meta_limit' => 3,
            'google_property' => config('seo.google.property'),
            'google_credentials' => config('seo.google.credentials'),
        ];
    }

    public function form(Schema $schema): Schema
    {
        $ai = app(AiClient::class);
        $google = app(GoogleClient::class);

        return $schema->components([
            Tabs::make('Ayarlar')->persistTabInQueryString()->tabs([

                Tab::make('AI sağlayıcı')->icon('heroicon-o-sparkles')->schema([
                    Section::make('OpenRouter')
                        ->description($ai->isConfigured()
                            ? '✅ Yapılandırılmış. Model: '.$ai->model()
                            : '⚠️ API anahtarı yok. .env dosyasındaki AI_API_KEY değerini doldurun; anahtar olmadan AI aksiyonları panelde gizlenir.')
                        ->schema([
                            Select::make('ai_model')
                                ->label('Model')
                                ->options(fn () => $ai->availableModels())
                                ->searchable()
                                ->placeholder(config('seo.ai.model'))
                                ->helperText('Liste OpenRouter\'dan çekilir. Boş bırakılırsa .env dosyasındaki AI_MODEL kullanılır.'),
                            Toggle::make('ai_refresh_meta')->label('Düşük tıklamalı sayfaların metasını haftalık tazele')
                                ->helperText('Search Console verisi gerektirir.'),
                            TextInput::make('ai_refresh_meta_limit')->label('Haftalık en fazla kaç sayfa')->numeric()->minValue(1)->maxValue(25),
                        ])->columns(1)->columnSpanFull(),
                ]),

                Tab::make('Blog otomasyonu')->icon('heroicon-o-newspaper')->schema([
                    Grid::make(2)->schema([
                        Toggle::make('blog_daily_enabled')->label('Günlük otomatik üretim')
                            ->helperText('Kapalıyken "AI ile konu üret" butonu elle çalışmaya devam eder.'),
                        Toggle::make('blog_auto_publish')->label('Zamanı gelen taslakları otomatik yayınla'),
                        TextInput::make('blog_daily_count')->label('Günlük yazı sayısı')->numeric()->minValue(1)->maxValue(20)
                            ->helperText('Üretim hacmi konu envanterine bağlıdır. Havuz tükenirse sistem durup haber verir.'),
                        TextInput::make('blog_topic_candidates')->label('İstenen aday konu sayısı')->numeric()->minValue(1)->maxValue(20)
                            ->helperText('Filtreye takılanlar için yedek. İstenen yazı sayısından fazla olmalı.'),
                        TextInput::make('blog_publish_hour_start')->label('Yayın saati — başlangıç')->numeric()->minValue(0)->maxValue(23),
                        TextInput::make('blog_publish_hour_end')->label('Yayın saati — bitiş')->numeric()->minValue(0)->maxValue(23),
                        TextInput::make('blog_min_words')->label('En az kelime')->numeric()->minValue(200)->maxValue(5000),
                        TextInput::make('blog_max_words')->label('En fazla kelime')->numeric()->minValue(300)->maxValue(8000),
                    ]),
                ]),

                Tab::make('Çakışma')->icon('heroicon-o-document-duplicate')->schema([
                    Section::make('İki ayrı eşik — bilerek farklı')
                        ->description('Tarama eşiği "çakışma olabilir" demektir ve yalnız listeler. Birleştirme eşiği "pratikte aynı yazı" demektir ve tek tuşla birleştirmeye izin verir. Tek eşik kullanmak içerik silmek demektir.')
                        ->schema([
                            TextInput::make('duplicate_scan_threshold')->label('Tarama eşiği')->numeric()->step(0.01)->minValue(0.01)->maxValue(1)
                                ->helperText('0.55 önerilir. Düşürürseniz alakasız çiftler de listelenir.'),
                            TextInput::make('duplicate_merge_threshold')->label('Otomatik birleştirme eşiği')->numeric()->step(0.01)->minValue(0.01)->maxValue(1)
                                ->helperText('0.90 önerilir. Tarama eşiğinin altına düşemez.'),
                            TextInput::make('duplicate_max_owners_per_keyword')->label('Kelime başına azami sahip')->numeric()->minValue(1)->maxValue(10)
                                ->helperText('Bu sayıya ulaşan kelime için yeni konu üretilmez.'),
                        ])->columns(3)->columnSpanFull(),
                ]),

                Tab::make('İç link motoru')->icon('heroicon-o-link')->schema([
                    Section::make()
                        ->description('Motor varsayılan olarak KAPALIDIR. Açmadan önce kuralları gözden geçirin.')
                        ->schema([
                            Grid::make(2)->schema([
                                Toggle::make('internal_links_enabled')->label('Motor açık')
                                    ->helperText('Kapalıyken hiçbir link basılmaz (kill switch).'),
                                Toggle::make('internal_links_auto_apply')->label('Gövdeye kalıcı işle')
                                    ->helperText('Kapalıyken linkler yalnız render anında gösterilir, veritabanı değişmez.'),
                                TextInput::make('internal_links_max_per_article')->label('Makale başına tavan')->numeric()->minValue(0)->maxValue(50),
                                TextInput::make('internal_links_max_total')->label('Mutlak tavan')->numeric()->minValue(0)->maxValue(100),
                                TextInput::make('internal_links_max_home')->label('Anasayfa tavanı')->numeric()->minValue(0)->maxValue(20),
                                TextInput::make('internal_links_min_score')->label('Öneri için en düşük skor')->numeric()->minValue(0)->maxValue(100),
                                TextInput::make('internal_links_batch_size')->label('Parti boyutu')->numeric()->minValue(1)->maxValue(500),
                            ]),
                        ])->columnSpanFull(),
                ]),

                Tab::make('Search Console')->icon('heroicon-o-magnifying-glass')->schema([
                    Section::make('Google servis hesabı')
                        ->description($google->isConfigured()
                            ? '✅ Bağlı. Servis hesabı: '.($google->serviceAccountEmail() ?: 'okunamadı')
                            : '⚠️ Bağlı değil. GSC aksiyonları gizli. Aşağıdan anahtar dosyasını yükleyin ve mülk adresini girin.')
                        ->schema([
                            FileUpload::make('google_credentials')
                                ->label('Servis hesabı anahtar dosyası (JSON)')
                                // storage/app/private altına yazılır; web'den ERİŞİLEMEZ.
                                ->disk('local')
                                ->directory(CredentialFile::DIRECTORY)
                                ->visibility('private')
                                ->acceptedFileTypes(['application/json', 'text/json', 'text/plain'])
                                ->maxSize(128)
                                ->downloadable(false)
                                ->openable(false)
                                ->previewable(false)
                                ->helperText(new HtmlString(
                                    'Google Cloud → <strong>IAM ve Yönetici → Hizmet Hesapları → Anahtarlar → Anahtar ekle → JSON</strong> '
                                    .'ile indirdiğiniz dosyayı buraya sürükleyin. Dosya siteye açık olmayan bir klasörde saklanır. '
                                    .'Yükledikten sonra <strong>Kaydet</strong>\'e basın; servis hesabının e-posta adresi burada görünecek.'
                                ))
                                ->columnSpanFull(),

                            TextInput::make('google_property')->label('GSC mülkü')
                                ->placeholder('sc-domain:ornek.com')
                                ->helperText('Search Console\'daki mülkle BİREBİR aynı olmalı. Domain mülkü "sc-domain:ornek.com", URL öneki mülkü "https://ornek.com/" biçimindedir. Uyuşmazlık 403 verir.')
                                ->columnSpanFull(),
                        ])->columns(1)->columnSpanFull(),

                    Section::make('Sonraki adım')
                        ->description('Dosya yüklendikten sonra bu adımları Google tarafında yapmanız gerekir.')
                        ->schema([
                            Text::make(new HtmlString($this->connectionSteps($google)))->columnSpanFull(),
                        ])
                        ->collapsible()
                        ->columnSpanFull(),
                ]),

                Tab::make('Skor & yönlendirme')->icon('heroicon-o-chart-bar')->schema([
                    Grid::make(2)->schema([
                        TextInput::make('score_target')->label('Hedef SEO skoru')->numeric()->minValue(1)->maxValue(100)
                            ->helperText('Bu skorun altındaki sayfalar gösterge panelinde işaretlenir.'),
                        Toggle::make('redirects_auto_apply')->label('Çok güvenli yönlendirme önerilerini otomatik uygula')
                            ->helperText('Yalnız %90 ve üzeri güvenli öneriler uygulanır.'),
                    ]),
                ]),
            ])->columnSpanFull(),
        ])->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        // Kimlik dosyası ayrı ele alınır: doğrulanmadan kaydedilmez.
        $state = $this->handleCredentialUpload($state);

        foreach ($state as $key => $value) {
            SeoConfig::set($key, is_array($value) ? json_encode($value) : $value);
        }

        Setting::flush();

        Notification::make()->title('SEO & AI ayarları kaydedildi')->success()->send();
    }

    /**
     * Yüklenen servis hesabı dosyasını doğrular.
     *
     * Geçersizse dosya SİLİNİR ve eski ayar korunur — bozuk bir yolu kaydetmek,
     * her GSC çağrısının sessizce düşmesine yol açar.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function handleCredentialUpload(array $state): array
    {
        $previous = SeoConfig::raw('google_credentials');
        $uploaded = $state['google_credentials'] ?? null;

        // Filament tek dosyalı yüklemeyi diziyle de verebilir.
        if (is_array($uploaded)) {
            $uploaded = reset($uploaded) ?: null;
        }

        if (blank($uploaded)) {
            // Dosya kaldırıldıysa eskisini de temizle.
            if (filled($previous)) {
                app(CredentialFile::class)->forget($previous);
            }

            $state['google_credentials'] = null;

            return $state;
        }

        $state['google_credentials'] = $uploaded;

        if ($uploaded === $previous) {
            return $state; // değişmedi, tekrar doğrulamaya gerek yok
        }

        try {
            $info = app(CredentialFile::class)->validate($uploaded);
        } catch (Throwable $e) {
            app(CredentialFile::class)->forget($uploaded);

            Notification::make()
                ->title('Anahtar dosyası kabul edilmedi')
                ->body($e->getMessage())
                ->danger()->persistent()->send();

            $state['google_credentials'] = $previous;

            return $state;
        }

        app(CredentialFile::class)->forget($previous);
        Cache::forget('google.token.'.md5(GoogleClient::SCOPE_WEBMASTERS.'|'.storage_path('app/private/'.$uploaded)));

        Notification::make()
            ->title('Anahtar dosyası doğrulandı')
            ->body('Servis hesabı: '.$info['client_email']."\n\nBu adresi Search Console'da mülkünüze kullanıcı olarak ekleyin.")
            ->success()->persistent()->send();

        return $state;
    }

    /** Bağlantıyı tamamlamak için Google tarafında yapılacaklar. */
    private function connectionSteps(GoogleClient $google): string
    {
        $email = $google->serviceAccountEmail();

        $emailBlock = $email
            ? '<code style="user-select:all">'.e($email).'</code>'
            : '<em>anahtar dosyası yüklendikten sonra burada görünecek</em>';

        return implode('', [
            '<ol style="list-style:decimal;padding-left:1.25rem;line-height:1.7">',
            '<li>Google Cloud\'da projenizde <strong>Search Console API</strong>\'yi etkinleştirin.</li>',
            '<li>Bir <strong>hizmet hesabı</strong> oluşturup JSON anahtarını indirin ve yukarıya yükleyin.</li>',
            '<li>Search Console → <strong>Ayarlar → Kullanıcılar ve izinler → Kullanıcı ekle</strong> adımında şu adresi ekleyin:<br>',
            $emailBlock,
            '</li>',
            '<li>Yetki olarak <strong>Tam</strong> seçin. URL Inspection için bu yeterlidir.',
            ' Indexing API kullanacaksanız <strong>Sahip</strong> gerekir ve sahiplik',
            ' <em>Ayarlar → Sahiplik doğrulama → Yetkilendirilmiş sahipler</em> ekranından verilir;',
            ' kullanıcı ekleme penceresinde "Sahip" seçeneği çıkmaz.</li>',
            '<li>Yukarıdaki <strong>GSC mülkü</strong> alanına Search Console\'daki mülkü birebir yazın.</li>',
            '</ol>',
            '<p style="margin-top:.75rem">Bağlantıyı denemek için terminalde: <code>php artisan seo:sync-search-console</code></p>',
        ]);
    }
}
