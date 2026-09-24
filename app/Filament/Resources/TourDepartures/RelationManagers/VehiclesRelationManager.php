<?php

namespace App\Filament\Resources\TourDepartures\RelationManagers;

use App\Filament\Pages\VehicleWizard;
use App\Models\DepartureVehicle;
use App\Models\User;
use App\Models\Vehicle;
use Filament\Actions\Action;
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
 * Tura atanan araçlar. Toplu giriş için Araç Liste Sihirbazı; burada tek tek düzenlenir.
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
                ->label('Filodan seç')
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
                ->helperText('Seçince aşağıdaki alanlar dolar; bu tura özel değiştirebilirsiniz.')
                ->columnSpanFull(),
            TextInput::make('name')->label('Araç adı')->required()->maxLength(100),
            TextInput::make('plate')->label('Plaka')->maxLength(20),
            TextInput::make('seat_count')->label('Yolcu koltuğu')->required()->numeric()->minValue(1)->maxValue(99)
                ->helperText('Şoför hariç.'),
            TextInput::make('reserved_seats')->label('Görevliye ayrılan koltuk')->numeric()->minValue(0)->default(0)
                ->lt('seat_count'),
            TextInput::make('driver_name')->label('Şoför(ler)')->maxLength(150)->placeholder('Ali Usta, Veli Usta'),
            TextInput::make('driver_phone')->label('Şoför telefonu')->tel()->maxLength(30),
            TextInput::make('cost')->label('Araç ücreti')->numeric()->minValue(0)->step('0.01')->suffix('₺'),
            Select::make('guide_id')->label('Araç rehberi')
                ->options(fn () => User::query()->active()->ordered()->pluck('name', 'id'))
                ->searchable()->native(false),
            Textarea::make('notes')->label('Not')->rows(2)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn ($query) => $query->with(['groups', 'guide']))
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
                TextColumn::make('guide.name')->label('Rehber')->placeholder('-'),
                TextColumn::make('cost')->label('Ücret')->placeholder('-')
                    ->formatStateUsing(fn ($state) => money_label($state)),
            ])
            ->headerActions([
                Action::make('wizard')
                    ->label('Sihirbazla düzenle')
                    ->icon('heroicon-o-sparkles')
                    ->color('gray')
                    ->url(fn () => VehicleWizard::getUrl(['tour' => $this->getOwnerRecord()->getKey()]))
                    ->visible(fn () => auth()->user()?->managesOperations() ?? false),
                CreateAction::make()->label('Araç ata')->modalHeading('Tura araç ata'),
            ])
            ->recordActions([
                EditAction::make()->after(fn (DepartureVehicle $record) => $this->warnIfReleased($record)),
                DeleteAction::make()
                    ->modalDescription('Araç turdan çıkarılır; içindeki gruplar SİLİNMEZ, yerleşmeyi bekleyenlere döner.'),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->emptyStateHeading('Bu tura araç atanmadı')
            ->emptyStateDescription('Grupların dağıtılabilmesi için Araç Liste Sihirbazı\'ndan ya da buradan en az bir araç atayın.');
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
