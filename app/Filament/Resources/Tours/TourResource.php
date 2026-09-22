<?php

namespace App\Filament\Resources\Tours;

use App\Filament\Resources\Tours\Pages\CreateTour;
use App\Filament\Resources\Tours\Pages\EditTour;
use App\Filament\Resources\Tours\Pages\ListTours;
use App\Filament\Resources\Tours\Schemas\TourForm;
use App\Filament\Resources\Tours\Tables\ToursTable;
use App\Models\Tour;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Tur kataloğu: web sitesinde yayınlanan tur sayfaları (program, fiyat, dahil olanlar).
 * Belirli bir tarihteki sefer ve yolcuları "Tur Kayıtları" altındadır.
 */
class TourResource extends Resource
{
    protected static ?string $model = Tour::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Turlar';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Tur';

    protected static ?string $pluralModelLabel = 'Turlar';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return TourForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ToursTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTours::route('/'),
            'create' => CreateTour::route('/create'),
            'edit' => EditTour::route('/{record}/edit'),
        ];
    }
}
