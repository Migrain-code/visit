<?php

namespace App\Filament\Pages;

use App\Filament\Support\FormHelpers;
use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class SiteSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Ayarlar';

    protected static ?string $navigationLabel = 'Site Ayarları';

    protected static ?string $title = 'Site Ayarları';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.site-settings';

    public static function canAccess(): bool
    {
        return auth()->user()?->managesSettings() ?? false;
    }

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(Setting::query()->pluck('value', 'key')->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Ayarlar')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Marka & İletişim')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('site_name')->label('Site adı')->required()->maxLength(60)->placeholder('RTEÜ Geziyor'),
                                    TextInput::make('site_tagline')->label('Slogan')->maxLength(80)->placeholder('Keşfet · Tanış · Yaşa'),
                                    TextInput::make('phone')
                                        ->label('Telefon numarası')
                                        ->placeholder(config('site.phone') ?: '+90 5xx xxx xx xx')
                                        ->helperText('"Ara" düğmelerinde kullanılır.')
                                        ->maxLength(30),
                                    TextInput::make('whatsapp')
                                        ->label('WhatsApp numarası')
                                        ->placeholder(config('site.whatsapp') ?: '905xxxxxxxxx')
                                        ->helperText('Ülke koduyla, boşluksuz: 905xxxxxxxxx. Boşsa telefon numarası kullanılır.')
                                        ->maxLength(30),
                                    TextInput::make('email')->label('E-posta adresi')->email()->maxLength(150),
                                    TextInput::make('notification_email')
                                        ->label('Bildirim e-postası')
                                        ->email()
                                        ->maxLength(150)
                                        ->helperText('Yeni iletişim talebi ve iş başvurusu bu adrese bildirilir (MAIL ayarları .env dosyasında).'),
                                    TextInput::make('instagram_handle')->label('Instagram kullanıcı adı')->maxLength(60)->placeholder('rteugeziyor')
                                        ->helperText('Ana sayfadaki "Bizi Instagram\'da takip et" bandında görünür.'),
                                    TextInput::make('instagram_url')->label('Instagram adresi')->url()->maxLength(255)->placeholder('https://www.instagram.com/rteugeziyor/'),
                                    TextInput::make('address')->label('Adres')->maxLength(200)->columnSpanFull(),
                                    Textarea::make('whatsapp_message')
                                        ->label('WhatsApp hazır mesajı')
                                        ->rows(2)
                                        ->helperText('{tur} yer tutucusu seçilen turun adıyla değiştirilir.')
                                        ->columnSpanFull(),
                                ]),
                                FormHelpers::imageUpload('logo', 'site', 'Logo')
                                    ->helperText('Üst barda görünür. Boşsa varsayılan yazı logosu kullanılır. Tercihen şeffaf PNG ya da SVG değil WebP/PNG.'),
                            ]),

                        Tab::make('Ana Sayfa')
                            ->icon('heroicon-o-home')
                            ->schema([
                                Section::make('Üst görsel (hero)')->schema([
                                    TextInput::make('hero_title')->label('Başlık')->required()->maxLength(60)->placeholder('Bu Haftanın'),
                                    TextInput::make('hero_highlight')->label('Vurgulu kelime')->maxLength(40)->placeholder('Rotaları')
                                        ->helperText('Başlığın altında büyük, sarı ve el yazısı tarzında görünür.'),
                                    TextInput::make('hero_subtitle')->label('Alt metin')->maxLength(150)->placeholder('Yeni yerler, yeni insanlar, unutulmaz anılar seni bekliyor.')->columnSpanFull(),
                                    TextInput::make('hero_cta_text')->label('Düğme yazısı')->maxLength(40)->placeholder('Keşfetmeye Başla'),
                                    TextInput::make('hero_note')->label('Köşe notu')->maxLength(60)->placeholder('Daha fazla gör, daha fazla yaşa...'),
                                    FormHelpers::imageUpload('hero_image', 'site', 'Hero görseli')->columnSpanFull(),
                                ])->columns(2),

                                Section::make('Tur bölümü')->schema([
                                    TextInput::make('tours_title')->label('Bölüm başlığı')->maxLength(60)->placeholder('Haftalık Turlar'),
                                    TextInput::make('tours_empty_text')->label('Tur yokken gösterilecek metin')->maxLength(200)
                                        ->placeholder('Yeni rotalar çok yakında. Instagram\'dan takipte kal!'),
                                ])->columns(2),

                                Section::make('Öne çıkanlar bandı')
                                    ->description('Tur kartlarının altındaki dört madde.')
                                    ->schema(collect(range(1, 4))->flatMap(fn (int $i) => [
                                        TextInput::make("feature_{$i}_title")->label("Madde {$i} başlık")->maxLength(40),
                                        TextInput::make("feature_{$i}_text")->label("Madde {$i} açıklama")->maxLength(60),
                                    ])->all())
                                    ->columns(2)->collapsible()->collapsed(),
                            ]),

                        Tab::make('Operasyon')
                            ->icon('heroicon-o-truck')
                            ->schema([
                                Section::make('Otomatik araç yerleştirme')
                                    ->description('Kalkışı yaklaşan turlarda araçsız kalan gruplar saat başı kendiliğinden boş koltuklara yerleştirilir. Yerleşmiş ve elle sabitlenmiş gruplara dokunulmaz; gruplar hiçbir zaman bölünmez.')
                                    ->schema([
                                        TextInput::make('auto_allocate_hours')
                                            ->label('Kalkışa kaç saat kala?')
                                            ->numeric()->minValue(0)->maxValue(720)
                                            ->placeholder('48')
                                            ->suffix('saat')
                                            ->helperText('Boş bırakılırsa 48 saat. 0 yazarsanız otomatik yerleştirme kapanır; dağıtımı yalnız panelden yaparsınız.'),
                                    ]),
                            ]),

                        Tab::make('SEO & Analitik')
                            ->icon('heroicon-o-magnifying-glass')
                            ->schema([
                                TextInput::make('meta_title')->label('Ana sayfa meta başlık')->maxLength(70),
                                Textarea::make('meta_description')->label('Ana sayfa meta açıklama')->rows(3)->maxLength(320),
                                TextInput::make('google_analytics_id')->label('Google Analytics ölçüm kimliği')->placeholder('G-XXXXXXXXXX')->maxLength(30),
                                TextInput::make('google_site_verification')->label('Google Search Console doğrulama kodu')->maxLength(100),
                                Textarea::make('map_embed')
                                    ->label('Google Haritalar iframe kodu (iletişim sayfası)')
                                    ->rows(4)
                                    ->helperText('Google Haritalar > Paylaş > Harita yerleştir bölümündeki <iframe> kodunu yapıştırın.'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();

        foreach ($state as $key => $value) {
            Setting::set($key, is_array($value) ? json_encode($value) : $value);
        }

        Setting::flush();

        Notification::make()
            ->title('Site ayarları kaydedildi')
            ->success()
            ->send();
    }
}
