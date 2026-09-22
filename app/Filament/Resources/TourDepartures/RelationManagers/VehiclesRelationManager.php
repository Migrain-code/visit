<?php

namespace App\Filament\Resources\TourDepartures\RelationManagers;

use App\Models\DepartureVehicle;
use App\Models\Vehicle;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Sefere atanan araçlar. Hazır araç listesinden seçilir; koltuk sayısı filodan kopyalanır
 * ve bu sefere özel değiştirilebilir (örn. rehber için koltuk ayırmak).
 */
class VehiclesRelationManager extends RelationManager
{
    protected static string $relationship = 'vehicles';

    protected static ?string $title = 'Araçlar';

    protected static ?string $modelLabel = 'araç';

    protected static ?string $pluralModelLabel = 'araçlar';

    /** "Özet" bir görüntüleme sayfası olsa da araçlar buradan yönetilir; yetkiyi ilke belirler. */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('vehicle_id')
                ->label('Hazır araç listesinden seç')
                ->options(fn () => Vehicle::query()->active()->ordered()->get()->mapWithKeys(fn (Vehicle $v) => [$v->id => $v->label]))
                ->searchable()
                ->native(false)
                ->live()
                ->afterStateUpdated(function (Set $set, ?string $state) {
                    if (! $vehicle = Vehicle::find($state)) {
                        return;
                    }

                    $set('name', $vehicle->name);
                    $set('seat_count', $vehicle->seat_count);
                    $set('plate', $vehicle->plate);
                    $set('driver_name', $vehicle->driver_name);
                    $set('driver_phone', $vehicle->driver_phone);
                })
                ->helperText('Seçince aşağıdaki alanlar dolar; bu sefere özel değiştirebilirsiniz. Aynı araç tipi birden çok kez eklenebilir.')
                ->columnSpanFull(),
            TextInput::make('name')->label('Araç adı')->required()->maxLength(100),
            TextInput::make('plate')->label('Plaka')->maxLength(20),
            TextInput::make('seat_count')->label('Yolcu koltuğu')->required()->numeric()->minValue(1)->maxValue(99)
                ->helperText('Şoför hariç.'),
            TextInput::make('reserved_seats')->label('Görevliye ayrılan koltuk')->numeric()->minValue(0)->default(0)
                ->lt('seat_count')
                ->helperText('Rehber vb. için. Bu koltuklara yolcu yerleştirilmez.'),
            TextInput::make('driver_name')->label('Şoför')->maxLength(100),
            TextInput::make('driver_phone')->label('Şoför telefonu')->tel()->maxLength(30),
            Textarea::make('notes')->label('Not')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn ($query) => $query->with('groups'))
            ->columns([
                TextColumn::make('name')->label('Araç')->weight('semibold')
                    ->description(fn (DepartureVehicle $record) => $record->plate),
                TextColumn::make('seat_count')->label('Koltuk')
                    ->formatStateUsing(fn (DepartureVehicle $record) => $record->reserved_seats > 0
                        ? $record->usable_seats.' yolcu + '.$record->reserved_seats.' görevli'
                        : $record->seat_count.' koltuk'),
                TextColumn::make('occupied_seats')->label('Dolu')
                    ->formatStateUsing(fn (DepartureVehicle $record) => $record->occupied_seats.' / '.$record->usable_seats)
                    ->badge()
                    ->color(fn (DepartureVehicle $record) => match (true) {
                        $record->occupied_seats > $record->usable_seats => 'danger',
                        $record->occupied_seats === $record->usable_seats => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('driver_name')->label('Şoför')->placeholder('-')
                    ->description(fn (DepartureVehicle $record) => $record->driver_phone),
            ])
            ->headerActions([
                CreateAction::make()->label('Araç ata')->modalHeading('Sefere araç ata'),
            ])
            ->recordActions([
                EditAction::make()->after(fn (DepartureVehicle $record) => $this->warnIfReleased($record)),
                DeleteAction::make()
                    ->modalDescription('Araç seferden çıkarılır; içindeki gruplar SİLİNMEZ, yerleşmeyi bekleyenlere döner.'),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->emptyStateHeading('Bu sefere araç atanmadı')
            ->emptyStateDescription('Grupların dağıtılabilmesi için hazır araç listesinden en az bir araç atayın.');
    }

    /** Koltuk azaltılınca araç taştıysa gruplar boşa çıkar; kullanıcı bundan haberdar edilir. */
    private function warnIfReleased(DepartureVehicle $vehicle): void
    {
        if ($vehicle->wasChanged(['seat_count', 'reserved_seats']) && $vehicle->groups()->doesntExist() && $this->getOwnerRecord()->unassigned_passengers > 0) {
            Notification::make()
                ->title('Araçtaki gruplar yerleşmeyi bekleyenlere alındı')
                ->body('Koltuk sayısı azaldığı için gruplar bu araca sığmıyordu. "Araç Dağılımı" sekmesinden yeniden dağıtın.')
                ->warning()->persistent()->send();
        }
    }
}
