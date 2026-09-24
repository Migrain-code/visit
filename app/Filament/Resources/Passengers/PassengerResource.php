<?php

namespace App\Filament\Resources\Passengers;

use App\Enums\Gender;
use App\Filament\Resources\Passengers\Pages\ManagePassengers;
use App\Filament\Resources\TourGroups\TourGroupResource;
use App\Models\Passenger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Yolcu arama: "bu kişi hangi tura, hangi grupla kayıtlı?" sorusunun cevabı.
 *
 * SALT OKUNUR. Yolcu bilgisi yalnız grubunun içinden düzenlenir; böylece grubun
 * büyüklüğü ve araç uyumu tek bir yerden korunur.
 */
class PassengerResource extends Resource
{
    protected static ?string $model = Passenger::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Operasyon';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Yolcu';

    protected static ?string $pluralModelLabel = 'Yolcular';

    protected static ?string $slug = 'yolcular';

    public static function canCreate(): bool
    {
        return false;
    }

    /** Yetkisiz hesap (rehber) yalnız kendi turlarının yolcularını görür. */
    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->with(['group.departure', 'group.vehicle'])
            ->when($user?->isGuideOnly(), fn (Builder $q) => $q->whereHas('group.departure', fn (Builder $d) => $d->visibleTo($user)));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('last_name')->label('Yolcu')
                    ->formatStateUsing(fn (Passenger $record) => $record->full_name)
                    ->searchable(['first_name', 'last_name'])
                    ->sortable()
                    ->weight('semibold'),
                // Listede maskeli; tam numara grubun içinde görünür. Arama TAM numarayla yapılır.
                TextColumn::make('tc_no')->label('Kimlik')
                    ->formatStateUsing(fn (Passenger $record) => $record->masked_identity)
                    ->getStateUsing(fn (Passenger $record) => $record->identity)
                    ->placeholder('-')
                    ->searchable(query: fn (Builder $query, string $search) => $query
                        ->where('tc_no', $search)->orWhere('passport_no', $search)),
                TextColumn::make('phone')->label('Telefon')->placeholder('-')->searchable(),
                TextColumn::make('pickup_point')->label('Biniş')->placeholder('-')->toggleable(),
                TextColumn::make('gender')->label('Cinsiyet')->placeholder('-')
                    ->formatStateUsing(fn (?Gender $state) => $state?->label())
                    ->toggleable(),
                TextColumn::make('group.name')->label('Grup')->badge()->color('gray'),
                TextColumn::make('group.departure.starts_at')->label('Tur')->date('d.m.Y')
                    ->description(fn (Passenger $record) => $record->group?->departure?->title),
                TextColumn::make('group.vehicle.name')->label('Araç')->placeholder('Yerleşmedi')->badge()
                    ->color(fn (?string $state) => $state ? 'success' : 'warning'),
            ])
            ->filters([
                Filter::make('upcoming')
                    ->label('Yalnız yaklaşan turlar')
                    ->query(fn (Builder $query) => $query->whereHas('group.departure', fn (Builder $q) => $q->upcoming()))
                    ->default(),
                SelectFilter::make('gender')->label('Cinsiyet')->options(Gender::options()),
            ])
            ->recordActions([
                Action::make('group')
                    ->label('Gruba git')
                    ->icon('heroicon-o-user-group')
                    ->url(fn (Passenger $record) => (auth()->user()?->can('update', $record->group) ?? false)
                        ? TourGroupResource::getUrl('edit', ['record' => $record->tour_group_id])
                        : TourGroupResource::getUrl('view', ['record' => $record->tour_group_id])),
            ])
            ->defaultSort('id', 'desc')
            ->searchPlaceholder('Ad, soyad, telefon ya da TAM kimlik no');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePassengers::route('/'),
        ];
    }
}
