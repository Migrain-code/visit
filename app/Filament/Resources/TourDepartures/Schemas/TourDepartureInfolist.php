<?php

namespace App\Filament\Resources\TourDepartures\Schemas;

use App\Models\TourDeparture;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TourDepartureInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sefer')->schema([
                    TextEntry::make('tour.title')->label('Tur')->weight('semibold'),
                    TextEntry::make('code')->label('Sefer kodu')->badge()->color('gray')->copyable(),
                    TextEntry::make('status')->label('Durum')->badge()
                        ->formatStateUsing(fn ($state) => $state->label())
                        ->color(fn ($state) => $state->color()),
                    TextEntry::make('starts_at')->label('Kalkış')->dateTime('d.m.Y H:i'),
                    TextEntry::make('ends_on')->label('Dönüş')->date('d.m.Y')->placeholder('-'),
                    TextEntry::make('meeting_point')->label('Buluşma yeri')->placeholder('-'),
                    TextEntry::make('guide.name')->label('Rehber')->placeholder('Atanmadı'),
                    TextEntry::make('price_label')->label('Kişi başı fiyat')->placeholder('-'),
                    IconEntry::make('is_public')->label('Sitede görünür')->boolean(),
                ])->columns(3)->columnSpanFull(),

                Section::make('Doluluk')->schema([
                    TextEntry::make('capacity')->label('Araç kapasitesi')
                        ->formatStateUsing(fn (int $state) => $state > 0 ? $state.' koltuk' : 'Araç atanmadı')
                        ->color(fn (int $state) => $state > 0 ? null : 'danger'),
                    TextEntry::make('seats_taken')->label('Kayıtlı yolcu')
                        ->formatStateUsing(fn (int $state) => $state.' kişi'),
                    TextEntry::make('seats_left')->label('Boş koltuk')->placeholder('-')
                        ->badge()
                        ->color(fn (?int $state) => match (true) {
                            $state === null => 'gray',
                            $state === 0 => 'danger',
                            $state <= 5 => 'warning',
                            default => 'success',
                        }),
                    TextEntry::make('unassigned_passengers')->label('Araca yerleşmemiş')
                        ->badge()
                        ->formatStateUsing(fn (int $state) => $state === 0 ? 'Herkes yerleşti' : $state.' yolcu bekliyor')
                        ->color(fn (int $state) => $state === 0 ? 'success' : 'warning'),
                    TextEntry::make('allocated_at')->label('Son dağıtım')->dateTime('d.m.Y H:i')->placeholder('Henüz yapılmadı'),
                ])->columns(5)->columnSpanFull(),

                Section::make('Operasyon notları')
                    ->schema([TextEntry::make('notes')->hiddenLabel()->placeholder('-')])
                    ->visible(fn (TourDeparture $record) => filled($record->notes))
                    ->columnSpanFull(),
            ]);
    }
}
