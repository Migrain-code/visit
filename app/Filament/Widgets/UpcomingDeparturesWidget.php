<?php

namespace App\Filament\Widgets;

use App\Enums\DepartureStatus;
use App\Filament\Resources\TourDepartures\TourDepartureResource;
use App\Models\TourDeparture;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingDeparturesWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Yaklaşan Seferler';

    public static function canView(): bool
    {
        return auth()->user()?->seesPassengers() ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            // Rehber yalnız kendi seferlerini görür.
            ->query(TourDeparture::query()->visibleTo(auth()->user())->upcoming()->withSeatStats()->withCount('vehicles')->with(['tour', 'guide'])->orderBy('starts_at'))
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('starts_at')->label('Kalkış')->dateTime('d.m.Y H:i')
                    ->description(fn (TourDeparture $record) => $record->starts_at->diffForHumans()),
                TextColumn::make('tour.title')->label('Tur')->weight('semibold')
                    ->description(fn (TourDeparture $record) => $record->code),
                TextColumn::make('vehicles_count')->label('Araç')
                    ->formatStateUsing(fn (int $state, TourDeparture $record) => $state === 0 ? 'Araç yok' : $state.' araç · '.$record->capacity.' koltuk')
                    ->badge()->color(fn (int $state) => $state === 0 ? 'danger' : 'gray'),
                TextColumn::make('occupancy')->label('Doluluk')
                    ->getStateUsing(fn (TourDeparture $record) => $record->sale_limit !== null
                        ? $record->seats_taken.' / '.$record->sale_limit
                        : $record->seats_taken.' yolcu')
                    ->badge()
                    ->color(fn (TourDeparture $record) => match (true) {
                        $record->sale_limit !== null && $record->seats_taken > $record->sale_limit => 'danger',
                        $record->is_full => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('waiting')->label('Yerleşmeyen')
                    ->getStateUsing(fn (TourDeparture $record) => $record->unassigned_passengers)
                    ->formatStateUsing(fn (int $state) => $state === 0 ? '—' : $state.' yolcu')
                    ->badge()->color(fn (int $state) => $state === 0 ? 'gray' : 'warning'),
                TextColumn::make('status')->label('Durum')->badge()
                    ->formatStateUsing(fn (DepartureStatus $state) => $state->label())
                    ->color(fn (DepartureStatus $state) => $state->color()),
            ])
            ->recordActions([
                Action::make('board')
                    ->label('Araç dağılımı')
                    ->icon('heroicon-o-squares-plus')
                    ->url(fn (TourDeparture $record) => TourDepartureResource::getUrl('allocation', ['record' => $record])),
            ])
            ->recordUrl(fn (TourDeparture $record) => TourDepartureResource::getUrl('view', ['record' => $record]))
            ->emptyStateHeading('Yaklaşan sefer yok');
    }
}
