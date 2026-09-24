<?php

namespace App\Filament\Resources\ReservationRequests\Schemas;

use App\Models\ReservationRequest;
use App\Models\TourDeparture;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReservationRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        $canAssign = fn (): bool => auth()->user()?->assignsRequests() ?? false;

        return $schema
            ->components([
                Section::make('Takip')->schema([
                    Select::make('status')
                        ->label('Durum')
                        ->options(ReservationRequest::STATUSES)
                        ->required()
                        ->native(false),
                    Select::make('assigned_to')
                        ->label('İlgilenen personel')
                        ->options(fn () => User::query()->active()->ordered()->pluck('name', 'id'))
                        ->searchable()
                        ->native(false)
                        ->disabled(fn () => ! $canAssign()),
                    Textarea::make('admin_notes')
                        ->label('Notlar (kişi görmez)')
                        ->rows(4)
                        ->helperText('Görüşme sonucu, verilen bilgi, özel durumlar vb.')
                        ->columnSpanFull(),
                ])->columns(2)->columnSpanFull(),

                Section::make('Kişi')->schema([
                    TextInput::make('name')->label('Ad Soyad')->required()->maxLength(100),
                    TextInput::make('phone')->label('Telefon')->required()->tel()->maxLength(30),
                    TextInput::make('email')->label('E-posta')->email()->maxLength(150),
                    TextInput::make('people_count')->label('Kişi sayısı')->numeric()->minValue(1)->maxValue(60)->required(),
                ])->columns(4)->columnSpanFull(),

                Section::make('Talep')->schema([
                    Select::make('tour_departure_id')
                        ->label('Tur')
                        ->options(fn () => TourDeparture::query()->orderByDesc('starts_at')->limit(100)->get()
                            ->mapWithKeys(fn (TourDeparture $d) => [$d->id => $d->label]))
                        ->searchable()
                        ->native(false),
                    DatePicker::make('preferred_date')->label('Tercih edilen tarih')->native(false)->displayFormat('d.m.Y'),
                    Textarea::make('message')->label('Mesaj')->rows(4)->columnSpanFull(),
                ])->columns(2)->columnSpanFull(),
            ]);
    }
}
