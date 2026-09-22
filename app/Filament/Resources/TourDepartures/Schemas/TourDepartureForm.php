<?php

namespace App\Filament\Resources\TourDepartures\Schemas;

use App\Enums\DepartureStatus;
use App\Models\Tour;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TourDepartureForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tur ve tarih')->schema([
                    Select::make('tour_id')
                        ->label('Tur')
                        ->options(fn () => Tour::query()->ordered()->pluck('title', 'id'))
                        ->required()
                        ->searchable()
                        ->native(false)
                        ->live()
                        ->columnSpan(2),
                    Select::make('status')
                        ->label('Durum')
                        ->options(DepartureStatus::options())
                        ->default(DepartureStatus::Open->value)
                        ->required()
                        ->native(false)
                        ->helperText('Yalnız "Kayıt açık" seferlere yeni grup eklenebilir.'),
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
                        ->afterOrEqual('starts_at')
                        ->helperText('Boş bırakılırsa turun süresinden hesaplanır.'),
                    TextInput::make('meeting_point')
                        ->label('Buluşma / kalkış yeri')
                        ->maxLength(255)
                        ->placeholder(fn (Get $get) => Tour::find($get('tour_id'))?->departure_point ?: 'Otelinizden alınış; Rize Merkez meydan'),
                ])->columns(3)->columnSpanFull(),

                // Yalnız yeni kayıtta: araçlar sonradan "Araçlar" sekmesinden yönetilir.
                Section::make('Araçlar')
                    ->description('Bu sefere atanacak araçları seçin. Aynı araçtan birden fazla gerekiyorsa ya da koltuk ayırmak istiyorsanız kayıttan sonra "Araçlar" sekmesini kullanın.')
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

                Section::make('Fiyat ve kontenjan')->schema([
                    TextInput::make('price')
                        ->label('Bu sefere özel kişi başı fiyat')
                        ->numeric()->minValue(0)->step('0.01')
                        ->placeholder(fn (Get $get) => ($tour = Tour::find($get('tour_id'))) && $tour->price !== null ? 'Tur fiyatı: '.$tour->price_label : null)
                        ->helperText('Boş bırakılırsa turun katalog fiyatı geçerlidir.'),
                    TextInput::make('quota')
                        ->label('Satış kontenjanı')
                        ->numeric()->minValue(1)->maxValue(999)
                        ->helperText('Boş bırakılırsa atanan araçların toplam koltuğu kadar kayıt alınır.'),
                    Toggle::make('is_public')
                        ->label('Web sitesindeki tur takviminde göster')
                        ->default(true)
                        ->inline(false),
                ])->columns(3)->columnSpanFull(),

                Section::make('Görevli ve notlar')->schema([
                    Select::make('guide_id')
                        ->label('Rehber')
                        ->options(fn () => User::query()->active()->ordered()->get()
                            ->mapWithKeys(fn (User $u) => [$u->id => $u->name.' · '.$u->role_label]))
                        ->searchable()
                        ->native(false)
                        ->helperText('"Rehber" rolündeki kullanıcı yalnız rehberi olduğu seferleri görür.'),
                    Textarea::make('notes')->label('Operasyon notları')->rows(3)->columnSpan(2),
                ])->columns(3)->columnSpanFull(),
            ]);
    }
}
