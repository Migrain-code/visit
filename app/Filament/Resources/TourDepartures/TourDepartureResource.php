<?php

namespace App\Filament\Resources\TourDepartures;

use App\Filament\Resources\TourDepartures\Pages\CreateTourDeparture;
use App\Filament\Resources\TourDepartures\Pages\DepartureAllocation;
use App\Filament\Resources\TourDepartures\Pages\EditTourDeparture;
use App\Filament\Resources\TourDepartures\Pages\ListTourDepartures;
use App\Filament\Resources\TourDepartures\Pages\ViewTourDeparture;
use App\Filament\Resources\TourDepartures\RelationManagers\CommissionsRelationManager;
use App\Filament\Resources\TourDepartures\RelationManagers\GroupsRelationManager;
use App\Filament\Resources\TourDepartures\RelationManagers\LedgerRelationManager;
use App\Filament\Resources\TourDepartures\RelationManagers\VehiclesRelationManager;
use App\Filament\Resources\TourDepartures\Schemas\TourDepartureForm;
use App\Filament\Resources\TourDepartures\Schemas\TourDepartureInfolist;
use App\Filament\Resources\TourDepartures\Tables\TourDeparturesTable;
use App\Models\TourDeparture;
use BackedEnum;
use Filament\Pages\Enums\SubNavigationPosition;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Tüm Turlar: her tur tarihli tek bir gezidir. Araçlar buraya atanır, gruplar buraya
 * kaydedilir ve araçlara burada dağıtılır; komisyon ve kasa hareketi de buraya bağlanır.
 */
class TourDepartureResource extends Resource
{
    protected static ?string $model = TourDeparture::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static string|UnitEnum|null $navigationGroup = 'Operasyon';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Tur';

    protected static ?string $pluralModelLabel = 'Tüm Turlar';

    protected static ?string $navigationLabel = 'Tüm Turlar';

    protected static ?string $slug = 'turlar';

    /** Özet / Araç Dağılımı / Düzenle sekmeleri üstte durur; pano tam genişlikte kalır. */
    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    /** Bütün turları görmeyen ve hiçbir turun rehberi olmayan personelin menüsünde durmaz. */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->seesTourList() ?? false;
    }

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record?->label;
    }

    public static function form(Schema $schema): Schema
    {
        return TourDepartureForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TourDepartureInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TourDeparturesTable::configure($table);
    }

    /**
     * Yetkisiz hesap (rehber) yalnız kendi turlarını görür.
     *
     * İlke (TourDeparturePolicy) tek kayıt erişimini korur; bu kapsam listeyi korur.
     * İkisi birden gereklidir: yalnız listeyi filtrelemek, kayıt kimliğini bilen
     * birinin doğrudan adrese gitmesini engellemez.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user())->withSeatStats();
    }

    /** Yaklaşan ve henüz araçlara yerleşmemiş yolcusu olan tur sayısı. */
    public static function getNavigationBadge(): ?string
    {
        if (! (auth()->user()?->managesOperations() ?? false)) {
            return null;
        }

        $count = TourDeparture::query()
            ->upcoming()
            ->whereHas('seatHoldingGroups', fn (Builder $q) => $q->whereNull('departure_vehicle_id')->where('passenger_count', '>', 0))
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Araçlara yerleşmemiş grubu olan yaklaşan tur';
    }

    public static function getRelations(): array
    {
        return [
            VehiclesRelationManager::class,
            GroupsRelationManager::class,
            CommissionsRelationManager::class,
            LedgerRelationManager::class,
        ];
    }

    public static function getRecordSubNavigation(Page $page): array
    {
        return $page->generateNavigationItems([
            ViewTourDeparture::class,
            DepartureAllocation::class,
            EditTourDeparture::class,
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTourDepartures::route('/'),
            'create' => CreateTourDeparture::route('/create'),
            'view' => ViewTourDeparture::route('/{record}'),
            'allocation' => DepartureAllocation::route('/{record}/arac-dagilimi'),
            'edit' => EditTourDeparture::route('/{record}/edit'),
        ];
    }
}
