<?php

namespace App\Filament\Resources\TourDepartures\Tables;

use App\Enums\DepartureStatus;
use App\Filament\Resources\TourDepartures\TourDepartureResource;
use App\Models\TourDeparture;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TourDeparturesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('starts_at')->label('Kalkış')->dateTime('d.m.Y H:i')->sortable()
                    ->description(fn (TourDeparture $record) => $record->starts_at?->translatedFormat('l')),
                TextColumn::make('tour.title')->label('Tur')->searchable()->weight('semibold')
                    ->description(fn (TourDeparture $record) => $record->code),
                TextColumn::make('vehicles_count')->label('Araç')->counts('vehicles')
                    ->formatStateUsing(fn (int $state, TourDeparture $record) => $state === 0 ? 'Araç yok' : $state.' araç · '.$record->capacity.' koltuk')
                    ->badge()
                    ->color(fn (int $state) => $state === 0 ? 'danger' : 'gray'),
                TextColumn::make('seats_taken')->label('Doluluk')
                    ->getStateUsing(fn (TourDeparture $record) => $record->sale_limit !== null
                        ? $record->seats_taken.' / '.$record->sale_limit
                        : $record->seats_taken.' yolcu')
                    ->badge()
                    ->color(fn (TourDeparture $record) => match (true) {
                        $record->sale_limit !== null && $record->seats_taken > $record->sale_limit => 'danger',
                        $record->is_full => 'warning',
                        default => 'success',
                    })
                    ->tooltip(fn (TourDeparture $record) => $record->sale_limit !== null && $record->seats_taken > $record->sale_limit
                        ? 'Kayıtlı yolcu kapasiteyi aşıyor: araç ekleyin.'
                        : null),
                TextColumn::make('waiting')->label('Yerleşmeyen')
                    ->getStateUsing(fn (TourDeparture $record) => $record->unassigned_passengers)
                    ->formatStateUsing(fn (int $state) => $state === 0 ? '—' : $state.' yolcu')
                    ->badge()
                    ->color(fn (int $state) => $state === 0 ? 'gray' : 'warning')
                    ->toggleable(),
                TextColumn::make('guide.name')->label('Rehber')->placeholder('-')->toggleable(),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (DepartureStatus $state) => $state->label())
                    ->color(fn (DepartureStatus $state) => $state->color()),
            ])
            ->filters([
                Filter::make('upcoming')
                    ->label('Yalnız yaklaşan seferler')
                    ->query(fn (Builder $query) => $query->upcoming())
                    ->default(),
                SelectFilter::make('tour_id')->label('Tur')->relationship('tour', 'title')->preload()->searchable(),
                SelectFilter::make('status')->label('Durum')->options(DepartureStatus::options()),
            ])
            ->recordActions([
                Action::make('allocation')
                    ->label('Araç dağılımı')
                    ->icon('heroicon-o-squares-plus')
                    ->color('primary')
                    ->url(fn (TourDeparture $record) => TourDepartureResource::getUrl('allocation', ['record' => $record])),
                ViewAction::make()->label('Aç'),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->recordUrl(fn (TourDeparture $record) => TourDepartureResource::getUrl('view', ['record' => $record]))
            ->defaultSort('starts_at');
    }
}
