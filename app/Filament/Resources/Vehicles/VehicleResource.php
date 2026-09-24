<?php

namespace App\Filament\Resources\Vehicles;

use App\Filament\Resources\Vehicles\Pages\ManageVehicles;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Hazır araç listesi (filo). Koltuk sayıları burada tanımlanır; turlara buradan araç seçilir.
 */
class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Operasyon';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'Araç';

    protected static ?string $pluralModelLabel = 'Araçlar';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Araç adı')
                ->required()
                ->maxLength(100)
                ->placeholder('19 Koltuklu Sprinter')
                ->helperText('Tura araç atarken bu adla görünür.'),
            TextInput::make('seat_count')
                ->label('Yolcu koltuğu sayısı')
                ->required()
                ->numeric()
                ->minValue(1)
                ->maxValue(99)
                ->helperText('Şoför HARİÇ, yolcuya satılabilen koltuk. Gruplar bu sayıya göre yerleştirilir.'),
            TextInput::make('plate')->label('Plaka')->maxLength(20)->placeholder('59 ABC 123'),
            TextInput::make('driver_name')->label('Şoför')->maxLength(100),
            TextInput::make('driver_phone')->label('Şoför telefonu')->tel()->maxLength(30),
            TextInput::make('sort_order')->label('Sıra')->numeric()->default(0),
            Textarea::make('notes')->label('Notlar')->rows(2)->columnSpanFull(),
            Toggle::make('is_active')->label('Kullanımda')->default(true)
                ->helperText('Kapatılan araç yeni turlarda seçilemez; geçmiş turlar etkilenmez.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Araç')->searchable()->sortable()->weight('semibold')
                    ->description(fn (Vehicle $record) => $record->plate),
                TextColumn::make('seat_count')->label('Koltuk')->sortable()->badge()->color('info')
                    ->formatStateUsing(fn (int $state) => $state.' koltuk'),
                TextColumn::make('driver_name')->label('Şoför')->placeholder('-')
                    ->description(fn (Vehicle $record) => $record->driver_phone),
                TextColumn::make('assignments_count')->label('Tur')->counts('assignments')->sortable()
                    ->tooltip('Bu aracın atandığı tur sayısı'),
                ToggleColumn::make('is_active')->label('Kullanımda')
                    ->disabled(fn () => ! (auth()->user()?->managesVehicles() ?? false)),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Kullanımda'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVehicles::route('/'),
        ];
    }
}
