<?php

namespace App\Filament\Resources\TourDepartures\Schemas;

use App\Models\TourDeparture;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TourDepartureInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Doluluk')->schema([
                    TextEntry::make('capacity')->label('Araç kapasitesi')
                        ->formatStateUsing(fn (int $state) => $state > 0 ? $state.' koltuk' : 'Araç atanmadı')
                        ->color(fn (int $state) => $state > 0 ? null : 'danger'),
                    TextEntry::make('seats_taken')->label('Kayıtlı yolcu')
                        ->formatStateUsing(fn (int $state) => $state.' kişi'),
                    TextEntry::make('empty_seats')->label('Boş koltuk')->placeholder('-')
                        ->formatStateUsing(fn (?int $state) => $state === null ? null : $state.' koltuk')
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
                    TextEntry::make('vehicles_count')->label('Araç')
                        ->getStateUsing(fn (TourDeparture $record) => $record->vehicles()->count())
                        ->formatStateUsing(fn (int $state) => $state.' araç'),
                ])->columns(5)->columnSpanFull(),

                Section::make('Tur')->schema([
                    ImageEntry::make('image')->hiddenLabel()->disk('public')->height(120)->columnSpan(1)
                        ->defaultImageUrl(asset('images/placeholder.svg')),
                    TextEntry::make('starts_at')->label('Kalkış')->dateTime('d.m.Y H:i'),
                    TextEntry::make('ends_on')->label('Dönüş')->date('d.m.Y')->placeholder('Günübirlik'),
                    TextEntry::make('price_label')->label('Kişi başı fiyat')->placeholder('-'),
                    TextEntry::make('meeting_point')->label('Kalkış yeri')->placeholder('-'),
                    TextEntry::make('guide.name')->label('Tur rehberi')->placeholder('Atanmadı'),
                    TextEntry::make('status')->label('Durum')->badge()
                        ->formatStateUsing(fn ($state) => $state->label())
                        ->color(fn ($state) => $state->color()),
                    TextEntry::make('badge')->label('Rozet')->placeholder('-')->badge()->color('warning'),
                    IconEntry::make('is_public')->label('Sitede görünür')->boolean(),
                    TextEntry::make('short_description')->label('Kısa açıklama')->placeholder('-')->columnSpanFull(),
                ])->columns(4)->columnSpanFull(),

                Section::make('Kasa özeti')
                    ->description('Yolcu geliri kayıtlı yolcu × kişi başı fiyattır. Ayrıntılar ve ekstra hareketler aşağıdaki sekmelerde.')
                    ->schema([
                        TextEntry::make('passenger_revenue')->label('Yolcu geliri')->formatStateUsing(fn ($state) => money_label($state)),
                        TextEntry::make('vehicle_cost')->label('Araç gideri')->formatStateUsing(fn ($state) => money_label($state)),
                        TextEntry::make('commission_total')->label('Komisyon')->formatStateUsing(fn ($state) => money_label($state)),
                        TextEntry::make('extra_income')->label('Ekstra gelir')->formatStateUsing(fn ($state) => money_label($state)),
                        TextEntry::make('extra_expense')->label('Ekstra gider')->formatStateUsing(fn ($state) => money_label($state)),
                        TextEntry::make('net')->label('Kasaya kalan')->badge()
                            ->formatStateUsing(fn ($state) => money_label($state))
                            ->color(fn ($state) => (float) $state >= 0 ? 'success' : 'danger'),
                    ])
                    ->columns(6)
                    ->columnSpanFull()
                    ->visible(fn () => auth()->user()?->viewsReports() ?? false),

                Section::make('Operasyon notları')
                    ->schema([TextEntry::make('notes')->hiddenLabel()->placeholder('-')])
                    ->visible(fn (TourDeparture $record) => filled($record->notes))
                    ->columnSpanFull(),
            ]);
    }
}
