<?php

namespace App\Filament\Resources\TourGroups;

use App\Filament\Resources\TourGroups\Pages\CreateTourGroup;
use App\Filament\Resources\TourGroups\Pages\EditTourGroup;
use App\Filament\Resources\TourGroups\Pages\ListTourGroups;
use App\Filament\Resources\TourGroups\Pages\ViewTourGroup;
use App\Filament\Resources\TourGroups\Schemas\TourGroupForm;
use App\Filament\Resources\TourGroups\Tables\TourGroupsTable;
use App\Models\TourGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Gruplar: birlikte seyahat eden yolcular. Yolcu bilgileri (ad, soyad, TC, telefon,
 * yaş, cinsiyet) grubun içinde girilir. Grup araçlara BÖLÜNMEDEN yerleştirilir.
 */
class TourGroupResource extends Resource
{
    protected static ?string $model = TourGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Operasyon';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Grup';

    protected static ?string $pluralModelLabel = 'Gruplar';

    protected static ?string $slug = 'gruplar';

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record?->display_name;
    }

    public static function form(Schema $schema): Schema
    {
        return TourGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TourGroupsTable::configure($table);
    }

    /** Rehber yalnız kendi seferlerinin gruplarını görür (ilke tek kaydı, bu kapsam listeyi korur). */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTourGroups::route('/'),
            'create' => CreateTourGroup::route('/create'),
            'view' => ViewTourGroup::route('/{record}'),
            'edit' => EditTourGroup::route('/{record}/edit'),
        ];
    }
}
