<?php

namespace App\Filament\Resources\TourGroups\Schemas;

use App\Enums\Gender;
use App\Enums\GroupStatus;
use App\Models\Passenger;
use App\Models\TourDeparture;
use App\Models\TourGroup;
use App\Rules\TcKimlikNo;
use Closure;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class TourGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sefer')->schema([
                    Select::make('tour_departure_id')
                        ->label('Tur kaydı (sefer)')
                        ->options(fn (?TourGroup $record) => static::departureOptions($record))
                        ->required()
                        ->searchable()
                        ->native(false)
                        ->live()
                        ->helperText('Yalnız kayda açık, tarihi geçmemiş seferler listelenir.')
                        ->columnSpan(2),
                    Select::make('status')
                        ->label('Kayıt durumu')
                        ->options(GroupStatus::options())
                        ->default(GroupStatus::Confirmed->value)
                        ->required()
                        ->native(false)
                        ->live()
                        ->helperText('Opsiyon da koltuk tutar; yolcu kimlik bilgileri sonradan tamamlanabilir.'),
                    TextInput::make('name')
                        ->label('Grup adı (isteğe bağlı)')
                        ->maxLength(100)
                        ->placeholder('Yılmaz ailesi')
                        ->helperText('Boş bırakılırsa ilgili kişinin adı kullanılır.'),
                    TextInput::make('pickup_point')
                        ->label('Biniş noktası')
                        ->maxLength(255)
                        ->placeholder('Ardeşen, X Otel önü')
                        ->columnSpan(2),
                ])->columns(3)->columnSpanFull(),

                Section::make('İlgili kişi')
                    ->description('Grup adına görüşülecek kişi.')
                    ->schema([
                        TextInput::make('contact_name')->label('Ad Soyad')->required()->maxLength(100),
                        TextInput::make('contact_phone')->label('Telefon')->required()->tel()->maxLength(30),
                        TextInput::make('contact_email')->label('E-posta')->email()->maxLength(150),
                    ])->columns(3)->columnSpanFull(),

                Section::make('Yolcular')
                    ->description('Grubun büyüklüğü buradaki satır sayısıdır. Grup araçlara BÖLÜNMEDEN yerleştirilir: hepsi aynı araçta yolculuk eder.')
                    ->schema([
                        Repeater::make('passengers')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('sort_order')
                            ->schema([
                                TextInput::make('first_name')->label('Adı')->required()->maxLength(60),
                                TextInput::make('last_name')->label('Soyadı')->required()->maxLength(60),
                                TextInput::make('tc_no')
                                    ->label('T.C. kimlik no')
                                    ->mask('99999999999')
                                    ->length(11)
                                    ->rule(new TcKimlikNo)
                                    ->distinct()
                                    ->rule(fn (Get $get, $record): Closure => static::uniqueInDeparture($get('../../tour_departure_id'), $record))
                                    ->required(fn (Get $get) => static::identityRequired($get))
                                    ->hidden(fn (Get $get) => (bool) $get('is_foreign')),
                                TextInput::make('passport_no')
                                    ->label('Pasaport no')
                                    ->maxLength(30)
                                    ->required(fn (Get $get) => static::identityRequired($get))
                                    ->visible(fn (Get $get) => (bool) $get('is_foreign')),
                                TextInput::make('phone')->label('Telefon')->tel()->maxLength(30),
                                TextInput::make('age')->label('Yaş')->numeric()->minValue(0)->maxValue(120)
                                    ->required(fn (Get $get) => static::identityRequired($get)),
                                Select::make('gender')->label('Cinsiyet')->options(Gender::options())->native(false)
                                    ->required(fn (Get $get) => static::identityRequired($get)),
                                Toggle::make('is_foreign')->label('Yabancı uyruklu (pasaportla)')->live()->columnSpanFull(),
                            ])
                            // Geniş ekranda bir yolcu tek satıra sığar: kalabalık gruplarda form kısalır.
                            ->columns(['default' => 1, 'sm' => 2, 'md' => 3, 'xl' => 6])
                            ->itemLabel(fn (array $state): ?string => trim(($state['first_name'] ?? '').' '.($state['last_name'] ?? '')) ?: 'Yeni yolcu')
                            ->addActionLabel('Yolcu ekle')
                            ->minItems(1)
                            ->defaultItems(1)
                            ->maxItems(60)
                            ->collapsible()
                            ->columnSpanFull(),
                    ])->columnSpanFull(),

                Section::make('Ödeme ve notlar')->schema([
                    TextInput::make('total_price')->label('Toplam tutar')->numeric()->minValue(0)->step('0.01')
                        ->placeholder(function (Get $get) {
                            $price = TourDeparture::with('tour')->find($get('tour_departure_id'))?->effective_price;
                            $count = count((array) $get('passengers'));

                            return $price !== null && $count > 0 ? money_label($price * $count).' ('.$count.' × '.money_label($price).')' : null;
                        })
                        ->helperText('Boş bırakılırsa ödeme takibi yapılmaz.'),
                    TextInput::make('paid_amount')->label('Alınan ödeme')->numeric()->minValue(0)->step('0.01')->default(0),
                    Textarea::make('notes')->label('Notlar')->rows(3)->columnSpanFull()
                        ->placeholder('Koltuk tercihi, sağlık durumu, özel talepler...'),
                ])->columns(2)->columnSpanFull(),
            ]);
    }

    /** Kesin kayıtta kimlik, yaş ve cinsiyet zorunludur; opsiyonda sonradan tamamlanabilir. */
    private static function identityRequired(Get $get): bool
    {
        return $get('../../status') !== GroupStatus::Pending->value;
    }

    /** @return array<int, string> */
    private static function departureOptions(?TourGroup $record): array
    {
        return TourDeparture::query()
            ->withSeatStats()
            ->with('tour:id,title')
            ->where(function (Builder $query) use ($record) {
                $query->where(fn (Builder $q) => $q->where('status', 'open')->where('starts_at', '>=', now()->startOfDay()));

                // Düzenlemede grubun mevcut seferi, kapalı ya da geçmiş olsa da listede kalır.
                if ($record?->tour_departure_id) {
                    $query->orWhere('tour_departures.id', $record->tour_departure_id);
                }
            })
            ->orderBy('starts_at')
            ->get()
            ->mapWithKeys(fn (TourDeparture $d) => [
                $d->id => $d->starts_at->format('d.m.Y').' · '.$d->tour?->title
                    .($d->seats_left !== null ? ' · '.$d->seats_left.' boş koltuk' : ' · araç atanmadı'),
            ])
            ->all();
    }

    /** Aynı kişi aynı sefere iki kez kaydedilemez (başka grupta da olsa). */
    private static function uniqueInDeparture(mixed $departureId, mixed $record): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($departureId, $record): void {
            if (blank($value) || blank($departureId)) {
                return;
            }

            $duplicate = Passenger::query()
                ->where('tc_no', $value)
                ->when($record instanceof Passenger, fn (Builder $q) => $q->whereKeyNot($record->getKey()))
                ->whereHas('group', fn (Builder $q) => $q
                    ->where('tour_departure_id', $departureId)
                    ->where('status', '!=', GroupStatus::Cancelled->value))
                ->with('group:id,code,contact_name')
                ->first();

            if ($duplicate) {
                $fail("Bu T.C. kimlik numarası bu seferde zaten kayıtlı ({$duplicate->group?->code} · {$duplicate->group?->contact_name}).");
            }
        };
    }
}
