<?php

namespace App\Filament\Resources\Districts;

use App\Filament\Resources\Districts\Pages\CreateDistrict;
use App\Filament\Resources\Districts\Pages\EditDistrict;
use App\Filament\Resources\Districts\Pages\ListDistricts;
use App\Filament\Resources\Districts\Schemas\DistrictForm;
use App\Filament\Resources\Districts\Tables\DistrictsTable;
use App\Models\District;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DistrictResource extends Resource
{
    protected static ?string $model = District::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Bölgeler';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'İlçe';

    protected static ?string $pluralModelLabel = 'İlçeler';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return DistrictForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DistrictsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDistricts::route('/'),
            'create' => CreateDistrict::route('/create'),
            'edit' => EditDistrict::route('/{record}/edit'),
        ];
    }
}
