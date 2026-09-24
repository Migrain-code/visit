<?php

namespace App\Filament\Resources\TourDepartures\Schemas;

use App\Enums\DepartureStatus;
use App\Filament\Support\FormHelpers;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TourDepartureForm
{
    /** Ana sayfadaki kart rozeti için hazır seçenekler; serbest metin de yazılabilir. */
    public const BADGES = ['Popüler', 'Doğa & Tarih', 'Manzara', 'Doğa Kaçamağı', 'Keşif Rotaları', 'Yeni', 'Son Koltuklar'];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tur')->schema([
                    TextInput::make('title')
                        ->label('Tur adı')
                        ->required()
                        ->maxLength(120)
                        ->placeholder('Batum')
                        ->columnSpan(2),
                    Select::make('status')
                        ->label('Durum')
                        ->options(DepartureStatus::options())
                        ->default(DepartureStatus::Open->value)
                        ->required()
                        ->native(false)
                        ->helperText('Yalnız "Kayıt açık" turlara yolcu eklenebilir.'),
                    DateTimePicker::make('starts_at')
                        ->label('Kalkış tarihi ve saati')
                        ->required()
                        ->native(false)
                        ->seconds(false)
                        ->displayFormat('d.m.Y H:i')
                        ->minutesStep(5),
                    DatePicker::make('ends_on')
                        ->label('Dönüş tarihi')
                        ->native(false)
                        ->displayFormat('d.m.Y')
                        // Kalkış saati olan bir günle karşılaştırılır: aynı gün geçerlidir.
                        ->rule(fn (Get $get) => filled($get('starts_at'))
                            ? 'after_or_equal:'.\Illuminate\Support\Carbon::parse($get('starts_at'))->toDateString()
                            : 'nullable')
                        ->validationMessages(['after_or_equal' => 'Dönüş tarihi kalkış gününden önce olamaz.'])
                        ->helperText('Günübirlikse boş bırakın.'),
                    TextInput::make('price')
                        ->label('Kişi başı fiyat (₺)')
                        ->numeric()->minValue(0)->step('0.01')
                        ->placeholder('1250'),
                    TextInput::make('meeting_point')
                        ->label('Kalkış yeri')
                        ->maxLength(255)
                        ->placeholder('RTEÜ Zihni Derin Yerleşkesi önü')
                        ->columnSpan(2),
                    TextInput::make('quota')
                        ->label('Kontenjan (isteğe bağlı)')
                        ->numeric()->minValue(1)->maxValue(999)
                        ->helperText('Boşsa araçların toplam koltuğu kadar kayıt alınır.'),
                ])->columns(3)->columnSpanFull(),

                Section::make('Sitede görünüm')
                    ->description('Ana sayfadaki tur kartı. Kartların sırası "Tüm Turlar" listesinde sürüklenerek değiştirilir.')
                    ->schema([
                        FormHelpers::imageUpload('image', 'tours', 'Kart görseli')->columnSpan(2),
                        Select::make('badge')
                            ->label('Rozet')
                            ->options(array_combine(self::BADGES, self::BADGES))
                            ->native(false)
                            ->searchable()
                            ->placeholder('Rozet yok'),
                        TextInput::make('image_alt')->label('Görsel açıklaması')->maxLength(150)
                            ->helperText('Görme engelliler ve arama motorları için kısa açıklama.'),
                        Textarea::make('short_description')
                            ->label('Kısa açıklama')
                            ->rows(2)
                            ->maxLength(300)
                            ->placeholder('Karadeniz\'in incisi, farklı bir ülkede yeni bir deneyim.')
                            ->columnSpan(2),
                        RichEditor::make('description')
                            ->label('Detaylı açıklama')
                            ->toolbarButtons(['bold', 'italic', 'bulletList', 'orderedList', 'link', 'undo', 'redo'])
                            ->helperText('Kartta "Detay" açılınca görünür: program, dahil olanlar, notlar.')
                            ->columnSpanFull(),
                        Toggle::make('is_public')
                            ->label('Web sitesinde göster')
                            ->default(true)
                            ->inline(false),
                    ])->columns(3)->columnSpanFull(),

                // Yalnız yeni kayıtta: araçlar sonradan Araç Liste Sihirbazı'ndan yönetilir.
                Section::make('Araçlar')
                    ->description('Bu tura atanacak araçları seçin. Plaka, şoför, ücret ve rehberi Araç Liste Sihirbazı\'ndan girersiniz.')
                    ->schema([
                        Select::make('vehicle_ids')
                            ->label('Filodan araç seç')
                            ->options(fn () => Vehicle::query()->active()->ordered()->get()->mapWithKeys(fn (Vehicle $v) => [$v->id => $v->label]))
                            ->multiple()
                            ->native(false)
                            ->dehydrated(false)
                            ->helperText('Gruplar bu araçların koltuk sayısına göre, BÖLÜNMEDEN yerleştirilir.'),
                    ])
                    ->visibleOn('create')
                    ->columnSpanFull(),

                Section::make('Görevli ve notlar')->schema([
                    Select::make('guide_id')
                        ->label('Tur rehberi')
                        ->options(fn () => User::query()->active()->ordered()->pluck('name', 'id'))
                        ->searchable()
                        ->native(false)
                        ->helperText('Yetkisi olmayan personel yalnız rehberi olduğu turları görür.'),
                    Textarea::make('notes')->label('Operasyon notları')->rows(3)->columnSpan(2),
                ])->columns(3)->columnSpanFull(),
            ]);
    }
}
