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

/**
 * Yolcu Ekle: turu seç, yolcuları alt alta gir, kaydet → bir grup olur.
 *
 * Grup adı otomatik ("Grup 1", "Grup 2"...), iletişim kişisi ilk yolcudur.
 * Tek ekranda girilen yolcular birlikte yolculuk eder: grup bölünmez.
 */
class TourGroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Tur')->schema([
                    Select::make('tour_departure_id')
                        ->label('Tur')
                        ->options(fn (?TourGroup $record) => static::departureOptions($record))
                        ->required()
                        ->searchable()
                        ->native(false)
                        ->live()
                        ->helperText('Yalnız kayda açık, tarihi geçmemiş turlar listelenir.')
                        ->columnSpan(2),
                    Select::make('status')
                        ->label('Kayıt durumu')
                        ->options(GroupStatus::options())
                        ->default(GroupStatus::Confirmed->value)
                        ->required()
                        ->native(false)
                        ->live()
                        ->helperText('Opsiyon da koltuk tutar; kimlik bilgileri sonradan tamamlanabilir.'),
                    TextInput::make('name')
                        ->label('Grup adı')
                        ->maxLength(100)
                        ->placeholder('Boş bırakılırsa "Grup 1", "Grup 2"... verilir')
                        ->columnSpanFull(),
                ])->columns(3)->columnSpanFull(),

                Section::make('Yolcular')
                    ->description('Her yolcu için bir kart doldurun; "+ Yolcu ekle" ile yenisini açın. Buradaki herkes aynı araçta yolculuk eder.')
                    ->schema([
                        Repeater::make('passengers')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('sort_order')
                            ->schema([
                                TextInput::make('first_name')->label('Ad')->required()->maxLength(60)->autocomplete('off'),
                                TextInput::make('last_name')->label('Soyad')->required()->maxLength(60)->autocomplete('off'),
                                TextInput::make('tc_no')
                                    ->label('T.C. kimlik no')
                                    ->mask('99999999999')
                                    ->length(11)
                                    ->inputMode('numeric')
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
                                TextInput::make('phone')->label('Telefon')->tel()->maxLength(30)->placeholder('05xx xxx xx xx'),
                                TextInput::make('pickup_point')->label('Nereden binecek?')->maxLength(150)->placeholder('Yerleşke önü, Otogar...'),
                                Select::make('gender')->label('Cinsiyet')->options(Gender::options())->native(false)
                                    ->required(fn (Get $get) => static::identityRequired($get)),
                                TextInput::make('age')->label('Yaş')->numeric()->minValue(0)->maxValue(120)->inputMode('numeric'),
                                TextInput::make('notes')->label('Not')->maxLength(255)->placeholder('Koltuk tercihi, sağlık durumu...')->columnSpan(2),
                                Toggle::make('is_foreign')->label('Yabancı uyruklu (pasaportla)')->live()->columnSpanFull(),
                            ])
                            ->columns(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->itemLabel(fn (array $state): ?string => trim(($state['first_name'] ?? '').' '.($state['last_name'] ?? '')) ?: 'Yeni yolcu')
                            ->addActionLabel('+ Yolcu ekle')
                            ->addActionAlignment('center')
                            ->minItems(1)
                            ->defaultItems(1)
                            ->maxItems(60)
                            ->collapsible()
                            ->cloneable()
                            ->columnSpanFull(),
                    ])->columnSpanFull(),

                Section::make('Ödeme ve notlar')->schema([
                    TextInput::make('total_price')->label('Toplam tutar')->numeric()->minValue(0)->step('0.01')->suffix('₺')
                        ->placeholder(function (Get $get) {
                            $price = TourDeparture::query()->find($get('tour_departure_id'))?->effective_price;
                            $count = count((array) $get('passengers'));

                            return $price !== null && $count > 0 ? money_label($price * $count).' ('.$count.' × '.money_label($price).')' : null;
                        })
                        ->helperText('Boş bırakılırsa ödeme takibi yapılmaz.'),
                    TextInput::make('paid_amount')->label('Alınan ödeme')->numeric()->minValue(0)->step('0.01')->suffix('₺')->default(0),
                    Textarea::make('notes')->label('Grup notu')->rows(2)->columnSpanFull(),
                ])->columns(2)->columnSpanFull()->collapsible(),
            ]);
    }

    /** Kesin kayıtta kimlik ve cinsiyet zorunludur; opsiyonda sonradan tamamlanabilir. */
    private static function identityRequired(Get $get): bool
    {
        return $get('../../status') !== GroupStatus::Pending->value;
    }

    /** @return array<int, string> */
    private static function departureOptions(?TourGroup $record): array
    {
        return TourDeparture::query()
            ->withSeatStats()
            ->where(function (Builder $query) use ($record) {
                $query->where(fn (Builder $q) => $q->where('status', 'open')->where('starts_at', '>=', now()->startOfDay()));

                // Düzenlemede grubun mevcut turu, kapalı ya da geçmiş olsa da listede kalır.
                if ($record?->tour_departure_id) {
                    $query->orWhere('tour_departures.id', $record->tour_departure_id);
                }
            })
            ->orderBy('starts_at')
            ->get()
            ->mapWithKeys(fn (TourDeparture $d) => [
                $d->id => $d->starts_at->format('d.m.Y').' · '.$d->title
                    .($d->empty_seats !== null ? ' · '.$d->empty_seats.' boş koltuk' : ' · araç atanmadı'),
            ])
            ->all();
    }

    /** Aynı kişi aynı tura iki kez kaydedilemez (başka grupta da olsa). */
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
                ->with('group:id,name,contact_name')
                ->first();

            if ($duplicate) {
                $fail("Bu T.C. kimlik numarası bu turda zaten kayıtlı ({$duplicate->group?->name} · {$duplicate->group?->contact_name}).");
            }
        };
    }
}
