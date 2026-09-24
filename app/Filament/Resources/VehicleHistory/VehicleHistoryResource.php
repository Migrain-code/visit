<?php

namespace App\Filament\Resources\VehicleHistory;

use App\Filament\Resources\TourDepartures\TourDepartureResource;
use App\Filament\Resources\VehicleHistory\Pages\ListVehicleHistory;
use App\Models\DepartureVehicle;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Araç Geçmişi: hangi araç hangi tura gitmiş, kaç yolcu taşımış, ne ödenmiş.
 *
 * SALT OKUNUR. Kaynak, turlara atanmış araç kayıtlarıdır (departure_vehicles);
 * plaka ve şoför tur anındaki hâliyle saklanır.
 */
class VehicleHistoryResource extends Resource
{
    protected static ?string $model = DepartureVehicle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Operasyon';

    protected static ?int $navigationSort = 7;

    protected static ?string $modelLabel = 'Araç Geçmişi';

    protected static ?string $pluralModelLabel = 'Araç Geçmişi';

    protected static ?string $slug = 'arac-gecmisi';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->seesPassengers() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withOccupancy()
            ->with(['departure', 'vehicle', 'guide'])
            ->whereHas('departure');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('departure.starts_at')->label('Tarih')->date('d.m.Y')->sortable(),
                TextColumn::make('departure.title')->label('Tur')->searchable()->weight('semibold')
                    ->url(fn (DepartureVehicle $record) => TourDepartureResource::getUrl('view', ['record' => $record->tour_departure_id])),
                TextColumn::make('name')->label('Araç')->searchable()
                    ->description(fn (DepartureVehicle $record) => $record->plate),
                TextColumn::make('plate')->label('Plaka')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('occupied_seats')->label('Yolcu')
                    ->getStateUsing(fn (DepartureVehicle $record) => $record->occupied_seats.' / '.$record->usable_seats)
                    ->badge()->color('info'),
                TextColumn::make('driver_name')->label('Şoför')->placeholder('-')
                    ->description(fn (DepartureVehicle $record) => $record->driver_phone),
                TextColumn::make('guide.name')->label('Rehber')->placeholder('-')->toggleable(),
                TextColumn::make('cost')->label('Ücret')->placeholder('-')
                    ->formatStateUsing(fn ($state) => money_label($state))
                    ->visible(fn () => auth()->user()?->viewsReports() ?? false)
                    ->summarize(Sum::make()->label('Toplam')->formatStateUsing(fn ($state) => money_label($state))),
                TextColumn::make('notes')->label('Not')->placeholder('-')->limit(40)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('vehicle_id')
                    ->label('Filo aracı')
                    ->options(fn () => Vehicle::query()->ordered()->get()->mapWithKeys(fn (Vehicle $v) => [$v->id => $v->label])),
                Filter::make('plate')
                    ->label('Plaka')
                    ->schema([\Filament\Forms\Components\TextInput::make('plate')->label('Plaka')])
                    ->query(fn (Builder $query, array $data) => $query->when($data['plate'] ?? null, fn (Builder $q, $plate) => $q->where('plate', 'like', '%'.$plate.'%'))),
                Filter::make('dates')
                    ->label('Tarih aralığı')
                    ->schema([
                        DatePicker::make('from')->label('Başlangıç')->native(false)->displayFormat('d.m.Y'),
                        DatePicker::make('until')->label('Bitiş')->native(false)->displayFormat('d.m.Y'),
                    ])
                    ->columns(2)
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereHas('departure', fn (Builder $d) => $d->whereDate('starts_at', '>=', $date)))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereHas('departure', fn (Builder $d) => $d->whereDate('starts_at', '<=', $date)))),
            ])
            ->recordActions([
                Action::make('tour')
                    ->label('Tura git')
                    ->icon('heroicon-o-map')
                    ->url(fn (DepartureVehicle $record) => TourDepartureResource::getUrl('view', ['record' => $record->tour_departure_id])),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Henüz hiçbir tura araç atanmadı');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicleHistory::route('/'),
        ];
    }
}
