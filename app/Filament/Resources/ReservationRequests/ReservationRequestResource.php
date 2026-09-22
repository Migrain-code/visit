<?php

namespace App\Filament\Resources\ReservationRequests;

use App\Filament\Resources\ReservationRequests\Pages\EditReservationRequest;
use App\Filament\Resources\ReservationRequests\Pages\ListReservationRequests;
use App\Filament\Resources\ReservationRequests\Pages\ViewReservationRequest;
use App\Filament\Resources\ReservationRequests\Schemas\ReservationRequestForm;
use App\Filament\Resources\ReservationRequests\Schemas\ReservationRequestInfolist;
use App\Filament\Resources\ReservationRequests\Tables\ReservationRequestsTable;
use App\Models\ReservationRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Web sitesinden gelen rezervasyon talepleri. Talep koltuk TUTMAZ; personel müşteriyle
 * görüşüp "Gruba dönüştür" ile kayıt açar.
 */
class ReservationRequestResource extends Resource
{
    protected static ?string $model = ReservationRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Operasyon';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Rezervasyon Talebi';

    protected static ?string $pluralModelLabel = 'Rezervasyon Talepleri';

    protected static ?string $slug = 'rezervasyon-talepleri';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return ReservationRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReservationRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReservationRequestsTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        if (! (auth()->user()?->registersGroups() ?? false)) {
            return null;
        }

        $count = ReservationRequest::query()->where('status', ReservationRequest::STATUS_NEW)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReservationRequests::route('/'),
            'view' => ViewReservationRequest::route('/{record}'),
            'edit' => EditReservationRequest::route('/{record}/edit'),
        ];
    }
}
